<?php
declare(strict_types=1);

namespace App;

/** Prenotazioni online: indirizzo pubblico del negozio (slug) e ricerca. */
final class Booking
{
    /** Parole che non possono essere usate come indirizzo (servono al sito). */
    private const RESERVED = ['admin', 'api', 'login', 'registrati', 'register', 'contatti', 'abbonamento', 'prenota', 'annulla', 'gestisci', 'calendario', 'sposta', 'password-dimenticata', 'reimposta', 'app', 'logout', 'dashboard', 'calendar', 'appointments', 'clients', 'staff', 'services', 'products', 'settings', 'stripe-webhook', 'logo', 'assets', 'favicon', 'robots', 'sitemap', 'index', 'public', 'static', 'www', 'mail', 'test'];

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
     * $in: name, phone, email (almeno uno dei due), consent, notes, date, time, staff_id, service_ids[], free (true = richiesta senza orario libero)
     *
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $acct
     * @param array<string,mixed> $in
     * @return array{ok:bool,error?:string,token?:string,id?:int,auto?:bool,email?:string,phone?:string}
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
        if ($phone === '' && $email === '') {
            return $fail('Inserisci almeno un recapito: telefono oppure email.');
        }
        if ($phone !== '' && (strlen(preg_replace('/\D/', '', $phone) ?? '') < 6 || strlen($phone) > 30)) {
            return $fail('Il numero di telefono non sembra valido.');
        }
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
            return $fail('L\'indirizzo email non sembra valido.');
        }
        if (empty($in['consent'])) {
            return $fail('Per inviare la richiesta devi accettare l\'informativa sulla privacy.');
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
            "SELECT COUNT(*) AS c FROM appointments a JOIN clients c ON c.id = a.client_id WHERE a.account_id = ? AND a.status = 'requested' AND (c.email = ? OR c.phone = ?)",
            [$accountId, $email !== '' ? $email : "\0", $phone !== '' ? $phone : "\0"]
        )['c'];
        if ($open >= 3) {
            return $fail('Hai già alcune richieste in attesa di conferma: aspetta la risposta del negozio.');
        }

        $auto = false;
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
            $client = $email !== '' ? $db->row('SELECT * FROM clients WHERE account_id = ? AND email = ? LIMIT 1', [$accountId, $email]) : null;
            if ($client === null && $phone !== '') {
                $client = $db->row('SELECT * FROM clients WHERE account_id = ? AND phone = ? LIMIT 1', [$accountId, $phone]);
            }
            if ($client === null) {
                $db->exec('INSERT INTO clients (account_id, name, email, phone, notes) VALUES (?, ?, ?, ?, ?)', [$accountId, $name, $email, $phone, '']);
                $client = $db->row('SELECT * FROM clients WHERE id = ?', [$db->lastId()]);
            }
            $end = addMinutes($time, $pick['duration']);
            $token = bin2hex(random_bytes(16));
            $text = $free ? 'Richiesta fuori disponibilità: orario preferito ' . $time . '.' . ($notes !== '' ? ' ' . $notes : '') : $notes;
            $auto = !$free && $pick['auto'];
            $db->exec(
                "INSERT INTO appointments (account_id, identifier, client_id, staff_id, status, scheduled_date, start_time, end_time, total_price, notes, source, public_token, ip_hash, consent_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$accountId, 'APP-' . $db->nextCounter($accountId), (int)$client['id'], $staffId, $auto ? 'confirmed' : 'requested', $date, $time, $end, $pick['price'], $text, $free ? 'online_free' : 'online', $token, $ipHash, date('Y-m-d H:i:s', $now)]
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
            if ($auto) {
                Notify::bookingConfirmed($cfg, $acct, $apt, $client ?? [], self::serviceNames($db, $aptId));
            } else {
                Notify::requestReceived($cfg, $acct, $apt, $client ?? [], self::serviceNames($db, $aptId));
            }
        }
        return ['ok' => true, 'token' => $token, 'id' => $aptId, 'auto' => $auto, 'email' => $email, 'phone' => $phone];
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
        if ($apt === null || !self::changeable($apt)) {
            return false;
        }
        $db->exec("UPDATE appointments SET status = 'cancelled' WHERE id = ?", [(int)$apt['id']]);
        $acct = Auth::account($db, (int)$apt['account_id']);
        if ($acct !== null) {
            Notify::cancelledByCustomer($cfg, $acct, $apt, ['name' => $apt['client_name'], 'email' => $apt['client_email']], self::serviceNames($db, (int)$apt['id']));
        }
        return true;
    }

    /** Richiesta da gestire con il link firmato ricevuto dal titolare. @param array<string,mixed> $cfg @return array<string,mixed>|null */
    public static function byManageLink(Db $db, array $cfg, string $token, string $sig): ?array
    {
        if (!Notify::verify($cfg, 'gestisci|' . $token, $sig)) {
            return null;
        }
        return self::byToken($db, $token);
    }

    /**
     * Il titolare approva o rifiuta una richiesta (dal link nell'email).
     * Se il collaboratore non è già indicato si prende il primo attivo; se l'orario è occupato non si conferma.
     *
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $apt riga di byToken()
     * @return array{ok:bool,error?:string}
     */
    public static function decide(Db $db, array $cfg, array $apt, bool $approve): array
    {
        if ($apt['status'] !== 'requested') {
            return ['ok' => false, 'error' => 'Questa richiesta è già stata gestita.'];
        }
        $accountId = (int)$apt['account_id'];
        $acct = Auth::account($db, $accountId);
        if ($acct === null) {
            return ['ok' => false, 'error' => 'Negozio non trovato.'];
        }
        if ($approve) {
            $candidates = $apt['staff_id'] !== null
                ? [(int)$apt['staff_id']]
                : array_map(static fn(array $r): int => (int)$r['id'], $db->all('SELECT id FROM staff WHERE account_id = ? AND active = 1 ORDER BY id', [$accountId]));
            $staffId = 0;
            foreach ($candidates as $cand) {
                // Impegni dello stesso collaboratore, esclusa la richiesta stessa; i blocchi contano sempre.
                $others = [];
                foreach ($db->all("SELECT start_time, end_time FROM appointments WHERE account_id = ? AND staff_id = ? AND scheduled_date = ? AND status <> 'cancelled' AND id <> ?", [$accountId, $cand, $apt['scheduled_date'], (int)$apt['id']]) as $a) {
                    $others[] = ['kind' => 'appointment', 'start_time' => (string)$a['start_time'], 'end_time' => (string)$a['end_time'], 'label' => 'occupato'];
                }
                foreach (Availability::busy($db, $accountId, $cand, (string)$apt['scheduled_date']) as $bl) {
                    if ($bl['kind'] === 'blocked') {
                        $others[] = $bl;
                    }
                }
                if (findConflicts((string)$apt['start_time'], (string)$apt['end_time'], $others) === []) {
                    $staffId = $cand;
                    break;
                }
            }
            if ($staffId === 0) {
                return ['ok' => false, 'error' => $candidates === [] ? 'Nessun collaboratore disponibile: apri il pannello per confermare.' : 'A quell\'ora c\'è già un impegno: apri il pannello per scegliere un altro orario.'];
            }
            $db->exec("UPDATE appointments SET status = 'confirmed', staff_id = ? WHERE id = ? AND account_id = ?", [$staffId, (int)$apt['id'], $accountId]);
        } else {
            $db->exec("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND account_id = ?", [(int)$apt['id'], $accountId]);
        }
        $fresh = self::byToken($db, (string)$apt['public_token']) ?? $apt;
        Notify::decision($cfg, $acct, $fresh, ['name' => $fresh['client_name'], 'email' => $fresh['client_email']], self::serviceNames($db, (int)$apt['id']), $approve);
        return ['ok' => true];
    }

    /** File calendario (.ics) di una prenotazione. @param array<string,mixed> $acct @param array<string,mixed> $apt */
    public static function ics(array $acct, array $apt, string $services): string
    {
        $esc = static fn(string $s): string => str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\\;', '\\,', '\\n', '\\n'], $s);
        $stamp = static fn(string $d, string $t): string => str_replace('-', '', $d) . 'T' . str_replace(':', '', $t) . '00';
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Appuntamenti4me//IT', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:' . $apt['public_token'] . '@appuntamenti4me',
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $stamp((string)$apt['scheduled_date'], (string)$apt['start_time']),
            'DTEND:' . $stamp((string)$apt['scheduled_date'], (string)$apt['end_time']),
            'SUMMARY:' . $esc(($services !== '' ? $services . ' - ' : '') . $acct['business_name']),
        ];
        if (!empty($acct['shop_address'])) {
            $lines[] = 'LOCATION:' . $esc((string)$acct['shop_address']);
        }
        $lines[] = 'STATUS:' . ($apt['status'] === 'cancelled' ? 'CANCELLED' : 'CONFIRMED');
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines) . "\r\n";
    }

    /** Durata dell'appuntamento in minuti. @param array<string,mixed> $apt */
    public static function duration(array $apt): int
    {
        return max(5, (toMinutes((string)$apt['end_time']) ?? 0) - (toMinutes((string)$apt['start_time']) ?? 0));
    }

    /** L'appuntamento si può ancora spostare o annullare dal cliente? @param array<string,mixed> $apt */
    public static function changeable(array $apt, ?int $now = null): bool
    {
        return in_array($apt['status'], ['requested', 'booked', 'confirmed'], true) && $apt['scheduled_date'] >= date('Y-m-d', $now ?? time());
    }

    /**
     * Il cliente sposta la prenotazione (dal link ricevuto per email).
     * Resta confermata se tutti i suoi servizi sono a conferma immediata, altrimenti torna "da approvare".
     *
     * @param array<string,mixed> $cfg
     * @return array{ok:bool,error?:string,status?:string}
     */
    public static function reschedule(Db $db, array $cfg, string $token, string $date, string $time, ?int $now = null, ?array $serviceIds = null): array
    {
        $now ??= time();
        $apt = self::byToken($db, $token);
        if ($apt === null || !self::changeable($apt, $now)) {
            return ['ok' => false, 'error' => 'Questa prenotazione non può più essere spostata.'];
        }
        $accountId = (int)$apt['account_id'];
        $acct = Auth::account($db, $accountId);
        if ($acct === null || !self::open($cfg, $acct)) {
            return ['ok' => false, 'error' => 'Le prenotazioni online non sono disponibili al momento.'];
        }
        if (!Availability::dateAllowed($acct, $date, $now) || toMinutes($time) === null) {
            return ['ok' => false, 'error' => 'Scegli una data e un orario validi.'];
        }
        $ids = array_map(static fn(array $r): int => (int)$r['service_id'], $db->all('SELECT service_id FROM appointment_services WHERE appointment_id = ?', [(int)$apt['id']]));
        $duration = self::duration($apt);
        $changed = false;
        if ($serviceIds !== null) {
            $np = Availability::pick($db, $accountId, $serviceIds);
            if ($np === null) {
                return ['ok' => false, 'error' => 'Scegli almeno un servizio tra quelli disponibili.'];
            }
            $sorted = static function (array $a): array { $a = array_map('intval', $a); sort($a); return $a; };
            $changed = $sorted($np['ids']) !== $sorted($ids);
            if ($changed) {
                $ids = $np['ids'];
                $duration = $np['duration'];
            }
        }
        $pick = Availability::pick($db, $accountId, $ids);
        $auto = $pick !== null && $pick['auto'] && $apt['source'] !== 'online_free';
        if (!$db->lock('book_' . $accountId)) {
            return ['ok' => false, 'error' => 'Il sistema è occupato, riprova tra un istante.'];
        }
        try {
            $slots = Availability::slots($db, $acct, $date, $duration, null, $now, (int)$apt['id']);
            if (!isset($slots[$time])) {
                return ['ok' => false, 'error' => 'Questo orario è appena stato occupato: scegline un altro.'];
            }
            $staffId = $slots[$time];
            if ($apt['staff_id'] !== null) {
                // Se può, resta con la stessa persona.
                $same = Availability::slotsForStaff($db, $acct, (int)$apt['staff_id'], $date, $duration, $now, (int)$apt['id']);
                if (in_array($time, $same, true)) {
                    $staffId = (int)$apt['staff_id'];
                }
            }
            $status = $auto ? 'confirmed' : 'requested';
            $db->exec(
                'UPDATE appointments SET scheduled_date = ?, start_time = ?, end_time = ?, staff_id = ?, status = ?, reminded = 0 WHERE id = ? AND account_id = ?',
                [$date, $time, addMinutes($time, $duration), $staffId, $status, (int)$apt['id'], $accountId]
            );
            if ($changed) {
                $db->exec('DELETE FROM appointment_services WHERE appointment_id = ?', [(int)$apt['id']]);
                foreach ($db->all('SELECT id, price, duration FROM services WHERE account_id = ? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$accountId], $ids)) as $sv) {
                    $db->exec('INSERT INTO appointment_services (appointment_id, service_id, price, duration) VALUES (?, ?, ?, ?)', [(int)$apt['id'], (int)$sv['id'], $sv['price'], $sv['duration']]);
                }
                if ($pick !== null) {
                    $db->exec('UPDATE appointments SET total_price = ? WHERE id = ? AND account_id = ?', [$pick['price'], (int)$apt['id'], $accountId]);
                }
            }
        } finally {
            $db->unlock('book_' . $accountId);
        }
        $fresh = self::byToken($db, $token) ?? $apt;
        Notify::rescheduled($cfg, $acct, $fresh, ['name' => $fresh['client_name'], 'email' => $fresh['client_email'], 'phone' => $db->row('SELECT phone FROM clients WHERE id = ?', [(int)$fresh['client_id']])['phone'] ?? ''], self::serviceNames($db, (int)$apt['id']), $status === 'confirmed', Notify::dateIt((string)$apt['scheduled_date']) . ' alle ' . $apt['start_time']);
        return ['ok' => true, 'status' => $status];
    }
}
