<?php
declare(strict_types=1);

namespace App;

/**
 * Generatore di codici QR (byte, correzione errori M, versioni 1-10: fino a 213 caratteri).
 * Serve per il cartello con il link della pagina di prenotazione.
 */
final class Qr
{
    /** versione → [codici di correzione per blocco, [[numero blocchi, codici dati per blocco], ...]] */
    private const BLOCKS = [
        1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]], 4 => [18, [[2, 32]]], 5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]], 7 => [18, [[4, 31]]], 8 => [22, [[2, 38], [2, 39]]], 9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]],
    ];
    private const ALIGN = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];

    /** @var array<int,int> */
    private static array $exp = [];
    /** @var array<int,int> */
    private static array $log = [];

    private static function gfInit(): void
    {
        if (self::$exp !== []) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function mul(int $a, int $b): int
    {
        return ($a === 0 || $b === 0) ? 0 : self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** @param array<int,int> $data @return array<int,int> */
    private static function rs(array $data, int $ecLen): array
    {
        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $c) {
                $next[$j] ^= $c;
                $next[$j + 1] ^= self::mul($c, self::$exp[$i]);
            }
            $gen = $next;
        }
        $rem = array_fill(0, $ecLen, 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($rem);
            $rem[] = 0;
            if ($factor !== 0) {
                foreach ($rem as $i => $_) {
                    $rem[$i] ^= self::mul($gen[$i + 1], $factor);
                }
            }
        }
        return $rem;
    }

    private static function dataCapacity(int $v): int
    {
        $n = 0;
        foreach (self::BLOCKS[$v][1] as [$count, $dcw]) {
            $n += $count * $dcw;
        }
        return $n;
    }

    /** @return array<int,array<int,bool>>|null righe di moduli (true = scuro); null se il testo è troppo lungo */
    public static function matrix(string $text): ?array
    {
        self::gfInit();
        $len = strlen($text);
        $version = 0;
        for ($v = 1; $v <= 10; $v++) {
            if (4 + ($v < 10 ? 8 : 16) + 8 * $len <= self::dataCapacity($v) * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === 0 || $len === 0) {
            return null;
        }
        $size = 17 + 4 * $version;

        // 1. Bit dei dati
        $bits = '0100' . str_pad(decbin($len), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach (str_split($text) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $capBits = self::dataCapacity($version) * 8;
        $bits .= str_repeat('0', min(4, $capBits - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $bytes = array_map('bindec', str_split($bits, 8));
        for ($pad = 0xEC; count($bytes) < self::dataCapacity($version); $pad ^= 0xEC ^ 0x11) {
            $bytes[] = $pad;
        }

        // 2. Blocchi e correzione errori
        [$ecLen, $groups] = self::BLOCKS[$version];
        $blocks = [];
        $pos = 0;
        foreach ($groups as [$count, $dcw]) {
            for ($i = 0; $i < $count; $i++) {
                $d = array_slice($bytes, $pos, $dcw);
                $pos += $dcw;
                $blocks[] = ['data' => $d, 'ec' => self::rs($d, $ecLen)];
            }
        }
        $out = [];
        $maxData = max(array_map(static fn(array $b): int => count($b['data']), $blocks));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocks as $b) {
                if (isset($b['data'][$i])) {
                    $out[] = $b['data'][$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($blocks as $b) {
                $out[] = $b['ec'][$i];
            }
        }

        // 3. Moduli fissi
        $mod = array_fill(0, $size, array_fill(0, $size, false));
        $fn = array_fill(0, $size, array_fill(0, $size, false));
        $set = static function (int $x, int $y, bool $dark) use (&$mod, &$fn, $size): void {
            if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
                $mod[$y][$x] = $dark;
                $fn[$y][$x] = true;
            }
        };
        for ($i = 0; $i < $size; $i++) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $dist = max(abs($dx), abs($dy));
                    $set($cx + $dx, $cy + $dy, $dist !== 2 && $dist !== 4);
                }
            }
        }
        $ap = self::ALIGN[$version];
        $last = count($ap) - 1;
        foreach ($ap as $i => $ax) {
            foreach ($ap as $j => $ay) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $set($ax + $dx, $ay + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        if ($version >= 7) {
            $rem = $version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $vbits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($vbits >> $i) & 1) === 1;
                $a = $size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $set($a, $b, $bit);
                $set($b, $a, $bit);
            }
        }
        // Zone del formato (riservate; il valore si scrive dopo la scelta della maschera)
        $formatCells = [];
        for ($i = 0; $i <= 5; $i++) {
            $formatCells[$i] = [[8, $i], [$size - 1 - $i, 8]];
        }
        $formatCells[6] = [[8, 7], [$size - 7, 8]];
        $formatCells[7] = [[8, 8], [$size - 8, 8]];
        $formatCells[8] = [[7, 8], [8, $size - 7]];
        for ($i = 9; $i < 15; $i++) {
            $formatCells[$i] = [[14 - $i, 8], [8, $size - 15 + $i]];
        }
        foreach ($formatCells as $cells) {
            foreach ($cells as [$x, $y]) {
                $set($x, $y, false);
            }
        }
        $set(8, $size - 8, true);

        // 4. Dati a zig-zag
        $i = 0;
        $total = count($out) * 8;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $y = ((($right + 1) & 2) === 0) ? $size - 1 - $vert : $vert;
                    if (!$fn[$y][$x] && $i < $total) {
                        $mod[$y][$x] = (($out[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }

        // 5. Maschera migliore
        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $m = $mod;
            for ($y = 0; $y < $size; $y++) {
                for ($x = 0; $x < $size; $x++) {
                    if ($fn[$y][$x]) {
                        continue;
                    }
                    $inv = match ($mask) {
                        0 => ($x + $y) % 2 === 0,
                        1 => $y % 2 === 0,
                        2 => $x % 3 === 0,
                        3 => ($x + $y) % 3 === 0,
                        4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                        5 => $x * $y % 2 + $x * $y % 3 === 0,
                        6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                        default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                    };
                    if ($inv) {
                        $m[$y][$x] = !$m[$y][$x];
                    }
                }
            }
            // formato: correzione M = 00
            $data = (0 << 3) | $mask;
            $rem = $data;
            for ($k = 0; $k < 10; $k++) {
                $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
            }
            $fbits = ((($data << 10) | $rem) ^ 0x5412);
            foreach ($formatCells as $k => $cells) {
                foreach ($cells as [$x, $y]) {
                    $m[$y][$x] = (($fbits >> $k) & 1) === 1;
                }
            }
            $m[$size - 8][8] = true;
            $score = self::penalty($m, $size);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $m;
            }
        }
        return $best;
    }

    /** @param array<int,array<int,bool>> $m */
    private static function penalty(array $m, int $size): int
    {
        $score = 0;
        $dark = 0;
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $size; $a++) {
                $run = 1;
                $line = '';
                for ($b = 0; $b < $size; $b++) {
                    $c = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
                    $line .= $c ? '1' : '0';
                    if ($pass === 0 && $c) {
                        $dark++;
                    }
                    if ($b > 0) {
                        $prev = $pass === 0 ? $m[$a][$b - 1] : $m[$b - 1][$a];
                        if ($prev === $c) {
                            $run++;
                            if ($run === 5) {
                                $score += 3;
                            } elseif ($run > 5) {
                                $score++;
                            }
                        } else {
                            $run = 1;
                        }
                    }
                }
                $score += 40 * (substr_count($line, '10111010000') + substr_count($line, '00001011101'));
            }
        }
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                if ($m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $total = $size * $size;
        $score += ((int)ceil(abs($dark * 20 - $total * 10) / $total) - 1) * 10;
        return $score;
    }

    /** Immagine SVG del codice (con margine bianco). */
    public static function svg(string $text, int $px = 240): ?string
    {
        $m = self::matrix($text);
        if ($m === null) {
            return null;
        }
        $n = count($m);
        $q = 4;
        $d = '';
        foreach ($m as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $d .= 'M' . ($x + $q) . ',' . ($y + $q) . 'h1v1h-1z';
                }
            }
        }
        $s = $n + 2 * $q;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $s . ' ' . $s . '" width="' . $px . '" height="' . $px . '" shape-rendering="crispEdges" role="img" aria-label="Codice QR">'
            . '<rect width="' . $s . '" height="' . $s . '" fill="#fff"/><path d="' . $d . '" fill="#000"/></svg>';
    }
}
