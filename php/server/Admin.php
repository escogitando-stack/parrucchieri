<?php
declare(strict_types=1);

namespace App;

/** Gestione dei negozi registrati (pannello amministratore). */
final class Admin
{
    public static function csrfToken(): string
    {
        Auth::start();
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
    }

    public static function checkCsrf(string $token): bool
    {
        Auth::start();
        return $token !== '' && hash_equals((string)($_SESSION['csrf'] ?? ''), $token);
    }

    /**
     * Tutti i negozi con piano, utilizzo e ultimo accesso.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function rows(Db $db, ?int $now = null, array $cfg = []): array
    {
        $rows = $db->all(
            'SELECT a.id, a.email, a.business_name, a.category, a.plan_status, a.trial_ends_at, a.paid_until, a.suspended,
                    a.last_login_at, a.created_at, a.stripe_customer_id, a.approved, a.slug, a.shop_email,
                    (SELECT COUNT(*) FROM clients c WHERE c.account_id = a.id) AS clients,
                    (SELECT COUNT(*) FROM appointments p WHERE p.account_id = a.id) AS appointments
             FROM accounts a ORDER BY a.id DESC'
        );
        foreach ($rows as &$r) {
            $r['is_admin'] = Auth::isAdmin($cfg, $r);
            $r['plan'] = $r['is_admin'] ? Auth::effectivePlan($cfg, $r) : Auth::plan($r, $now);
            $r['clients'] = (int)$r['clients'];
            $r['appointments'] = (int)$r['appointments'];
            $r['category_label'] = Categories::get((string)$r['category'])['label'];
            $r['is_demo'] = $db->metaGet('demo_of_' . (int)$r['id']) !== null;
        }
        unset($r);
        // I negozi da approvare stanno in cima, poi i più recenti.
        usort($rows, static fn(array $a, array $b): int => ((int)$a['approved'] <=> (int)$b['approved']) ?: ((int)$b['id'] <=> (int)$a['id']));
        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array{total:int,trial:int,active:int,expired:int,suspended:int,pending:int,new7:int}
     */
    public static function stats(array $rows, ?int $now = null): array
    {
        $now ??= time();
        $s = ['total' => count($rows), 'trial' => 0, 'active' => 0, 'expired' => 0, 'suspended' => 0, 'pending' => 0, 'new7' => 0];
        foreach ($rows as $r) {
            $s[$r['plan']['state']]++;
            if ((int)strtotime((string)$r['created_at']) >= $now - 7 * 86400) {
                $s['new7']++;
            }
        }
        return $s;
    }

    /** Aggiunge giorni alla prova (o, per chi paga, alla scadenza dell'abbonamento). */
    public static function extend(Db $db, int $id, int $days, ?int $now = null): bool
    {
        $now ??= time();
        $acct = Auth::account($db, $id);
        if ($acct === null || $days < 1 || $days > 3650) {
            return false;
        }
        if ($acct['plan_status'] === 'active') {
            $base = max($now, (int)strtotime((string)$acct['paid_until']));
            $db->exec('UPDATE accounts SET paid_until = ? WHERE id = ?', [date('Y-m-d H:i:s', $base + $days * 86400), $id]);
        } else {
            $base = max($now, (int)strtotime((string)$acct['trial_ends_at']));
            $db->exec("UPDATE accounts SET plan_status = 'trial', trial_ends_at = ? WHERE id = ?", [date('Y-m-d H:i:s', $base + $days * 86400), $id]);
        }
        return true;
    }

