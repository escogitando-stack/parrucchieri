<?php
declare(strict_types=1);

namespace App;

/**
 * Demo pubblica su /esempio: il pannello vero del titolare, con l'API vera,
 * ma su un piccolo database SQLite usa e getta per ogni visitatore.
 * Niente email, niente pagamenti, nessun contatto con il database dei negozi.
 */
final class Demo
{
    public const BASE = '/esempio';
    /** Dopo quanto tempo senza uso una demo viene cancellata. */
    private const TTL = 3 * 3600;
    /** Quante demo possono esistere insieme (oltre si cancellano le più vecchie). */
    private const MAX = 200;
    private const SESSION_KEY = 'demo_token';

    public static function dir(): string
    {
        return __DIR__ . '/demo';
    }

    public static function available(): bool
    {
        return extension_loaded('pdo_sqlite');
    }

    /** Impostazioni per la demo: nessuna email parte davvero, niente Stripe, nessun amministratore. */
    public static function cfg(array $cfg): array
    {
        $cfg['mail'] = ['driver' => 'array', 'from' => 'demo@example.com'];
        $cfg['stripe'] = [];
        $cfg['admin_email'] = '';
        $cfg['admin_emails'] = [];
        $cfg['db'] = ['driver' => 'sqlite', 'path' => ':memory:'];
        return $cfg;
    }

