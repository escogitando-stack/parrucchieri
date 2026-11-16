<?php
declare(strict_types=1);

namespace App;

/**
 * Strumenti del titolare serviti come pagine del sito: statistiche e pacchetti/carnet.
 */
final class Owner
{
    // ── Statistiche ──────────────────────────────────────────────────

    /**
     * @return array<string,mixed> kpi, months, services, staff, heat, lapsed
     */
    public static function stats(Db $db, int $accountId, int $days = 90, ?int $now = null): array
    {
        $now ??= time();
        $today = date('Y-m-d', $now);
        $from = date('Y-m-d', $now - $days * 86400);
        $one = static fn(string $sql, array $p): float => (float)($db->row($sql, $p)['v'] ?? 0);

        $kpi = [
            'revenue' => $one("SELECT COALESCE(SUM(total_price), 0) AS v FROM appointments WHERE account_id = ? AND status = 'completed' AND scheduled_date BETWEEN ? AND ?", [$accountId, $from, $today]),
            'completed' => (int)$one("SELECT COUNT(*) AS v FROM appointments WHERE account_id = ? AND status = 'completed' AND scheduled_date BETWEEN ? AND ?", [$accountId, $from, $today]),
            'lost' => (int)$one("SELECT COUNT(*) AS v FROM appointments WHERE account_id = ? AND status IN ('cancelled', 'no_show') AND scheduled_date BETWEEN ? AND ?", [$accountId, $from, $today]),
            'no_show' => (int)$one("SELECT COUNT(*) AS v FROM appointments WHERE account_id = ? AND status = 'no_show' AND scheduled_date BETWEEN ? AND ?", [$accountId, $from, $today]),
            'online' => (int)$one("SELECT COUNT(*) AS v FROM appointments WHERE account_id = ? AND source IN ('online', 'online_free') AND scheduled_date BETWEEN ? AND ?", [$accountId, $from, $today]),
            'all' => (int)$one("SELECT COUNT(*) AS v FROM appointments WHERE account_id = ? AND scheduled_date BETWEEN ? AND ?", [$accountId, $from, $today]),
            'new_clients' => (int)$one('SELECT COUNT(*) AS v FROM clients WHERE account_id = ? AND created_at >= ?', [$accountId, $from . ' 00:00:00']),
        ];
        $kpi['avg_ticket'] = $kpi['completed'] > 0 ? $kpi['revenue'] / $kpi['completed'] : 0.0;

        // Incassi degli ultimi 12 mesi
        $months = [];
        $first = strtotime(date('Y-m-01', $now) . ' -11 months');
        for ($i = 0; $i < 12; $i++) {
            $months[date('Y-m', (int)strtotime("+$i months", (int)$first))] = 0.0;
        }
        foreach ($db->all("SELECT scheduled_date, total_price FROM appointments WHERE account_id = ? AND status = 'completed' AND scheduled_date >= ?", [$accountId, date('Y-m-d', (int)$first)]) as $r) {
            $m = substr((string)$r['scheduled_date'], 0, 7);
            if (isset($months[$m])) {
                $months[$m] += (float)$r['total_price'];
            }
        }

        $services = $db->all(
            "SELECT s.name, COUNT(*) AS n, COALESCE(SUM(x.price), 0) AS revenue FROM appointment_services x
             JOIN appointments a ON a.id = x.appointment_id JOIN services s ON s.id = x.service_id
             WHERE a.account_id = ? AND a.status = 'completed' AND a.scheduled_date BETWEEN ? AND ?
             GROUP BY s.id, s.name ORDER BY n DESC, revenue DESC LIMIT 10",
            [$accountId, $from, $today]
        );
        $staff = $db->all(
            "SELECT COALESCE(st.name, '—') AS name, COUNT(*) AS n, COALESCE(SUM(a.total_price), 0) AS revenue FROM appointments a
             LEFT JOIN staff st ON st.id = a.staff_id
             WHERE a.account_id = ? AND a.status = 'completed' AND a.scheduled_date BETWEEN ? AND ?
             GROUP BY st.id, st.name ORDER BY revenue DESC",
            [$accountId, $from, $today]
        );

        // Giorni e fasce più richiesti (appuntamenti non annullati, anche futuri già presi)
        $heat = [];
        foreach ($db->all("SELECT scheduled_date, start_time FROM appointments WHERE account_id = ? AND status NOT IN ('cancelled') AND scheduled_date >= ?", [$accountId, $from]) as $r) {
            $wd = (int)date('N', (int)strtotime((string)$r['scheduled_date'] . ' 12:00:00'));
            $h = (int)substr((string)$r['start_time'], 0, 2);
            $heat[$wd][$h] = ($heat[$wd][$h] ?? 0) + 1;
        }

        return ['kpi' => $kpi, 'months' => $months, 'services' => $services, 'staff' => $staff, 'heat' => $heat, 'lapsed' => self::lapsedClients($db, $accountId, 60, $now)];
    }

