<?php
declare(strict_types=1);

namespace App;

/** Crea le tabelle alla prima richiesta e inserisce i dati iniziali. */
final class Schema
{
    public static function install(Db $db, bool $seedDemo = false): void
    {
        if ($db->tableExists('clients')) {
            return;
        }
        $sqlite = $db->driver === 'sqlite';
        $pk = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'INT';
        $ts = $sqlite ? 'TEXT DEFAULT CURRENT_TIMESTAMP' : 'DATETIME DEFAULT CURRENT_TIMESTAMP';
        $tail = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $statements = [
            "CREATE TABLE clients (
                id $pk,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL DEFAULT '',
                phone VARCHAR(64) NOT NULL DEFAULT '',
                notes TEXT NOT NULL,
                created_at $ts,
                updated_at $ts
            )$tail",
            "CREATE TABLE staff (
                id $pk,
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
                identifier VARCHAR(32) NOT NULL UNIQUE,
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
            'CREATE INDEX idx_appointments_client ON appointments(client_id)',
            'CREATE INDEX idx_appointments_staff ON appointments(staff_id)',
            'CREATE INDEX idx_appointments_date ON appointments(scheduled_date)',
            'CREATE INDEX idx_appointments_status ON appointments(status)',
            'CREATE INDEX idx_appointment_services_apt ON appointment_services(appointment_id)',
            'CREATE INDEX idx_appointment_notes_apt ON appointment_notes(appointment_id)',
            'CREATE INDEX idx_blocked_slots_staff ON blocked_slots(staff_id)',
            'CREATE INDEX idx_blocked_slots_date ON blocked_slots(blocked_date)',
            'CREATE INDEX idx_clients_name ON clients(name)',
        ];
        foreach ($statements as $sql) {
            $db->exec($sql);
        }

        $db->exec("INSERT INTO _meta (meta_key, meta_value) VALUES ('appointment_counter', '0')");
        $db->exec("INSERT INTO _meta (meta_key, meta_value) VALUES ('appointment_prefix', 'APP')");
        self::seed($db, $seedDemo);
    }

    private static function seed(Db $db, bool $demo): void
    {
        // Servizi tipici di un salone: si modificano dall'app.
        $services = [
            ['Taglio donna', 'Shampoo, taglio e piega', 60, 45, '#ec4899', 'Taglio'],
            ['Taglio uomo', 'Taglio con rifinitura', 30, 20, '#3b82f6', 'Taglio'],
            ['Piega', 'Shampoo e piega', 45, 25, '#10b981', 'Styling'],
            ['Colore', 'Colorazione completa', 90, 60, '#f59e0b', 'Colore'],
            ['Colpi di sole / Balayage', 'Schiariture con tecnica a mano libera', 150, 120, '#8b5cf6', 'Colore'],
            ['Trattamento ristrutturante', 'Maschera e trattamento per capelli sfibrati', 30, 25, '#14b8a6', 'Trattamento'],
            ['Barba', 'Rifinitura barba con panno caldo', 20, 12, '#6b7280', 'Taglio'],
            ['Acconciatura sposa', 'Prova e acconciatura il giorno del matrimonio', 120, 150, '#f43f5e', 'Styling'],
        ];
        foreach ($services as [$name, $desc, $dur, $price, $color, $cat]) {
            $db->exec(
                'INSERT INTO services (name, description, duration, price, color, category) VALUES (?, ?, ?, ?, ?, ?)',
                [$name, $desc, $dur, $price, $color, $cat]
            );
        }

        if (!$demo) {
            $db->exec("INSERT INTO staff (name, title, color) VALUES ('Titolare', 'Parrucchiere', '#ec4899')");
            return;
        }

        $staff = [
            ['Giulia', 'giulia@example.com', 'Titolare / Hair stylist', '#ec4899'],
            ['Marco', 'marco@example.com', 'Barbiere', '#3b82f6'],
            ['Sara', 'sara@example.com', 'Colorista', '#f59e0b'],
            ['Elena', 'elena@example.com', 'Apprendista', '#8b5cf6'],
        ];
        foreach ($staff as [$name, $email, $title, $color]) {
            $db->exec('INSERT INTO staff (name, email, title, color) VALUES (?, ?, ?, ?)', [$name, $email, $title, $color]);
        }
        $clients = [
            ['Chiara Rossi', 'chiara@example.com', '333 1234567'],
            ['Luca Bianchi', 'luca@example.com', '347 7654321'],
            ['Francesca Verdi', 'francesca@example.com', '320 1112233'],
            ['Paolo Neri', 'paolo@example.com', '339 4445566'],
        ];
        foreach ($clients as [$name, $email, $phone]) {
            $db->exec('INSERT INTO clients (name, email, phone, notes) VALUES (?, ?, ?, ?)', [$name, $email, $phone, '']);
        }
        $products = [
            ['Shampoo professionale', 'Salonline', 'Cura capelli', 14.9, 7.0, 25],
            ['Maschera nutriente', 'Salonline', 'Cura capelli', 18.5, 9.0, 18],
            ['Lacca extra forte', 'StylePro', 'Styling', 11.9, 5.5, 30],
            ['Cera modellante uomo', 'StylePro', 'Styling', 12.5, 6.0, 20],
        ];
        foreach ($products as [$name, $brand, $cat, $price, $cost, $stock]) {
            $db->exec(
                'INSERT INTO products (name, brand, category, price, cost, stock) VALUES (?, ?, ?, ?, ?, ?)',
                [$name, $brand, $cat, $price, $cost, $stock]
            );
        }
    }
}
