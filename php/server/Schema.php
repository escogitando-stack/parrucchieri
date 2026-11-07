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
 */
final class Schema
{
    public const VERSION = 3;

    /** Tabelle i cui dati appartengono a un negozio. */
    private const OWNED = ['clients', 'staff', 'services', 'appointments', 'blocked_slots', 'products'];

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
            "CREATE TABLE _meta (
                meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
                meta_value VARCHAR(255) NOT NULL
            )$tail",
            'CREATE UNIQUE INDEX uq_appointments_account_identifier ON appointments(account_id, identifier)',
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
        ];
        foreach ($statements as $sql) {
            $db->exec($sql);
        }
        $db->metaSet('schema_version', (string)self::VERSION);
    }

    /** Dal salone singolo ai negozi multipli. I dati esistenti restano con account_id = 0 fino alla prima registrazione. */
    private static function migrateTo2(Db $db): void
    {
        if (!$db->tableExists('accounts')) {
            $db->exec(self::accountsSql($db));
        }
        foreach (self::OWNED as $table) {
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