    /** Attiva l'account fino a una data (per esempio un cliente che paga con bonifico). */
    public static function activate(Db $db, int $id, string $until, ?int $now = null): bool
    {
        $now ??= time();
        $ts = strtotime($until . ' 23:59:59');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) || $ts === false || $ts < $now || Auth::account($db, $id) === null) {
            return false;
        }
        $db->exec("UPDATE accounts SET plan_status = 'active', paid_until = ? WHERE id = ?", [date('Y-m-d H:i:s', $ts), $id]);
        return true;
    }

    /** Approva un nuovo negozio: da ora parte la prova gratuita. Restituisce false se non esiste o era già approvato. */
    public static function approve(Db $db, array $cfg, int $id, ?int $now = null): bool
    {
        $now ??= time();
        $acct = Auth::account($db, $id);
        if ($acct === null || (int)$acct['approved'] === 1) {
            return false;
        }
        $days = max(1, (int)($cfg['trial_days'] ?? 30));
        $db->exec("UPDATE accounts SET approved = 1, plan_status = 'trial', trial_ends_at = ? WHERE id = ?", [date('Y-m-d H:i:s', $now + $days * 86400), $id]);
        Notify::accountApproved($cfg, Auth::account($db, $id) ?? $acct);
        return true;
    }

    public static function setSuspended(Db $db, int $id, bool $suspended): bool
    {
        return $db->exec('UPDATE accounts SET suspended = ? WHERE id = ?', [$suspended ? 1 : 0, $id]) > 0;
    }

    /** Imposta una password provvisoria e la restituisce (da comunicare al cliente). */
    public static function resetPassword(Db $db, int $id): ?string
    {
        if (Auth::account($db, $id) === null) {
            return null;
        }
        $pwd = self::randomPassword();
        $db->exec('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($pwd, PASSWORD_DEFAULT), $id]);
        return $pwd;
    }

    /** Elimina il negozio e tutti i suoi dati, senza possibilità di recupero. */
    public static function delete(Db $db, int $id): bool
    {
        if (Auth::account($db, $id) === null) {
            return false;
        }
        $db->begin();
        try {
            foreach (['appointments', 'blocked_slots', 'working_hours', 'products', 'services', 'staff', 'clients'] as $table) {
                $db->exec("DELETE FROM $table WHERE account_id = ?", [$id]);
            }
            $db->exec('DELETE FROM accounts WHERE id = ?', [$id]);
            $db->exec('DELETE FROM _meta WHERE meta_key IN (?, ?)', ['demo_of_' . $id, 'nosummary_' . $id]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return true;
    }

    /**
     * Duplica un negozio per preparare una demo personalizzata da mostrare a un possibile cliente.
     *
     * Copia impostazioni, collaboratori con i loro orari, servizi e prodotti; con copy_data anche
     * clienti, appuntamenti (con servizi e note) e chiusure. Il nuovo negozio è già approvato, in prova,
     * senza email automatiche (il riepilogo giornaliero è spento) e marcato come demo.
     *
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $in business_name, email, category, shop_phone, shop_whatsapp, shop_email,
     *                                shop_address, shop_description, shop_hours, copy_data, logo (data URL)
     * @return array{ok:bool,error?:string,id?:int,email?:string,password?:string,slug?:string}
     */
    public static function duplicate(Db $db, array $cfg, int $sourceId, array $in, ?int $now = null): array
    {
        $now ??= time();
        $src = Auth::account($db, $sourceId);
        if ($src === null) {
            return ['ok' => false, 'error' => 'Negozio da duplicare non trovato.'];
        }
        $name = trim((string)($in['business_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            return ['ok' => false, 'error' => 'Scrivi il nome dell\'attività del nuovo cliente.'];
        }
        $category = trim((string)($in['category'] ?? ''));
        if ($category === '') {
            $category = (string)$src['category'];
        } elseif (!Categories::valid($category) && $category !== (string)$src['category']) {
            return ['ok' => false, 'error' => 'Categoria non valida.'];
        }
        $slug = Booking::makeSlug($db, $name);
        $email = strtolower(trim((string)($in['email'] ?? '')));
        if ($email === '') {
            $host = preg_replace('/^www\./', '', (string)(parse_url((string)($cfg['base_url'] ?? ''), PHP_URL_HOST) ?: ''));
            $email = 'demo-' . $slug . '@' . (str_contains((string)$host, '.') ? $host : 'appuntamenti4me.it');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            return ['ok' => false, 'error' => 'L\'email di accesso non è valida.'];
        }
        if ($db->row('SELECT id FROM accounts WHERE email = ?', [$email]) !== null) {
            return ['ok' => false, 'error' => 'Esiste già un account con l\'email ' . $email . '.'];
        }
        $shopEmail = trim((string)($in['shop_email'] ?? ''));
        if ($shopEmail !== '' && !filter_var($shopEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'L\'email del negozio non è valida.'];
        }
        $logo = null;
        $logoUrl = (string)($in['logo'] ?? '');
        if ($logoUrl !== '') {
            $logo = Shop::parseLogo($logoUrl);
            if ($logo === null) {
                return ['ok' => false, 'error' => 'Logo non valido: usa un\'immagine PNG, JPEG, WebP o GIF fino a 400 KB.'];
            }
        }
        $field = static fn(string $k, int $max): string => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);
        $hours = $field('shop_hours', 500);
        $copyData = !empty($in['copy_data']);
        $password = self::randomPassword();
        $days = max(1, (int)($cfg['trial_days'] ?? 30));

        $db->begin();
        try {
            $db->exec(
                'INSERT INTO accounts (email, password_hash, business_name, category, plan_status, trial_ends_at, approved, slug,
                    booking_enabled, slot_minutes, booking_notice_hours, booking_horizon_days,
                    shop_phone, shop_whatsapp, shop_email, shop_address, shop_description, shop_hours,
                    logo_mime, logo_data, appointment_counter, last_login_at, trial_mail_stage, onboard_mail)
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 9, 1)',
                [
                    $email, password_hash($password, PASSWORD_DEFAULT), $name, $category, 'trial', date('Y-m-d H:i:s', $now + $days * 86400), $slug,
                    (int)$src['booking_enabled'], (int)$src['slot_minutes'], (int)$src['booking_notice_hours'], (int)$src['booking_horizon_days'],
                    $field('shop_phone', 64), $field('shop_whatsapp', 64), $shopEmail, $field('shop_address', 255), $field('shop_description', 1000),
                    $hours !== '' ? $hours : (string)$src['shop_hours'],
                    $logo['mime'] ?? '', $logo['data'] ?? null, $copyData ? (int)$src['appointment_counter'] : 0, '',
                ]
            );
            $id = $db->lastId();

            $staffMap = self::copyRows($db, 'staff', $sourceId, $id);
            foreach ($db->all('SELECT * FROM working_hours WHERE account_id = ?', [$sourceId]) as $r) {
                if (isset($staffMap[(int)$r['staff_id']])) {
                    $db->exec('INSERT INTO working_hours (account_id, staff_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?, ?)',
                        [$id, $staffMap[(int)$r['staff_id']], (int)$r['weekday'], $r['start_time'], $r['end_time']]);
                }
            }
            $serviceMap = self::copyRows($db, 'services', $sourceId, $id);
            self::copyRows($db, 'products', $sourceId, $id);
            if ($staffMap === []) {
                // Un negozio senza collaboratori non può ricevere appuntamenti: ne creiamo uno.
                $db->exec('INSERT INTO staff (account_id, name, title, color) VALUES (?, ?, ?, ?)', [$id, 'Titolare', Categories::get($category)['staff_title'], '#3b82f6']);
                Schema::defaultHours($db, $id, $db->lastId());
            }

            if ($copyData) {
                $clientMap = self::copyRows($db, 'clients', $sourceId, $id);
                foreach ($db->all('SELECT * FROM appointments WHERE account_id = ? ORDER BY id', [$sourceId]) as $a) {
                    if (!isset($clientMap[(int)$a['client_id']])) {
                        continue;
                    }
                    $oldId = (int)$a['id'];
                    unset($a['id']);
                    $a['account_id'] = $id;
                    $a['client_id'] = $clientMap[(int)$a['client_id']];
                    $a['staff_id'] = $a['staff_id'] !== null && isset($staffMap[(int)$a['staff_id']]) ? $staffMap[(int)$a['staff_id']] : null;
                    $a['public_token'] = ($a['public_token'] ?? '') !== '' ? bin2hex(random_bytes(16)) : '';
                    $a['ip_hash'] = '';
                    $a['reminded'] = 1; // nessun promemoria ai clienti di esempio
                    $newApt = self::insertRow($db, 'appointments', $a);
                    foreach ($db->all('SELECT * FROM appointment_services WHERE appointment_id = ?', [$oldId]) as $as) {
                        if (isset($serviceMap[(int)$as['service_id']])) {
                            $db->exec('INSERT INTO appointment_services (appointment_id, service_id, price, duration) VALUES (?, ?, ?, ?)',
                                [$newApt, $serviceMap[(int)$as['service_id']], $as['price'], (int)$as['duration']]);
                        }
                    }
                    foreach ($db->all('SELECT * FROM appointment_notes WHERE appointment_id = ?', [$oldId]) as $n) {
                        $db->exec('INSERT INTO appointment_notes (appointment_id, content, created_at) VALUES (?, ?, ?)', [$newApt, $n['content'], $n['created_at']]);
                    }
                }
                foreach ($db->all('SELECT * FROM blocked_slots WHERE account_id = ?', [$sourceId]) as $b) {
                    if (isset($staffMap[(int)$b['staff_id']])) {
                        $db->exec('INSERT INTO blocked_slots (account_id, staff_id, blocked_date, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?, ?)',
                            [$id, $staffMap[(int)$b['staff_id']], $b['blocked_date'], $b['start_time'], $b['end_time'], $b['reason']]);
                    }
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        // Niente email automatiche verso l'indirizzo demo; si riattiva dalle Impostazioni.
        $db->metaSet('nosummary_' . $id, '1');
        $db->metaSet('demo_of_' . $id, (string)$sourceId);
        return ['ok' => true, 'id' => $id, 'email' => $email, 'password' => $password, 'slug' => $slug];
    }

    /** Il negozio è una demo creata con "Duplica"? */
    public static function isDemo(Db $db, int $id): bool
    {
        return $db->metaGet('demo_of_' . $id) !== null;
    }

    /**
     * Consegna di una demo (o cambio email): nuova email di accesso. Restituisce un errore oppure null.
     */
    public static function changeEmail(Db $db, int $id, string $email): ?string
    {
        $email = strtolower(trim($email));
        if (Auth::account($db, $id) === null) {
            return 'Negozio non trovato.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            return 'Inserisci un indirizzo email valido.';
        }
        if ($db->row('SELECT id FROM accounts WHERE email = ? AND id <> ?', [$email, $id]) !== null) {
            return 'Questa email è già usata da un altro account.';
        }
        $db->exec('UPDATE accounts SET email = ?, trial_mail_stage = CASE WHEN trial_mail_stage = 9 THEN 0 ELSE trial_mail_stage END WHERE id = ?', [$email, $id]);
        // Da ora l'account appartiene a una persona vera: tornano le email automatiche.
        $db->exec('DELETE FROM _meta WHERE meta_key IN (?, ?)', ['demo_of_' . $id, 'nosummary_' . $id]);
        return null;
    }

    /**
     * Copia le righe di una tabella da un negozio all'altro. Restituisce la mappa vecchio id => nuovo id.
     *
     * @return array<int,int>
     */
    private static function copyRows(Db $db, string $table, int $from, int $to): array
    {
        $map = [];
        foreach ($db->all("SELECT * FROM $table WHERE account_id = ? ORDER BY id", [$from]) as $r) {
            $old = (int)$r['id'];
            unset($r['id']);
            $r['account_id'] = $to;
            $map[$old] = self::insertRow($db, $table, $r);
        }
        return $map;
    }

    /** @param array<string,mixed> $row */
    private static function insertRow(Db $db, string $table, array $row): int
    {
        $cols = array_keys($row);
        foreach ($cols as $c) {
            if (!preg_match('/^[a-z_]+$/', (string)$c)) {
                throw new \RuntimeException('Colonna non valida');
            }
        }
        $db->exec("INSERT INTO $table (" . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($row));
        return $db->lastId();
    }

    private static function randomPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $pwd = '';
        for ($i = 0; $i < 12; $i++) {
            $pwd .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $pwd;
    }
}