    /**
     * Clienti che non tornano da almeno $days giorni e non hanno appuntamenti in programma.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function lapsedClients(Db $db, int $accountId, int $days = 60, ?int $now = null): array
    {
        $now ??= time();
        $today = date('Y-m-d', $now);
        return $db->all(
            "SELECT c.id, c.name, c.phone, c.email, MAX(a.scheduled_date) AS last_visit, COUNT(a.id) AS visits
             FROM clients c JOIN appointments a ON a.client_id = c.id AND a.status = 'completed'
             WHERE c.account_id = ?
               AND NOT EXISTS (SELECT 1 FROM appointments f WHERE f.client_id = c.id AND f.scheduled_date >= ? AND f.status NOT IN ('cancelled', 'no_show'))
             GROUP BY c.id, c.name, c.phone, c.email
             HAVING MAX(a.scheduled_date) <= ?
             ORDER BY last_visit DESC LIMIT 40",
            [$accountId, $today, date('Y-m-d', $now - $days * 86400)]
        );
    }

    // ── Pacchetti e carnet ───────────────────────────────────────────

    /** @return array<int,array<string,mixed>> */
    public static function packages(Db $db, int $accountId): array
    {
        return $db->all('SELECT p.*, s.name AS service_name FROM packages p LEFT JOIN services s ON s.id = p.service_id WHERE p.account_id = ? ORDER BY p.active DESC, p.name', [$accountId]);
    }

    /** Crea (id = 0) o aggiorna un tipo di pacchetto. Restituisce un errore oppure null. @param array<string,mixed> $in */
    public static function savePackage(Db $db, int $accountId, int $id, array $in): ?string
    {
        $name = trim((string)($in['name'] ?? ''));
        $sessions = (int)($in['sessions'] ?? 0);
        $price = str_replace([',', '€', ' '], ['.', '', ''], trim((string)($in['price'] ?? '0')));
        $valid = (int)($in['valid_days'] ?? 0);
        $service = (int)($in['service_id'] ?? 0);
        if ($name === '' || mb_strlen($name) > 80) {
            return 'Dai un nome al pacchetto (massimo 80 caratteri).';
        }
        if ($sessions < 1 || $sessions > 200) {
            return 'Il numero di sedute deve essere tra 1 e 200.';
        }
        if (!is_numeric($price) || (float)$price < 0 || (float)$price > 100000) {
            return 'Il prezzo non è valido.';
        }
        if ($valid < 0 || $valid > 3650) {
            return 'La validità deve essere tra 0 (senza scadenza) e 3650 giorni.';
        }
        if ($service > 0 && $db->row('SELECT id FROM services WHERE id = ? AND account_id = ?', [$service, $accountId]) === null) {
            return 'Servizio non trovato.';
        }
        $active = !empty($in['active']) || $id === 0 ? 1 : 0;
        $vals = [$name, $service > 0 ? $service : null, $sessions, round((float)$price, 2), $valid, $active];
        if ($id === 0) {
            $db->exec('INSERT INTO packages (account_id, name, service_id, sessions, price, valid_days, active) VALUES (?, ?, ?, ?, ?, ?, ?)', array_merge([$accountId], $vals));
            return null;
        }
        if ($db->exec('UPDATE packages SET name = ?, service_id = ?, sessions = ?, price = ?, valid_days = ?, active = ? WHERE id = ? AND account_id = ?', array_merge($vals, [$id, $accountId])) === 0
            && $db->row('SELECT id FROM packages WHERE id = ? AND account_id = ?', [$id, $accountId]) === null) {
            return 'Pacchetto non trovato.';
        }
        return null;
    }

