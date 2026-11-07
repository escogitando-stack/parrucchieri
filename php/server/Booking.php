<?php
declare(strict_types=1);

namespace App;

/** Prenotazioni online: indirizzo pubblico del negozio (slug) e ricerca. */
final class Booking
{
    /** Parole che non possono essere usate come indirizzo (servono al sito). */
    private const RESERVED = ['admin', 'api', 'login', 'registrati', 'register', 'contatti', 'abbonamento', 'prenota', 'annulla', 'app', 'logout', 'dashboard', 'calendar', 'appointments', 'clients', 'staff', 'services', 'products', 'settings', 'stripe-webhook', 'logo', 'assets', 'favicon', 'robots', 'sitemap', 'index', 'public', 'static', 'www', 'mail', 'test'];

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

    /**
     * Il negozio può ricevere prenotazioni? (approvato, non sospeso, prova o abbonamento validi, prenotazioni attive)
     *
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $acct
     */
    public static function open(array $cfg, array $acct): bool
    {
        return !empty($acct['booking_enabled']) && Auth::canUse($cfg, $acct) && Auth::plan($acct)['state'] !== 'pending';
    }

    /**
     * Nuova richiesta di prenotazione dal sito pubblico: resta "da approvare" finché il titolare non risponde.
     * $in: name, phone, email, notes, date, time, staff_id, service_ids[], free (true = richiesta senza orario libero)
     *
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $acct
     * @param array<string,mixed> $in
     * @return array{ok:bool,error?:string,token?:string,id?:int}
     */
    public static function create(Db $db, array $cfg, array $acct, array $in, string $ip = '', ?int $now = null): array
    {
        $now ??= time();
        $fail = static fn(string $m): array => ['ok' => false, 'error' => $m];
        if (!self::open($cfg, $acct)) {
            return $fail('Le prenotazioni online non sono disponibili al momento.');
        }
        $accountId = (int)$acct['id'];
        $name = trim((string)($in['name'] ?? ''));
        $phone = trim((string)($in['phone'] ?? ''));
        $email = strtolower(trim((string)($in['email'] ?? '')));
        $notes = trim((string)($in['notes'] ?? ''));
        $date = (string)($in['date'] ?? '');
        $time = (string)($in['time'] ?? '');
        $free = !empty($in['free']);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            return $fail('Inserisci il tuo nome.');
        }
        if (strlen(preg_replace('/\D/', '', $phone) ?? '') < 6 || strlen($phone) > 30) {
            return $fail('Inserisci un numero di telefono valido.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            return $fail('Inserisci un indirizzo email valido: ti serve per ricevere la conferma.');
        }
        if (mb_strlen($notes) > 500) {
            return $fail('Le note sono troppo lunghe (massimo 500 caratteri).');
        }
        $pick = Availability::pick($db, $accountId, (array)($in['service_ids'] ?? []));
        if ($pick === null) {
            return $fail('Scegli almeno un servizio.');
        }
        if (!Availability::dateAllowed($acct, $date, $now)) {
            return $fail('Scegli una data valida.');
        }
        $start = toMinutes($time);
        if ($start === null || $start + $pick['duration'] >= 24 * 60) {
            return $fail('Scegli un orario valido.');
        }
        $staffId = (int)($in['staff_id'] ?? 0) ?: null;
        $ipHash = $ip !== '' ? hash('sha256', $ip . '|' . $accountId) : '';

        if ($ipHash !== '' && (int)$db->row("SELECT COUNT(*) AS c FROM appointments WHERE account_id = ? AND ip_hash = ? AND created_at >= ?", [$accountId, $ipHash, date('Y-m-d H:i:s', $now - 3600)])['c'] >= 5) {
            return $fail('Hai inviato molte richieste: riprova più tardi o chiama il negozio.');
        }
        $open = (int)$db->row(
            "SELECT COUNT(*) AS c FROM appointments a JOIN clients c ON c.id = a.client_id WHERE a.account_id = ? AND a.status = 'requested' AND c.email = ?",
            [$accountId, $email]
        )['c'];
        if ($open >= 3) {
            return $fail('Hai già alcune richieste in attesa di conferma: aspetta la risposta del negozio.');
        }

        if (!$db->lock('book_' . $accountId)) {
            return $fail('Il sistema è occupato, riprova tra un istante.');
        }
        try {
            if (!$free) {
                $slots = Availability::slots($db, $acct, $date, $pick['duration'], $staffId, $now);
                if (!isset($slots[$time])) {
                    return $fail('Questo orario è appena stato occupato: scegline un altro.');
                }
                $staffId = $slots[$time];
            } elseif ($staffId !== null && $db->row('SELECT id FROM staff WHERE id = ? AND account_id = ? AND active = 1', [$staffId, $accountId]) === null) {
                $staffId = null;
            }
            $client = $db->row('SELECT * FROM clients WHERE account_id = ? AND email = ? LIMIT 1', [$accountId, $email]);
            if ($client === null) {
                $db->exec('INSERT INTO clients (account_id, name, email, phone, notes) VALUES (?, ?, ?, ?, ?)', [$accountId, $name, $email, $phone, '']);
                $client = $db->row('SELECT * FROM clients WHERE id = ?', [$db->lastId()]);
            }
            $end = addMinutes($time, $pick['duration']);
            $token = bin2hex(random_bytes(16));
            $text = $free ? 'Richiesta fuori disponibilità: orario preferito ' . $time . '.' . ($notes !== '' ? ' ' . $notes : '') : $notes;
            $db->exec(
                "INSERT INTO appointments (account_id, identifier, client_id, staff_id, status, scheduled_date, start_time, end_time, total_price, notes, source, public_token, ip_hash)
                 VALUES (?, ?, ?, ?, 'requested', ?, ?, ?, ?, ?, ?, ?, ?)",
                [$accountId, 'APP-' . $db->nextCounter($accountId), (int)$client['id'], $staffId, $date, $time, $end, $pick['price'], $text, $free ? 'online_free' : 'online', $token, $ipHash]
            );
            $aptId = $db->lastId();
            foreach ($pick['ids'] as $sid) {
                $sv = $db->row('SELECT price, duration FROM services WHERE id = ? AND account_id = ?', [$sid, $accountId]);
                $db->exec('INSERT INTO appointment_services (appointment_id, service_id, price, duration) VALUES (?, ?, ?, ?)', [$aptId, $sid, $sv['price'], $sv['duration']]);
            }
        } finally {
            $db->unlock('book_' . $accountId);
        }
        $apt = self::detail($db, $aptId);
        if ($apt !== null) {
            Notify::requestReceived($cfg, $acct, $apt, $client ?? [], self::serviceNames($db, $aptId));
        }
        return ['ok' => true, 'token' => $token, 'id' => $aptId];
    }

