<?php
declare(strict_types=1);

/**
 * Rilevamento sovrapposizioni tra appuntamenti.
 *
 * Gli intervalli sono semiaperti [inizio, fine): un taglio 10:00-11:00 e un
 * colore 11:00-12:00 sono consecutivi, non in conflitto.
 * Gli orari sono "HH:MM"; i minuti sono contati da mezzanotte.
 */

namespace App;

/** "09:30" -> 570. Restituisce null se l'orario non è valido. */
function toMinutes(?string $time): ?int
{
    if ($time === null || !preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
        return null;
    }
    $h = (int)$m[1];
    $min = (int)$m[2];
    if ($h > 23 || $min > 59) {
        return null;
    }
    return $h * 60 + $min;
}

/** "09:30" + 45 minuti -> "10:15". */
function addMinutes(string $time, int $minutes): string
{
    $base = toMinutes($time) ?? 0;
    $total = $base + $minutes;
    return sprintf('%02d:%02d', intdiv($total, 60) % 24, $total % 60);
}

/**
 * Elementi di $busy che si sovrappongono a [$start, $end).
 * Un candidato non valido, o che non va avanti nel tempo, non ha conflitti:
 * spetta al chiamante rifiutarlo.
 *
 * @param array<int, array{kind:string,start_time:string,end_time:string,label:string}> $busy
 * @return array<int, array{kind:string,start_time:string,end_time:string,label:string}>
 */
function findConflicts(string $start, string $end, array $busy): array
{
    $s = toMinutes($start);
    $e = toMinutes($end);
    if ($s === null || $e === null || $e <= $s) {
        return [];
    }
    $out = [];
    foreach ($busy as $b) {
        $bs = toMinutes($b['start_time']);
        $be = toMinutes($b['end_time']);
        if ($bs === null || $be === null || $be <= $bs) {
            continue;
        }
        if ($s < $be && $bs < $e) {
            $out[] = $b;
        }
    }
    return $out;
}

/** Messaggio del 409: dice cosa è occupato e come procedere comunque. */
function describeConflicts(string $staffName, array $conflicts, string $action = 'book'): string
{
    $who = $staffName !== '' ? $staffName : 'Il collaboratore';
    $list = implode(', ', array_map(
        fn($c) => "{$c['start_time']}-{$c['end_time']} ({$c['label']})",
        $conflicts
    ));
    $allBlocked = true;
    foreach ($conflicts as $c) {
        if ($c['kind'] !== 'blocked') {
            $allBlocked = false;
        }
    }
    $what = $allBlocked ? 'non disponibile' : 'già occupato';
    $verb = $action === 'block' ? 'bloccare' : 'prenotare';
    return "$who è $what alle $list. Scegli un altro orario o collaboratore, oppure invia allow_conflict: true per $verb comunque.";
}
