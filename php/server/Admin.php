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
            'SELECT a.id, a.email, a.business_name, a.category, a.shop_phone, a.shop_whatsapp, a.plan_status, a.trial_ends_at, a.paid_until, a.suspended,
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
            $r['is_showcase'] = $db->metaGet('showcase_' . (int)$r['id']) !== null;
            $r['wa_demo'] = $r['is_demo'] ? self::demoWhatsappUrl($db, $cfg, $r) : '';
            $r['delivered'] = $db->metaGet('delivered_' . (int)$r['id']) !== null;
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

    /**
     * Andamento commerciale: prove in scadenza da richiamare, demo, conversioni.
     *
     * @param array<int,array<string,mixed>> $rows Admin::rows()
     * @return array{expiring:array<int,array<string,mixed>>,demos:int,delivered:int,paying:int,expired:int,conversion:?int,new30:int}
     */
    public static function insights(array $rows, ?int $now = null): array
    {
        $now ??= time();
        $expiring = [];
        $out = ['demos' => 0, 'delivered' => 0, 'paying' => 0, 'expired' => 0, 'new30' => 0];
        foreach ($rows as $r) {
            if (!empty($r['is_admin'])) {
                continue;
            }
            $state = $r['plan']['state'];
            if (!empty($r['is_demo'])) {
                $out['demos']++;
            } elseif ($state === 'trial' && $r['plan']['days_left'] <= 7) {
                $expiring[] = $r;
            }
            if (!empty($r['delivered'])) {
                $out['delivered']++;
            }
            if ($state === 'active') {
                $out['paying']++;
            } elseif ($state === 'expired') {
                $out['expired']++;
            }
            if ((int)strtotime((string)$r['created_at']) >= $now - 30 * 86400) {
                $out['new30']++;
            }
        }
        usort($expiring, static fn($a, $b) => $a['plan']['days_left'] <=> $b['plan']['days_left']);
        $done = $out['paying'] + $out['expired'];
        return $out + ['expiring' => $expiring, 'conversion' => $done > 0 ? (int)round($out['paying'] * 100 / $done) : null];
    }

    /** Giorni di prova impostati dall'admin (null se non impostati: vale config.php). */
    public static function trialDays(Db $db): ?int
    {
        $v = $db->metaGet('trial_days');
        return $v !== null && ctype_digit($v) && (int)$v >= 1 ? (int)$v : null;
    }

    /** Imposta i giorni di prova dei NUOVI iscritti (1–365). */
    public static function setTrialDays(Db $db, int $days): bool
    {
        if ($days < 1 || $days > 365) {
            return false;
        }
        $db->metaSet('trial_days', (string)$days);
        return true;
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
            foreach (['appointments', 'blocked_slots', 'working_hours', 'products', 'services', 'staff', 'clients', 'shop_photos', 'client_packages', 'packages'] as $table) {
                $db->exec("DELETE FROM $table WHERE account_id = ?", [$id]);
            }
            $db->exec('DELETE FROM accounts WHERE id = ?', [$id]);
            $db->exec('DELETE FROM _meta WHERE meta_key IN (?, ?, ?, ?, ?)', ['demo_of_' . $id, 'nosummary_' . $id, 'showcase_' . $id, 'demo_phone_' . $id, 'delivered_' . $id]);
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
        $cover = null;
        $coverUrl = (string)($in['cover'] ?? '');
        if ($coverUrl !== '') {
            $cover = Shop::parseImage($coverUrl, 1500);
            if ($cover === null) {
                return ['ok' => false, 'error' => 'Foto di copertina non valida: usa un\'immagine PNG, JPEG o WebP fino a 1,5 MB.'];
            }
        }
        $review = trim((string)($in['review_url'] ?? ''));
        if (!Shop::reviewUrlValid($review)) {
            return ['ok' => false, 'error' => 'Il link per le recensioni deve iniziare con https://'];
        }
        $photos = array_slice(array_values(array_filter((array)($in['photos'] ?? []), 'is_string')), 0, Shop::MAX_PHOTOS);
        foreach ($photos as $ph) {
            if (Shop::parseImage($ph, 1500) === null) {
                return ['ok' => false, 'error' => 'Una delle foto della galleria non è valida: usa PNG, JPEG o WebP fino a 1,5 MB.'];
            }
        }
        $field = static fn(string $k, int $max): string => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);
        $hours = $field('shop_hours', 500);
        $copyData = !empty($in['copy_data']);
        // Giorni e orari di apertura (facoltativi): valgono per tutti i collaboratori della demo.
        $openDays = array_values(array_unique(array_filter(array_map('intval', (array)($in['open_days'] ?? [])), static fn(int $d): bool => $d >= 1 && $d <= 7)));
        $ranges = [];
        foreach ([['am_from', 'am_to'], ['pm_from', 'pm_to']] as [$f, $t]) {
            $from = trim((string)($in[$f] ?? ''));
            $to = trim((string)($in[$t] ?? ''));
            if ($from === '' && $to === '') {
                continue;
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $from) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $to) || $from >= $to) {
                return ['ok' => false, 'error' => 'Controlla gli orari di apertura: ogni fascia deve avere inizio e fine (es. 09:00–13:00).'];
            }
            $ranges[] = [$from, $to];
        }
        if ($openDays !== [] && $ranges === []) {
            return ['ok' => false, 'error' => 'Hai scelto i giorni di apertura: indica anche almeno una fascia oraria.'];
        }
        $password = self::randomPassword();
        $days = max(1, (int)($cfg['trial_days'] ?? 30));

        $db->begin();
        try {
            $db->exec(
                'INSERT INTO accounts (email, password_hash, business_name, category, plan_status, trial_ends_at, approved, slug,
                    booking_enabled, slot_minutes, booking_notice_hours, booking_horizon_days,
                    shop_phone, shop_whatsapp, shop_email, shop_address, shop_description, shop_hours,
                    logo_mime, logo_data, cover_mime, cover_data, appointment_counter, last_login_at, trial_mail_stage, onboard_mail)
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 9, 1)',
                [
                    $email, password_hash($password, PASSWORD_DEFAULT), $name, $category, 'trial', date('Y-m-d H:i:s', $now + $days * 86400), $slug,
                    (int)$src['booking_enabled'], (int)$src['slot_minutes'], (int)$src['booking_notice_hours'], (int)$src['booking_horizon_days'],
                    $field('shop_phone', 64), $field('shop_whatsapp', 64), $shopEmail, $field('shop_address', 255), $field('shop_description', 1000),
                    $hours !== '' ? $hours : (string)$src['shop_hours'],
                    $logo['mime'] ?? '', $logo['data'] ?? null, $cover['mime'] ?? '', $cover['data'] ?? null, $copyData ? (int)$src['appointment_counter'] : 0, '',
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
            if ($openDays !== []) {
                Availability::setShopHours($db, $id, $openDays, $ranges);
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
                    $a['review_asked'] = 1;
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
            if ($review !== '') {
                $db->exec('UPDATE accounts SET review_url = ? WHERE id = ?', [$review, $id]);
            }
            foreach ($photos as $ph) {
                Shop::addPhoto($db, $id, $ph);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        // Niente email automatiche verso l'indirizzo demo; si riattiva dalle Impostazioni.
        $db->metaSet('nosummary_' . $id, '1');
        $db->metaSet('demo_of_' . $id, (string)$sourceId);
        $prospect = trim((string)($in['prospect_phone'] ?? ''));
        if ($prospect !== '') {
            $db->metaSet('demo_phone_' . $id, mb_substr($prospect, 0, 40));
        }
        return ['ok' => true, 'id' => $id, 'email' => $email, 'password' => $password, 'slug' => $slug];
    }

    /**
     * Consegna in un passo: email del cliente, link per scegliere la password (inviato per email)
     * ed eventualmente un piano. Restituisce un errore oppure null.
     *
     * @param array<string,mixed> $cfg
     */
    public static function deliver(Db $db, array $cfg, int $id, string $email, int $planId = 0, ?int $now = null): ?string
    {
        $now ??= time();
        if ($planId > 0 && Plans::find($db, $planId) === null) {
            return 'Piano non trovato.';
        }
        $err = self::changeEmail($db, $id, $email);
        if ($err !== null) {
            return $err;
        }
        // Password casuale finché il cliente non sceglie la sua dal link.
        self::resetPassword($db, $id);
        $token = bin2hex(random_bytes(16));
        $db->exec('DELETE FROM password_resets WHERE account_id = ?', [$id]);
        $db->exec('INSERT INTO password_resets (account_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
            [$id, hash('sha256', $token), date('Y-m-d H:i:s', $now + 7 * 86400), date('Y-m-d H:i:s', $now)]);
        if ($planId > 0) {
            Plans::assign($db, $id, $planId, $now);
        }
        $db->exec('DELETE FROM _meta WHERE meta_key = ?', ['demo_phone_' . $id]);
        $db->metaSet('delivered_' . $id, date('Y-m-d', $now));
        $acct = Auth::account($db, $id) ?? [];
        Notify::accountDelivered($cfg, $acct, $token);
        return null;
    }

    /** Testo del messaggio WhatsApp con cui si manda la demo al cliente. */
    public static function demoMessage(array $cfg, array $acct): string
    {
        return 'Ciao! Ecco come sarebbe la pagina di prenotazione online di ' . $acct['business_name'] . ': ' . Notify::shopUrl($cfg, $acct)
            . "\nProvala pure dal telefono: i tuoi clienti scelgono il servizio, il giorno e l'orario e tu ricevi la richiesta. Fammi sapere cosa ne pensi!";
    }

    /** Link wa.me per inviare la demo (al numero del cliente, se salvato). */
    public static function demoWhatsappUrl(Db $db, array $cfg, array $acct): string
    {
        $phone = Notify::digits((string)($db->metaGet('demo_phone_' . (int)$acct['id']) ?? ''));
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode(self::demoMessage($cfg, $acct));
    }

    public const ACTION_LABELS = [
        'approve' => 'Approvato', 'impersonate' => 'Entrato nel negozio', 'extend' => 'Prova prolungata', 'assign' => 'Piano assegnato',
        'activate' => 'Attivato fino a una data', 'suspend' => 'Sospeso / riattivato', 'reset' => 'Nuova password', 'delete' => 'Eliminato',
        'email' => 'Email di accesso cambiata', 'deliver' => 'Demo consegnata', 'duplicate' => 'Demo creata', 'showcase' => 'Esempi della home',
        'cat_save' => 'Categoria salvata', 'cat_delete' => 'Categoria eliminata', 'plan_save' => 'Piano salvato', 'plan_delete' => 'Piano eliminato', 'trial_days' => 'Giorni di prova cambiati',
        'backup' => 'Backup scaricato',
    ];

    /**
     * Backup completo del database in formato SQL, scritto a pezzi con $out (per non riempire la memoria).
     * Con MySQL si reimporta da phpMyAdmin → Importa.
     *
     * @param callable(string):void $out
     */
    public static function backup(Db $db, callable $out): void
    {
        $sqlite = $db->driver === 'sqlite';
        $tables = $sqlite
            ? array_column($db->all("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name')
            : array_map(static fn(array $r): string => (string)array_values($r)[0], $db->all('SHOW TABLES'));
        $q = static fn(string $n): string => $sqlite ? '"' . str_replace('"', '""', $n) . '"' : '`' . str_replace('`', '``', $n) . '`';
        $out('-- Backup ' . date('Y-m-d H:i:s') . "\n" . ($sqlite ? '' : "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n"));
        foreach ($tables as $t) {
            $t = (string)$t;
            if ($sqlite) {
                $create = (string)($db->row("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$t])['sql'] ?? '');
            } else {
                $r = $db->row('SHOW CREATE TABLE ' . $q($t));
                $create = (string)($r['Create Table'] ?? array_values($r ?? [])[1] ?? '');
            }
            $out("\nDROP TABLE IF EXISTS " . $q($t) . ";\n" . $create . ";\n");
            $batch = [];
            $cols = null;
            foreach ($db->iterate('SELECT * FROM ' . $q($t)) as $row) {
                $cols ??= implode(', ', array_map($q, array_keys($row)));
                $batch[] = '(' . implode(', ', array_map([$db, 'quote'], array_values($row))) . ')';
                if (count($batch) >= 100) {
                    $out('INSERT INTO ' . $q($t) . " ($cols) VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $out('INSERT INTO ' . $q($t) . " ($cols) VALUES\n" . implode(",\n", $batch) . ";\n");
            }
        }
        $out($sqlite ? '' : "\nSET FOREIGN_KEY_CHECKS = 1;\n");
    }

    /** Annota un'azione dell'amministratore nel registro. */
    public static function log(Db $db, string $adminEmail, string $action, int $targetId = 0, string $targetName = '', string $ip = ''): void
    {
        $db->exec('INSERT INTO admin_log (admin_email, action, target_id, target_name, ip, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [mb_substr($adminEmail, 0, 190), mb_substr($action, 0, 40), $targetId, mb_substr($targetName, 0, 255), mb_substr($ip, 0, 64), date('Y-m-d H:i:s')]);
    }

    /** Ultime azioni registrate. @return array<int,array<string,mixed>> */
    public static function logRows(Db $db, int $limit = 50): array
    {
        return $db->all('SELECT * FROM admin_log ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)));
    }

    /** Mostra o nasconde il negozio tra gli esempi della home. Restituisce il nuovo stato. */
    public static function toggleShowcase(Db $db, int $id): bool
    {
        if (self::isShowcase($db, $id)) {
            $db->exec('DELETE FROM _meta WHERE meta_key = ?', ['showcase_' . $id]);
            return false;
        }
        $db->metaSet('showcase_' . $id, '1');
        return true;
    }

    public static function isShowcase(Db $db, int $id): bool
    {
        return $db->metaGet('showcase_' . $id) !== null;
    }

    /**
     * Negozi da mostrare come esempio nella home (solo quelli con la pagina visibile).
     *
     * @return array<int,array{name:string,slug:string,category:string,cover:bool}>
     */
    public static function showcase(Db $db): array
    {
        $out = [];
        foreach ($db->all("SELECT meta_key FROM _meta WHERE meta_key LIKE 'showcase!_%' ESCAPE '!' ORDER BY meta_key") as $m) {
            $a = Auth::account($db, (int)substr((string)$m['meta_key'], 9));
            if ($a === null || (int)$a['approved'] !== 1 || (int)$a['suspended'] === 1) {
                continue;
            }
            $out[] = ['name' => (string)$a['business_name'], 'slug' => (string)$a['slug'], 'category' => (string)$a['category'], 'cover' => ($a['cover_mime'] ?? '') !== ''];
        }
        return $out;
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