    /** @return array<string,mixed>|null */
    public static function detail(Db $db, int $id): ?array
    {
        return $db->row('SELECT a.*, s.name AS staff_name FROM appointments a LEFT JOIN staff s ON s.id = a.staff_id WHERE a.id = ?', [$id]);
    }

    public static function serviceNames(Db $db, int $aptId): string
    {
        return implode(', ', array_map(static fn($r): string => (string)$r['name'], $db->all(
            'SELECT sv.name FROM appointment_services aps JOIN services sv ON sv.id = aps.service_id WHERE aps.appointment_id = ? ORDER BY aps.id',
            [$aptId]
        )));
    }

    /** Appuntamento da annullare tramite il link ricevuto per email. @return array<string,mixed>|null */
    public static function byToken(Db $db, string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        return $db->row(
            "SELECT a.*, s.name AS staff_name, c.name AS client_name, c.email AS client_email FROM appointments a
             LEFT JOIN staff s ON s.id = a.staff_id LEFT JOIN clients c ON c.id = a.client_id WHERE a.public_token = ?",
            [$token]
        );
    }

    /** @param array<string,mixed> $cfg */
    public static function cancelByToken(Db $db, array $cfg, string $token): bool
    {
        $apt = self::byToken($db, $token);
        if ($apt === null || !in_array($apt['status'], ['requested', 'booked', 'confirmed'], true) || $apt['scheduled_date'] < date('Y-m-d')) {
            return false;
        }
        $db->exec("UPDATE appointments SET status = 'cancelled' WHERE id = ?", [(int)$apt['id']]);
        $acct = Auth::account($db, (int)$apt['account_id']);
        if ($acct !== null) {
            Notify::cancelledByCustomer($cfg, $acct, $apt, ['name' => $apt['client_name'], 'email' => $apt['client_email']], self::serviceNames($db, (int)$apt['id']));
        }
        return true;
    }
}
