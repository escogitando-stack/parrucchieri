<?php
declare(strict_types=1);

namespace App;

/** Prenotazioni online: indirizzo pubblico del negozio (slug) e ricerca. */
final class Booking
{
    /** Parole che non possono essere usate come indirizzo (servono al sito). */
    private const RESERVED = ['admin', 'api', 'login', 'registrati', 'register', 'contatti', 'abbonamento', 'prenota', 'annulla', 'app', 'logout'];

    /** Ricava un indirizzo breve e unico (a-z, 0-9, trattini) dal nome dell'attività. */
    public static function makeSlug(Db $db, string $name): string
    {
        $base = strtolower(trim($name));
        $base = strtr($base, ['à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u', 'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n']);
        $base = trim((string)preg_replace('/[^a-z0-9]+/', '-', $base), '-');
        $base = trim(substr($base, 0, 40), '-');
        if ($base === '' || in_array($base, self::RESERVED, true)) {
            $base = 'negozio' . ($base !== '' ? '-' . $base : '');
        }
        $slug = $base;
        for ($i = 2; $db->row('SELECT id FROM accounts WHERE slug = ?', [$slug]) !== null; $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    public static function slugValid(string $slug): bool
    {
        return (bool)preg_match('/^[a-z0-9](?:[a-z0-9-]{1,78}[a-z0-9])$/', $slug) && !in_array($slug, self::RESERVED, true);
    }

    /** @return array<string,mixed>|null */
    public static function accountBySlug(Db $db, string $slug): ?array
    {
        return self::slugValid($slug) ? $db->row('SELECT * FROM accounts WHERE slug = ?', [$slug]) : null;
    }
}
