<?php
declare(strict_types=1);

namespace App;

/**
 * Crea le tabelle alla prima richiesta e aggiorna i database delle versioni precedenti.
 *
 * Versioni dello schema (in _meta.schema_version):
 *   1 = app per un solo salone, senza account (installazioni precedenti)
 *   2 = più negozi: tabella accounts (con prova gratuita e abbonamento) e colonna account_id
 *   3 = pannello amministratore: sospensione e ultimo accesso degli account
 *   4 = prenotazioni online: orari di lavoro, indirizzo pubblico e richieste da approvare
 *   5 = approvazione dei nuovi negozi e profilo pubblico
 *   6 = piani di abbonamento gestiti dall'amministratore
 *   7 = conferma automatica, promemoria, email automatiche e recupero password
 *   8 = categorie merceologiche gestite dall'amministratore (tabella categories)
 *   9 = 15 nuove categorie (dentista, veterinario, gommista, idraulico, autoscuola...)
 */
final class Schema
{
    public const VERSION = 9;

    /** Tabelle i cui dati appartengono a un negozio. */
    private const OWNED = ['clients', 'staff', 'services', 'appointments', 'blocked_slots', 'products', 'working_hours'];

    public static function ensure(Db $db): void
    {
        if (!$db->tableExists('clients')) {
            self::install($db);
            return;
        }
        $version = (int)($db->metaGet('schema_version') ?? 1);
        if ($version < 2) {
            self::migrateTo2($db);
        }
        if ($version < 3) {
            self::migrateTo3($db);
        }
        if ($version < 4) {
            self::migrateTo4($db);
        }
        if ($version < 5) {
            self::migrateTo5($db);
        }
        if ($version < 6) {
            self::migrateTo6($db);
        }
        if ($version < 7) {
            self::migrateTo7($db);
        }
        if ($version < 8) {
            self::migrateTo8($db);
        }
        if ($version < 9) {
            Categories::addMissing($db, Categories::ADDED_V9);
            $db->metaSet('schema_version', (string)self::VERSION);
        }
    }

    /** @return array{0:string,1:string,2:string,3:string,4:string} */
    private static function types(Db $db): array
    {
        $sqlite = $db->driver === 'sqlite';
        return [
            $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            $sqlite ? 'INTEGER' : 'INT',
            $sqlite ? 'TEXT DEFAULT CURRENT_TIMESTAMP' : 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            '',
        ];
    }