    /** Vende un pacchetto a un cliente. Restituisce un errore oppure null. */
    public static function sell(Db $db, int $accountId, int $packageId, int $clientId, string $note = '', ?int $now = null): ?string
    {
        $now ??= time();
        $p = $db->row('SELECT * FROM packages WHERE id = ? AND account_id = ?', [$packageId, $accountId]);
        $c = $db->row('SELECT id FROM clients WHERE id = ? AND account_id = ?', [$clientId, $accountId]);
        if ($p === null || $c === null) {
            return 'Scegli il pacchetto e il cliente.';
        }
        $expires = (int)$p['valid_days'] > 0 ? date('Y-m-d', $now + (int)$p['valid_days'] * 86400) : '';
        $db->exec(
            'INSERT INTO client_packages (account_id, client_id, package_id, name, sessions_total, sessions_used, price, purchased_at, expires_at, note) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)',
            [$accountId, $clientId, $packageId, $p['name'], (int)$p['sessions'], (float)$p['price'], date('Y-m-d', $now), $expires, mb_substr(trim($note), 0, 255)]
        );
        return null;
    }

    /**
     * Carnet venduti, i più "vivi" in cima (con sedute rimaste e non scaduti).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function clientPackages(Db $db, int $accountId, string $search = '', ?int $now = null): array
    {
        $now ??= time();
        $today = date('Y-m-d', $now);
        $sql = 'SELECT cp.*, c.name AS client_name, c.phone AS client_phone FROM client_packages cp JOIN clients c ON c.id = cp.client_id WHERE cp.account_id = ?';
        $p = [$accountId];
        if ($search !== '') {
            $sql .= ' AND c.name LIKE ?';
            $p[] = '%' . $search . '%';
        }
        $rows = $db->all($sql . ' ORDER BY cp.id DESC LIMIT 300', $p);
        foreach ($rows as &$r) {
            $left = (int)$r['sessions_total'] - (int)$r['sessions_used'];
            $expired = (string)$r['expires_at'] !== '' && (string)$r['expires_at'] < $today;
            $r['left'] = $left;
            $r['state'] = $left <= 0 ? 'done' : ($expired ? 'expired' : 'active');
        }
        unset($r);
        $rank = ['active' => 0, 'expired' => 1, 'done' => 2];
        usort($rows, static fn($a, $b) => ($rank[$a['state']] <=> $rank[$b['state']]) ?: ((int)$b['id'] <=> (int)$a['id']));
        return $rows;
    }

    /** Segna una seduta usata ($delta = 1) o la annulla ($delta = -1). */
    public static function useSession(Db $db, int $accountId, int $clientPackageId, int $delta = 1, ?int $now = null): ?string
    {
        $now ??= time();
        $cp = $db->row('SELECT * FROM client_packages WHERE id = ? AND account_id = ?', [$clientPackageId, $accountId]);
        if ($cp === null) {
            return 'Carnet non trovato.';
        }
        $used = (int)$cp['sessions_used'] + ($delta >= 0 ? 1 : -1);
        if ($used > (int)$cp['sessions_total']) {
            return 'Le sedute di questo carnet sono già state usate tutte.';
        }
        if ($used < 0) {
            return 'Non ci sono sedute da annullare.';
        }
        $db->exec('UPDATE client_packages SET sessions_used = ?, last_used_at = ? WHERE id = ?', [$used, $delta >= 0 ? date('Y-m-d H:i:s', $now) : (string)$cp['last_used_at'], $clientPackageId]);
        return null;
    }

    public static function deleteClientPackage(Db $db, int $accountId, int $id): bool
    {
        return $db->exec('DELETE FROM client_packages WHERE id = ? AND account_id = ?', [$id, $accountId]) > 0;
    }
}