    /** Indirizzo della pagina di prenotazione del negozio demo. */
    public static function shopUrl(array $cfg): string
    {
        $base = !empty($cfg['base_url']) ? rtrim((string)$cfg['base_url'], '/') : ((Auth::isHttps() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return $base . self::BASE . '/negozio';
    }

    /** La pagina dell'app con la modalità demo accesa: le chiamate vanno a /esempio/api. */
    public static function appHtml(string $html): string
    {
        $inject = '<meta name="robots" content="noindex, nofollow"><script>window.A4M_DEMO={base:"' . self::BASE . '"}</script>';
        return str_contains($html, '</head>') ? str_replace('</head>', $inject . '</head>', $html) : $inject . $html;
    }

    private static function file(string $token): string
    {
        return self::dir() . '/' . $token . '.sqlite';
    }

    /**
     * La demo del visitatore, se esiste ancora: [database, id del negozio demo].
     *
     * @return array{0:Db,1:int}|null
     */
    public static function open(): ?array
    {
        Auth::start();
        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $file = self::file($token);
        if (!is_file($file) || filemtime($file) < time() - self::TTL) {
            return null;
        }
        @touch($file);
        $db = Db::connect(['driver' => 'sqlite', 'path' => $file]);
        Categories::boot($db);
        $row = $db->row('SELECT id FROM accounts ORDER BY id LIMIT 1');
        return $row === null ? null : [$db, (int)$row['id']];
    }

    /**
     * Apre la demo del visitatore o ne crea una nuova.
     *
     * @return array{0:Db,1:int}
     */
    public static function openOrCreate(array $cfg): array
    {
        $open = self::open();
        if ($open !== null) {
            return $open;
        }
        if (!is_dir(self::dir())) {
            @mkdir(self::dir(), 0750, true);
            @file_put_contents(self::dir() . '/.htaccess', "Require all denied\n");
        }
        self::cleanup();
        $token = bin2hex(random_bytes(16));
        $db = connect(['db' => ['driver' => 'sqlite', 'path' => self::file($token)]]);
        $id = self::seed($db, self::cfg($cfg));
        Auth::start();
        $_SESSION[self::SESSION_KEY] = $token;
        return [$db, $id];
    }

    /** Cancella le demo scadute e, se sono troppe, le più vecchie. */
    public static function cleanup(?int $now = null): void
    {
        $now ??= time();
        $files = glob(self::dir() . '/*.sqlite') ?: [];
        $keep = [];
        foreach ($files as $f) {
            $t = (int)@filemtime($f);
            if ($t < $now - self::TTL) {
                @unlink($f);
            } else {
                $keep[$f] = $t;
            }
        }
        if (count($keep) >= self::MAX) {
            asort($keep);
            foreach (array_slice(array_keys($keep), 0, count($keep) - self::MAX + 1) as $f) {
                @unlink($f);
            }
        }
    }

    /** Un salone con collaboratori, clienti, agenda di due settimane e richieste da approvare. */
    public static function seed(Db $db, array $cfg, ?int $now = null): int
    {
        $now ??= time();
        $db->exec(
            "INSERT INTO accounts (email, password_hash, business_name, category, plan_status, approved, slug, trial_ends_at, booking_enabled, shop_phone, shop_address, shop_description, shop_hours)
             VALUES (?, ?, ?, 'parrucchiere', 'trial', 1, 'salone-esempio', ?, 1, ?, ?, ?, ?)",
            [
                'demo@example.com', password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'Salone Esempio',
                date('Y-m-d H:i:s', $now + 30 * 86400), '0565 000000', 'Via Roma 1, Piombino',
                'Taglio, colore e piega su appuntamento. Prenota online in un minuto.', "Lun-Sab 9-13 e 15-19",
            ]
        );
        $acct = $db->lastId();
        Schema::seedAccount($db, $acct, 'parrucchiere');
        $api = new Api($db, $acct, $cfg);
        $call = static function (string $method, string $path, array $body = []) use ($api): array {
            [$status, $data] = $api->handle($method, $path, [], $body);
            if ($status >= 400) {
                throw new \RuntimeException("Demo: $method $path → $status " . json_encode($data));
            }
            return $data;
        };

        // Collaboratori: il "Titolare" creato dal preset diventa Giulia, poi Marco e Sara.
        $owner = (int)$db->row('SELECT id FROM staff WHERE account_id = ? ORDER BY id LIMIT 1', [$acct])['id'];
        $db->exec("UPDATE staff SET name = 'Giulia', title = 'Titolare', color = '#ec4899' WHERE id = ?", [$owner]);
        $staff = [$owner];
        foreach ([['Marco', 'Parrucchiere', '#3b82f6'], ['Sara', 'Colorista', '#f59e0b']] as [$n, $t, $c]) {
            $staff[] = (int)$call('POST', '/api/staff', ['name' => $n, 'title' => $t, 'color' => $c])['staff']['id'];
        }
        $week = [];
        foreach ([1, 2, 3, 4, 5, 6] as $d) {
            $week[] = ['weekday' => $d, 'start_time' => '09:00', 'end_time' => '13:00'];
            $week[] = ['weekday' => $d, 'start_time' => '15:00', 'end_time' => '19:00'];
        }
        foreach ($staff as $sid) {
            $call('PUT', "/api/working-hours/$sid", ['hours' => $week]);
        }

        $services = [];
        foreach ($db->all('SELECT id, name FROM services WHERE account_id = ?', [$acct]) as $s) {
            $services[(string)$s['name']] = (int)$s['id'];
        }
        $svc = static fn(string ...$names): array => array_values(array_filter(array_map(static fn(string $n): ?int => $services[$n] ?? null, $names)));

        $people = [
            ['Anna Bianchi', '3331234501', 'Preferisce la piega mossa.'], ['Luca Ferri', '3331234502', ''],
            ['Chiara Conti', '3331234503', 'Allergica all\'ammoniaca: usare colore senza.'], ['Paolo Greco', '3331234504', ''],
            ['Elena Marino', '3331234505', 'Viene sempre il sabato mattina.'], ['Francesca Rizzo', '3331234506', ''],
            ['Davide Costa', '3331234507', ''], ['Martina Gallo', '3331234508', 'Sposa a giugno: prova acconciatura.'],
            ['Giorgio Lombardi', '3331234509', ''], ['Sofia Moretti', '3331234510', ''],
            ['Alessia Fontana', '3331234511', ''], ['Matteo Barbieri', '3331234512', ''],
            ['Valentina Romano', '3331234513', 'Colore 7.3, ritocco ogni 5 settimane.'], ['Simone Ricci', '3331234514', ''],
            ['Laura De Luca', '3331234515', ''], ['Roberta Esposito', '3331234516', ''],
        ];
        $clients = [];
        foreach ($people as [$name, $phone, $note]) {
            $email = strtolower(str_replace([' ', '\''], ['.', ''], $name)) . '@example.com';
            $clients[] = (int)$call('POST', '/api/clients', ['name' => $name, 'phone' => '+39 ' . $phone, 'email' => $email, 'notes' => $note])['client']['id'];
        }

        // Agenda: schema fisso per giorno, ripetuto da 14 giorni fa a 10 giorni avanti (lunedì-sabato,
        // più oggi anche se è domenica, così chi apre la demo trova sempre la giornata piena).
        $pattern = [
            [0, '09:00', ['Taglio donna']], [0, '10:00', ['Colore', 'Piega']], [0, '15:00', ['Taglio donna']], [0, '17:00', ['Piega']],
            [1, '09:30', ['Taglio uomo', 'Barba']], [1, '11:00', ['Taglio uomo']], [1, '15:30', ['Taglio uomo']], [1, '16:30', ['Taglio donna']],
            [2, '09:00', ['Colpi di sole / Balayage']], [2, '15:00', ['Colore']], [2, '17:00', ['Trattamento ristrutturante']],
        ];
        $today = date('Y-m-d', $now);
        $ci = 0;
        for ($off = -14; $off <= 10; $off++) {
            $ts = $now + $off * 86400;
            $dow = (int)date('N', $ts);
            if ($dow === 7 && $off !== 0) {
                continue;
            }
            $date = date('Y-m-d', $ts);
            foreach ($pattern as $k => [$si, $time, $names]) {
                // Qualche buco qua e là, diverso ogni giorno, per un'agenda credibile.
                if ((($off + 20) * 7 + $k * 3) % 5 === 0) {
                    continue;
                }
                $client = $clients[$ci++ % count($clients)];
                $apt = $call('POST', '/api/appointments', [
                    'client_id' => $client, 'staff_id' => $staff[$si], 'scheduled_date' => $date,
                    'start_time' => $time, 'service_ids' => $svc(...$names), 'allow_conflict' => true,
                ])['appointment'];
                $status = 'confirmed';
                if ($date < $today) {
                    $r = ($off * 13 + $k) % 17;
                    $status = $r === 0 ? 'no_show' : ($r === 5 ? 'cancelled' : 'completed');
                } elseif ($date === $today) {
                    $status = $time < date('H:i', $now) ? 'completed' : 'confirmed';
                } elseif ($off > 3 && $k % 4 === 1) {
                    $status = 'booked';
                }
                $source = ($k % 3 === 0) ? 'online' : 'owner';
                $db->exec('UPDATE appointments SET status = ?, source = ? WHERE id = ?', [$status, $source, (int)$apt['id']]);
            }
        }

        // Richieste online da approvare.
        foreach ([[1, '12:00', ['Taglio donna'], 'Se possibile con Giulia, grazie!'], [2, '18:00', ['Taglio uomo'], ''], [3, '11:30', ['Piega'], 'Ho un matrimonio il giorno dopo.']] as $i => [$days, $time, $names, $note]) {
            $ts = $now + $days * 86400;
            if ((int)date('N', $ts) === 7) {
                $ts += 86400;
            }
            $apt = $call('POST', '/api/appointments', [
                'client_id' => $clients[(10 + $i) % count($clients)], 'staff_id' => null, 'scheduled_date' => date('Y-m-d', $ts),
                'start_time' => $time, 'service_ids' => $svc(...$names), 'notes' => $note,
            ])['appointment'];
            $db->exec("UPDATE appointments SET status = 'requested', source = 'online' WHERE id = ?", [(int)$apt['id']]);
        }

        // Un paio di note sugli appuntamenti di oggi e una pausa bloccata.
        foreach ($db->all("SELECT id FROM appointments WHERE account_id = ? AND scheduled_date = ? ORDER BY start_time LIMIT 2", [$acct, $today]) as $i => $r) {
            $call('POST', '/api/appointments/' . (int)$r['id'] . '/notes', ['content' => $i === 0 ? 'Vuole provare una frangia leggera.' : 'Portare la foto del colore che le piace.']);
        }
        $call('POST', '/api/blocked-slots', ['staff_id' => $staff[1], 'blocked_date' => date('Y-m-d', $now + 86400 * ((int)date('N', $now) >= 6 ? 3 : 1)), 'start_time' => '13:00', 'end_time' => '14:00', 'reason' => 'Corso di aggiornamento']);

        foreach ([
            ['Shampoo idratante 250 ml', 'Linea Pro', 'Shampoo', 'SH-250', 14.5, 6.2, 12, 4],
            ['Maschera ristrutturante', 'Linea Pro', 'Trattamenti', 'MA-200', 22, 9.5, 3, 4],
            ['Olio di argan', 'Natura', 'Trattamenti', 'OL-100', 18, 7, 8, 3],
            ['Lacca tenuta forte', 'Style', 'Styling', 'LA-300', 11, 4.5, 15, 5],
            ['Cera opaca uomo', 'Style', 'Styling', 'CE-075', 13, 5, 2, 3],
            ['Spazzola termica', 'Tools', 'Accessori', 'SP-T32', 25, 11, 5, 2],
        ] as [$name, $brand, $cat, $sku, $price, $cost, $stock, $alert]) {
            $call('POST', '/api/products', ['name' => $name, 'brand' => $brand, 'category' => $cat, 'sku' => $sku, 'price' => $price, 'cost' => $cost, 'stock' => $stock, 'low_stock_alert' => $alert]);
        }
        return $acct;
    }
}