    private static function accountsSql(Db $db): string
    {
        [$pk, $int, $ts, $tail] = self::types($db);
        return "CREATE TABLE accounts (
                id $pk,
                email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                business_name VARCHAR(255) NOT NULL,
                category VARCHAR(32) NOT NULL DEFAULT 'altro',
                appointment_counter $int NOT NULL DEFAULT 0,
                plan_status VARCHAR(16) NOT NULL DEFAULT 'trial',
                trial_ends_at VARCHAR(19) NOT NULL DEFAULT '',
                paid_until VARCHAR(19) NOT NULL DEFAULT '',
                stripe_customer_id VARCHAR(64) NOT NULL DEFAULT '',
                stripe_subscription_id VARCHAR(64) NOT NULL DEFAULT '',
                suspended $int NOT NULL DEFAULT 0,
                last_login_at VARCHAR(19) NOT NULL DEFAULT '',
                slug VARCHAR(80) NOT NULL DEFAULT '',
                booking_enabled $int NOT NULL DEFAULT 1,
                slot_minutes $int NOT NULL DEFAULT 30,
                booking_notice_hours $int NOT NULL DEFAULT 2,
                booking_horizon_days $int NOT NULL DEFAULT 60,
                shop_phone VARCHAR(64) NOT NULL DEFAULT '',
                shop_address VARCHAR(255) NOT NULL DEFAULT '',
                approved $int NOT NULL DEFAULT 1,
                shop_email VARCHAR(190) NOT NULL DEFAULT '',
                shop_whatsapp VARCHAR(64) NOT NULL DEFAULT '',
                shop_description VARCHAR(1000) NOT NULL DEFAULT '',
                shop_hours VARCHAR(500) NOT NULL DEFAULT '',
                logo_mime VARCHAR(32) NOT NULL DEFAULT '',
                logo_data MEDIUMTEXT NULL,
                trial_mail_stage $int NOT NULL DEFAULT 0,
                onboard_mail $int NOT NULL DEFAULT 0,
                created_at $ts
            )$tail";
    }

    public static function install(Db $db): void
    {
        [$pk, $int, $ts, $tail] = self::types($db);

        $statements = [
            self::accountsSql($db),
            "CREATE TABLE clients (
                id $pk,
                account_id $int NOT NULL DEFAULT 0,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL DEFAULT '',
                phone VARCHAR(64) NOT NULL DEFAULT '',
                notes TEXT NOT NULL,
                created_at $ts,
                updated_at $ts
            )$tail",
            "CREATE TABLE staff (
                id $pk,
                account_id $int NOT NULL DEFAULT 0,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL DEFAULT '',
                phone VARCHAR(64) NOT NULL DEFAULT '',
                title VARCHAR(255) NOT NULL DEFAULT '',
                color VARCHAR(16) NOT NULL DEFAULT '#7c3aed',
                active $int NOT NULL DEFAULT 1,
                created_at $ts
            )$tail",
            "CREATE TABLE services (
                id $pk,
                account_id $int NOT NULL DEFAULT 0,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                duration $int NOT NULL DEFAULT 60,
                price DOUBLE NOT NULL DEFAULT 0,
                color VARCHAR(16) NOT NULL DEFAULT '#6b7280',
                category VARCHAR(255) NOT NULL DEFAULT '',
                active $int NOT NULL DEFAULT 1,
                online $int NOT NULL DEFAULT 1,
                auto_confirm $int NOT NULL DEFAULT 0,
                created_at $ts
            )$tail",
            "CREATE TABLE appointments (
                id $pk,
                account_id $int NOT NULL DEFAULT 0,
                identifier VARCHAR(32) NOT NULL,
                client_id $int NOT NULL,
                staff_id $int NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'booked',
                scheduled_date VARCHAR(10) NOT NULL,
                start_time VARCHAR(5) NOT NULL DEFAULT '09:00',
                end_time VARCHAR(5) NOT NULL DEFAULT '10:00',
                total_price DOUBLE NOT NULL DEFAULT 0,
                notes TEXT NOT NULL,
                is_recurring $int NOT NULL DEFAULT 0,
                recurrence_interval VARCHAR(64) NOT NULL DEFAULT '',
                source VARCHAR(16) NOT NULL DEFAULT 'owner',
                public_token VARCHAR(48) NOT NULL DEFAULT '',
                ip_hash VARCHAR(64) NOT NULL DEFAULT '',
                reminded $int NOT NULL DEFAULT 0,
                consent_at VARCHAR(19) NOT NULL DEFAULT '',
                created_at $ts,
                updated_at $ts,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
            )$tail",
            "CREATE TABLE appointment_services (
                id $pk,
                appointment_id $int NOT NULL,
                service_id $int NOT NULL,
                price DOUBLE NOT NULL DEFAULT 0,
                duration $int NOT NULL DEFAULT 60,
                FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
                FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
            )$tail",
            "CREATE TABLE appointment_notes (
                id $pk,
                appointment_id $int NOT NULL,
                content TEXT NOT NULL,
                created_at $ts,
                FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
            )$tail",
            "CREATE TABLE blocked_slots (
                id $pk,
                account_id $int NOT NULL DEFAULT 0,
                staff_id $int NOT NULL,
                blocked_date VARCHAR(10) NOT NULL,
                start_time VARCHAR(5) NOT NULL,
                end_time VARCHAR(5) NOT NULL,
                reason VARCHAR(255) NOT NULL DEFAULT '',
                created_at $ts,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
            )$tail",
            "CREATE TABLE products (
                id $pk,
                account_id $int NOT NULL DEFAULT 0,
                name VARCHAR(255) NOT NULL,
                brand VARCHAR(255) NOT NULL DEFAULT '',
                category VARCHAR(255) NOT NULL DEFAULT '',
                sku VARCHAR(128) NOT NULL DEFAULT '',
                price DOUBLE NOT NULL DEFAULT 0,
                cost DOUBLE NOT NULL DEFAULT 0,
                stock $int NOT NULL DEFAULT 0,
                low_stock_alert $int NOT NULL DEFAULT 5,
                created_at $ts,
                updated_at $ts
            )$tail",
            "CREATE TABLE working_hours (
                id $pk,
                account_id $int NOT NULL DEFAULT 0,
                staff_id $int NOT NULL,
                weekday $int NOT NULL,
                start_time VARCHAR(5) NOT NULL,
                end_time VARCHAR(5) NOT NULL,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
            )$tail",
            self::plansSql($db),
            self::resetsSql($db),
            self::categoriesSql($db),
            "CREATE TABLE _meta (
                meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
                meta_value VARCHAR(255) NOT NULL
            )$tail",
            'CREATE UNIQUE INDEX uq_appointments_account_identifier ON appointments(account_id, identifier)',
            'CREATE UNIQUE INDEX uq_accounts_slug ON accounts(slug)',
            'CREATE INDEX idx_appointments_token ON appointments(public_token)',
            'CREATE INDEX idx_working_hours_staff ON working_hours(staff_id, weekday)',
            'CREATE INDEX idx_appointments_client ON appointments(client_id)',
            'CREATE INDEX idx_appointments_staff ON appointments(staff_id)',
            'CREATE INDEX idx_appointments_date ON appointments(account_id, scheduled_date)',
            'CREATE INDEX idx_appointments_status ON appointments(status)',
            'CREATE INDEX idx_appointment_services_apt ON appointment_services(appointment_id)',
            'CREATE INDEX idx_appointment_notes_apt ON appointment_notes(appointment_id)',
            'CREATE INDEX idx_blocked_slots_staff ON blocked_slots(staff_id)',
            'CREATE INDEX idx_blocked_slots_date ON blocked_slots(account_id, blocked_date)',
            'CREATE INDEX idx_clients_account ON clients(account_id, name)',
            'CREATE INDEX idx_staff_account ON staff(account_id)',
            'CREATE INDEX idx_services_account ON services(account_id)',
            'CREATE INDEX idx_products_account ON products(account_id)',
            'CREATE INDEX idx_password_resets_hash ON password_resets(token_hash)',
        ];
        foreach ($statements as $sql) {
            $db->exec($sql);
        }
        Categories::seed($db);
        $db->metaSet('schema_version', (string)self::VERSION);
    }

    /** Dal salone singolo ai negozi multipli. I dati esistenti restano con account_id = 0 fino alla prima registrazione. */
    private static function migrateTo2(Db $db): void
    {
        if (!$db->tableExists('accounts')) {
            $db->exec(self::accountsSql($db));
        }
        foreach (self::OWNED as $table) {
            if (!$db->tableExists($table)) {
                continue;
            }
            if (!$db->columnExists($table, 'account_id')) {
                $db->exec("ALTER TABLE $table ADD COLUMN account_id " . ($db->driver === 'sqlite' ? 'INTEGER' : 'INT') . ' NOT NULL DEFAULT 0');
                $db->exec("CREATE INDEX idx_{$table}_account ON $table(account_id)");
            }
        }
        if ($db->driver !== 'sqlite') {
            // L'identificativo (APP-1...) deve essere unico per negozio, non in assoluto.
            foreach ($db->all("SHOW INDEX FROM appointments WHERE Column_name = 'identifier' AND Non_unique = 0") as $idx) {
                $db->exec('ALTER TABLE appointments DROP INDEX `' . str_replace('`', '', (string)$idx['Key_name']) . '`');
            }
            $db->exec('CREATE UNIQUE INDEX uq_appointments_account_identifier ON appointments(account_id, identifier)');
        }
        $db->metaSet('schema_version', '2');
    }

    /** Sospensione degli account e ultimo accesso (usati dal pannello amministratore). */
    private static function migrateTo3(Db $db): void
    {
        $int = $db->driver === 'sqlite' ? 'INTEGER' : 'INT';
        if (!$db->columnExists('accounts', 'suspended')) {
            $db->exec("ALTER TABLE accounts ADD COLUMN suspended $int NOT NULL DEFAULT 0");
        }
        if (!$db->columnExists('accounts', 'last_login_at')) {
            $db->exec("ALTER TABLE accounts ADD COLUMN last_login_at VARCHAR(19) NOT NULL DEFAULT ''");
        }
        $db->metaSet('schema_version', (string)self::VERSION);
    }

    /** Prenotazioni online: orari di lavoro, indirizzo pubblico (slug) e richieste da approvare. */
    private static function migrateTo4(Db $db): void
    {
        $int = $db->driver === 'sqlite' ? 'INTEGER' : 'INT';
        $cols = [
            'accounts' => [
                'slug' => "VARCHAR(80) NOT NULL DEFAULT ''",
                'booking_enabled' => "$int NOT NULL DEFAULT 1",
                'slot_minutes' => "$int NOT NULL DEFAULT 30",
                'booking_notice_hours' => "$int NOT NULL DEFAULT 2",
                'booking_horizon_days' => "$int NOT NULL DEFAULT 60",
                'shop_phone' => "VARCHAR(64) NOT NULL DEFAULT ''",
                'shop_address' => "VARCHAR(255) NOT NULL DEFAULT ''",
            ],
            'services' => ['online' => "$int NOT NULL DEFAULT 1"],
            'appointments' => [
                'source' => "VARCHAR(16) NOT NULL DEFAULT 'owner'",
                'public_token' => "VARCHAR(48) NOT NULL DEFAULT ''",
                'ip_hash' => "VARCHAR(64) NOT NULL DEFAULT ''",
            ],
        ];
        foreach ($cols as $table => $defs) {
            foreach ($defs as $col => $def) {
                if (!$db->columnExists($table, $col)) {
                    $db->exec("ALTER TABLE $table ADD COLUMN $col $def");
                }
            }
        }
        if (!$db->tableExists('working_hours')) {
            [$pk, $int2, , $tail] = self::types($db);
            $db->exec("CREATE TABLE working_hours (
                id $pk,
                account_id $int2 NOT NULL DEFAULT 0,
                staff_id $int2 NOT NULL,
                weekday $int2 NOT NULL,
                start_time VARCHAR(5) NOT NULL,
                end_time VARCHAR(5) NOT NULL,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
            )$tail");
            $db->exec('CREATE INDEX idx_working_hours_staff ON working_hours(staff_id, weekday)');
        }
        // Un indirizzo pubblico per ogni negozio già registrato, poi l'indice che lo rende unico.
        foreach ($db->all("SELECT id, business_name FROM accounts WHERE slug = ''") as $a) {
            $db->exec('UPDATE accounts SET slug = ? WHERE id = ?', [Booking::makeSlug($db, (string)$a['business_name']), (int)$a['id']]);
        }
        if (!self::indexExists($db, 'accounts', 'uq_accounts_slug')) {
            $db->exec('CREATE UNIQUE INDEX uq_accounts_slug ON accounts(slug)');
        }
        if (!self::indexExists($db, 'appointments', 'idx_appointments_token')) {
            $db->exec('CREATE INDEX idx_appointments_token ON appointments(public_token)');
        }
        // Orari di partenza per i collaboratori già presenti (il titolare li modifica dalle Impostazioni).
        foreach ($db->all('SELECT id, account_id FROM staff WHERE account_id > 0') as $st) {
            if ($db->row('SELECT id FROM working_hours WHERE staff_id = ? LIMIT 1', [(int)$st['id']]) === null) {
                self::defaultHours($db, (int)$st['account_id'], (int)$st['id']);
            }
        }
        $db->metaSet('schema_version', (string)self::VERSION);
    }

    /** Approvazione dei nuovi negozi e profilo pubblico (contatti, descrizione, orari, logo). */
    private static function migrateTo5(Db $db): void
    {
        $int = $db->driver === 'sqlite' ? 'INTEGER' : 'INT';
        $cols = [
            'approved' => "$int NOT NULL DEFAULT 1",
            'shop_email' => "VARCHAR(190) NOT NULL DEFAULT ''",
            'shop_whatsapp' => "VARCHAR(64) NOT NULL DEFAULT ''",
            'shop_description' => "VARCHAR(1000) NOT NULL DEFAULT ''",
            'shop_hours' => "VARCHAR(500) NOT NULL DEFAULT ''",
            'logo_mime' => "VARCHAR(32) NOT NULL DEFAULT ''",
            'logo_data' => 'MEDIUMTEXT NULL',
        ];
        foreach ($cols as $col => $def) {
            if (!$db->columnExists('accounts', $col)) {
                $db->exec("ALTER TABLE accounts ADD COLUMN $col $def");
            }
        }
        // I negozi già presenti restano approvati (default 1).
        $db->metaSet('schema_version', (string)self::VERSION);
    }

    private static function plansSql(Db $db): string
    {
        [$pk, $int, , $tail] = self::types($db);
        return "CREATE TABLE plans (
                id $pk,
                name VARCHAR(60) NOT NULL,
                duration_days $int NOT NULL DEFAULT 30,
                price DOUBLE NOT NULL DEFAULT 0,
                stripe_price_id VARCHAR(64) NOT NULL DEFAULT '',
                active $int NOT NULL DEFAULT 1,
                sort_order $int NOT NULL DEFAULT 0
            )$tail";
    }

    /** Piani di abbonamento modificabili dall'amministratore. */
    private static function migrateTo6(Db $db): void
    {
        if (!$db->tableExists('plans')) {
            $db->exec(self::plansSql($db));
        }
        $db->metaSet('schema_version', (string)self::VERSION);
    }

    private static function resetsSql(Db $db): string
    {
        [$pk, $int, , $tail] = self::types($db);
        return "CREATE TABLE password_resets (
                id $pk,
                account_id $int NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at VARCHAR(19) NOT NULL,
                created_at VARCHAR(19) NOT NULL DEFAULT ''
            )$tail";
    }

    /** Conferma automatica dei servizi, promemoria, email automatiche, recupero password. */
    private static function migrateTo7(Db $db): void
    {
        $int = $db->driver === 'sqlite' ? 'INTEGER' : 'INT';
        $cols = [
            'services' => ['auto_confirm' => "$int NOT NULL DEFAULT 0"],
            'appointments' => ['reminded' => "$int NOT NULL DEFAULT 0", 'consent_at' => "VARCHAR(19) NOT NULL DEFAULT ''"],
            'accounts' => ['trial_mail_stage' => "$int NOT NULL DEFAULT 0", 'onboard_mail' => "$int NOT NULL DEFAULT 0"],
        ];
        foreach ($cols as $table => $defs) {
            foreach ($defs as $col => $def) {
                if (!$db->columnExists($table, $col)) {
                    $db->exec("ALTER TABLE $table ADD COLUMN $col $def");
                }
            }
        }
        if (!$db->tableExists('password_resets')) {
            $db->exec(self::resetsSql($db));
            $db->exec('CREATE INDEX idx_password_resets_hash ON password_resets(token_hash)');
        }
        $db->metaSet('schema_version', (string)self::VERSION);
    }

    private static function categoriesSql(Db $db): string
    {
        [$pk, $int, , $tail] = self::types($db);
        return "CREATE TABLE categories (
                id $pk,
                cat_key VARCHAR(32) NOT NULL UNIQUE,
                label VARCHAR(80) NOT NULL,
                staff_title VARCHAR(80) NOT NULL DEFAULT '',
                staff_hint VARCHAR(120) NOT NULL DEFAULT '',
                terms TEXT NOT NULL,
                services MEDIUMTEXT NOT NULL,
                active $int NOT NULL DEFAULT 1,
                sort_order $int NOT NULL DEFAULT 0
            )$tail";
    }

    /** Categorie merceologiche nel database, partendo da quelle predefinite. */
    private static function migrateTo8(Db $db): void
    {
        if (!$db->tableExists('categories')) {
            $db->exec(self::categoriesSql($db));
        }
        Categories::seed($db);
        $db->metaSet('schema_version', (string)self::VERSION);
    }

    private static function indexExists(Db $db, string $table, string $index): bool
    {
        if ($db->driver === 'sqlite') {
            return $db->row("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$index]) !== null;
        }
        return $db->row(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        ) !== null;
    }

    /** Dal lunedì al venerdì, mattina e pomeriggio: un punto di partenza da adattare. */
    public static function defaultHours(Db $db, int $accountId, int $staffId): void
    {
        foreach ([1, 2, 3, 4, 5] as $day) {
            foreach ([['09:00', '13:00'], ['15:00', '19:00']] as [$from, $to]) {
                $db->exec(
                    'INSERT INTO working_hours (account_id, staff_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?, ?)',
                    [$accountId, $staffId, $day, $from, $to]
                );
            }
        }
    }

    /** Servizi di partenza e primo collaboratore, secondo il tipo di attività. */
    public static function seedAccount(Db $db, int $accountId, string $category): void
    {
        $preset = Categories::get($category);
        foreach ($preset['services'] as [$name, $desc, $dur, $price, $color, $cat]) {
            $db->exec(
                'INSERT INTO services (account_id, name, description, duration, price, color, category) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$accountId, $name, $desc, $dur, $price, $color, $cat]
            );
        }
        $db->exec(
            'INSERT INTO staff (account_id, name, title, color) VALUES (?, ?, ?, ?)',
            [$accountId, 'Titolare', $preset['staff_title'], '#3b82f6']
        );
        self::defaultHours($db, $accountId, $db->lastId());
    }

    /** Ci sono dati della vecchia versione (senza proprietario) da assegnare al primo negozio? */
    public static function hasUnownedData(Db $db): bool
    {
        foreach (self::OWNED as $table) {
            if ($db->row("SELECT id FROM $table WHERE account_id = 0 LIMIT 1") !== null) {
                return true;
            }
        }
        return false;
    }

    /** Assegna al negozio i dati rimasti senza proprietario e porta avanti il suo contatore. */
    public static function adoptUnownedData(Db $db, int $accountId): void
    {
        foreach (self::OWNED as $table) {
            $db->exec("UPDATE $table SET account_id = ? WHERE account_id = 0", [$accountId]);
        }
        foreach ($db->all('SELECT id FROM staff WHERE account_id = ?', [$accountId]) as $st) {
            if ($db->row('SELECT id FROM working_hours WHERE staff_id = ? LIMIT 1', [(int)$st['id']]) === null) {
                self::defaultHours($db, $accountId, (int)$st['id']);
            }
        }
        $legacy = (int)($db->metaGet('appointment_counter') ?? 0);
        $max = 0;
        foreach ($db->all('SELECT identifier FROM appointments WHERE account_id = ?', [$accountId]) as $r) {
            if (preg_match('/-(\d+)$/', (string)$r['identifier'], $m)) {
                $max = max($max, (int)$m[1]);
            }
        }
        $db->exec('UPDATE accounts SET appointment_counter = ? WHERE id = ?', [max($legacy, $max), $accountId]);
    }
}
