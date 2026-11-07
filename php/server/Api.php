<?php
declare(strict_types=1);

namespace App;

require_once __DIR__ . '/scheduling.php';

/** Errore HTTP da restituire al client come {"error": "..."} (più eventuali campi extra). */
final class HttpError extends \RuntimeException
{
    /** @param array<string,mixed> $extra */
    public function __construct(public readonly int $status, string $message, public readonly array $extra = [])
    {
        parent::__construct($message);
    }
}

/**
 * API JSON degli appuntamenti. Le rotte e le forme delle risposte sono le stesse
 * della versione TypeScript, così il client React/Preact funziona senza modifiche.
 */
final class Api
{
    private const STATUSES = ['requested', 'booked', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];

    /** @param array<string,mixed> $cfg */
    public function __construct(private Db $db, private int $acct, private array $cfg = [])
    {
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @return array{0:int,1:array<string,mixed>}
     */
    public function handle(string $method, string $path, array $query = [], ?array $body = null): array
    {
        try {
            foreach ($this->routes() as [$m, $pattern, $handler]) {
                if ($m === $method && preg_match($pattern, $path, $matches)) {
                    array_shift($matches);
                    return $this->{$handler}($matches, $query, $body ?? []);
                }
            }
            throw new HttpError(404, 'Non trovato');
        } catch (HttpError $e) {
            return [$e->status, ['error' => $e->getMessage()] + $e->extra];
        }
    }

    /** @return array<int, array{0:string,1:string,2:string}> */
    private function routes(): array
    {
        return [
            ['GET', '#^/api/account$#', 'getAccount'],
            ['PUT', '#^/api/account$#', 'updateAccount'],
            ['POST', '#^/api/account/password$#', 'changePassword'],
            ['GET', '#^/api/stats$#', 'stats'],
            ['POST', '#^/api/appointments/(\d+)/approve$#', 'approveAppointment'],
            ['POST', '#^/api/appointments/(\d+)/reject$#', 'rejectAppointment'],
            ['GET', '#^/api/requests$#', 'listRequests'],
            ['GET', '#^/api/closures$#', 'listClosures'],
            ['POST', '#^/api/closures$#', 'createClosure'],
            ['DELETE', '#^/api/closures$#', 'deleteClosure'],
            ['GET', '#^/api/working-hours$#', 'listWorkingHours'],
            ['PUT', '#^/api/working-hours/(\d+)$#', 'setWorkingHours'],
            ['GET', '#^/api/calendar$#', 'calendar'],
            ['GET', '#^/api/appointments$#', 'listAppointments'],
            ['POST', '#^/api/appointments$#', 'createAppointment'],
            ['GET', '#^/api/appointments/(\d+)$#', 'getAppointment'],
            ['PUT', '#^/api/appointments/(\d+)$#', 'updateAppointment'],
            ['DELETE', '#^/api/appointments/(\d+)$#', 'deleteAppointment'],
            ['POST', '#^/api/appointments/(\d+)/notes$#', 'addNote'],
            ['DELETE', '#^/api/notes/(\d+)$#', 'deleteNote'],
            ['GET', '#^/api/clients$#', 'listClients'],
            ['GET', '#^/api/clients/all$#', 'allClients'],
            ['POST', '#^/api/clients$#', 'createClient'],
            ['GET', '#^/api/clients/(\d+)$#', 'getClient'],
            ['PUT', '#^/api/clients/(\d+)$#', 'updateClient'],
            ['DELETE', '#^/api/clients/(\d+)$#', 'deleteClient'],
            ['GET', '#^/api/staff$#', 'listStaff'],
            ['GET', '#^/api/staff/all$#', 'allStaff'],
            ['POST', '#^/api/staff$#', 'createStaff'],
            ['PUT', '#^/api/staff/(\d+)$#', 'updateStaff'],
            ['DELETE', '#^/api/staff/(\d+)$#', 'deleteStaff'],
            ['GET', '#^/api/services$#', 'listServices'],
            ['POST', '#^/api/services$#', 'createService'],
            ['PUT', '#^/api/services/(\d+)$#', 'updateService'],
            ['DELETE', '#^/api/services/(\d+)$#', 'deleteService'],
            ['POST', '#^/api/blocked-slots$#', 'createBlockedSlot'],
            ['DELETE', '#^/api/blocked-slots/(\d+)$#', 'deleteBlockedSlot'],
            ['GET', '#^/api/products$#', 'listProducts'],
            ['POST', '#^/api/products$#', 'createProduct'],
            ['PUT', '#^/api/products/(\d+)$#', 'updateProduct'],
            ['DELETE', '#^/api/products/(\d+)$#', 'deleteProduct'],
        ];
    }

    // ── Validazione input ────────────────────────────────────────────

    private function reqString(array $b, string $k): string
    {
        if (!isset($b[$k]) || !is_string($b[$k]) || trim($b[$k]) === '') {
            throw new HttpError(400, "Campo obbligatorio: $k");
        }
        return trim($b[$k]);
    }

    private function optString(array $b, string $k): ?string
    {
        if (!array_key_exists($k, $b) || $b[$k] === null) {
            return null;
        }
        if (!is_string($b[$k])) {
            throw new HttpError(400, "Il campo $k deve essere un testo");
        }
        return $b[$k];
    }

    private function optInt(array $b, string $k): ?int
    {
        if (!array_key_exists($k, $b) || $b[$k] === null) {
            return null;
        }
        if (!is_int($b[$k]) && !(is_float($b[$k]) && floor($b[$k]) === $b[$k])) {
            throw new HttpError(400, "Il campo $k deve essere un numero intero");
        }
        return (int)$b[$k];
    }

    private function optNumber(array $b, string $k): ?float
    {
        if (!array_key_exists($k, $b) || $b[$k] === null) {
            return null;
        }
        if (!is_int($b[$k]) && !is_float($b[$k])) {
            throw new HttpError(400, "Il campo $k deve essere un numero");
        }
        return (float)$b[$k];
    }

    private function date(string $value, string $field): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || !checkdate((int)substr($value, 5, 2), (int)substr($value, 8, 2), (int)substr($value, 0, 4))) {
            throw new HttpError(400, "Data non valida in $field (usa AAAA-MM-GG)");
        }
        return $value;
    }

    private function color(?string $c): ?string
    {
        if ($c !== null && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $c)) {
            throw new HttpError(400, 'Colore non valido');
        }
        return $c;
    }

    private function id(array $m): int
    {
        return (int)$m[0];
    }

    private function paging(array $q): array
    {
        $page = max(1, (int)($q['page'] ?? 1));
        $limit = min(200, max(1, (int)($q['limit'] ?? 50)));
        return [$page, $limit, ($page - 1) * $limit];
    }

    private function like(string $s): string
    {
        return '%' . $s . '%';
    }

    /**
     * Costruisce "col = ?, ..." solo con le colonne ammesse (mai nomi dal client).
     *
     * @param array<string,mixed> $values
     * @return array{0:string[],1:array<int,mixed>}
     */
    private function sets(array $values): array
    {
        $sets = [];
        $params = [];
        foreach ($values as $col => $val) {
            if ($val !== null || $col === 'staff_id') {
                $sets[] = "$col = ?";
                $params[] = $val;
            }
        }
        return [$sets, $params];
    }

    private function exists(string $table, int $id): bool
    {
        return $this->db->row("SELECT id FROM $table WHERE id = ? AND account_id = ?", [$id, $this->acct]) !== null;
    }

    // ── Account ──────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function accountPayload(): array
    {
        $acct = Auth::account($this->db, $this->acct) ?? throw new HttpError(404, 'Non trovato');
        $preset = Categories::get((string)$acct['category']);
        $plan = Auth::effectivePlan($this->cfg, $acct);
        return [
            'email' => $acct['email'],
            'business_name' => $acct['business_name'],
            'category' => $acct['category'],
            'category_label' => $preset['label'],
            'terms' => $preset['terms'] + ['staff_hint' => $preset['staff_hint']],
            'categories' => Categories::options(),
            'plan' => ['state' => $plan['state'], 'days_left' => $plan['days_left'], 'ends_at' => $plan['ends_at']],
            'is_admin' => Auth::isAdmin($this->cfg, $acct),
            'slug' => $acct['slug'],
            'shop_url' => Notify::shopUrl($this->cfg, $acct),
            'booking_enabled' => (int)$acct['booking_enabled'],
            'slot_minutes' => (int)$acct['slot_minutes'],
            'booking_notice_hours' => (int)$acct['booking_notice_hours'],
            'booking_horizon_days' => (int)$acct['booking_horizon_days'],
            'shop_phone' => $acct['shop_phone'],
            'shop_whatsapp' => $acct['shop_whatsapp'],
            'shop_email' => $acct['shop_email'],
            'shop_address' => $acct['shop_address'],
            'shop_description' => $acct['shop_description'],
            'shop_hours' => $acct['shop_hours'],
            'has_logo' => ($acct['logo_mime'] ?? '') !== '',
            'impersonating' => Auth::impersonator() !== null,
            'daily_summary' => $this->db->metaGet('nosummary_' . $this->acct) !== '1',
        ];
    }

    private function getAccount(array $m, array $q, array $b): array
    {
        return [200, ['account' => $this->accountPayload()]];
    }

    private function text(array $b, string $k, int $max): ?string
    {
        $v = $this->optString($b, $k);
        if ($v === null) {
            return null;
        }
        $v = trim($v);
        if (mb_strlen($v) > $max) {
            throw new HttpError(400, "Il campo $k è troppo lungo (massimo $max caratteri)");
        }
        return $v;
    }

    private function updateAccount(array $m, array $q, array $b): array
    {
        $name = $this->optString($b, 'business_name');
        $category = $this->optString($b, 'category');
        if ($name !== null && (trim($name) === '' || mb_strlen(trim($name)) > 120)) {
            throw new HttpError(400, 'Il nome dell\'attività non è valido');
        }
        if ($category !== null && !Categories::valid($category)) {
            throw new HttpError(400, 'Tipo di attività non valido');
        }
        $slug = $this->optString($b, 'slug');
        if ($slug !== null) {
            $slug = strtolower(trim($slug));
            if (!Booking::slugValid($slug)) {
                throw new HttpError(400, 'Indirizzo non valido: usa da 3 a 80 tra lettere minuscole, numeri e trattini.');
            }
            if ($this->db->row('SELECT id FROM accounts WHERE slug = ? AND id <> ?', [$slug, $this->acct]) !== null) {
                throw new HttpError(409, 'Questo indirizzo è già usato da un altro negozio.');
            }
        }
        $email = $this->text($b, 'shop_email', 190);
        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpError(400, 'L\'email del negozio non è valida');
        }
        $slot = $this->optInt($b, 'slot_minutes');
        if ($slot !== null && !in_array($slot, [10, 15, 20, 30, 45, 60], true)) {
            throw new HttpError(400, 'Intervallo tra gli orari non valido');
        }
        $notice = $this->optInt($b, 'booking_notice_hours');
        if ($notice !== null && ($notice < 0 || $notice > 168)) {
            throw new HttpError(400, 'Il preavviso deve essere tra 0 e 168 ore');
        }
        $horizon = $this->optInt($b, 'booking_horizon_days');
        if ($horizon !== null && ($horizon < 1 || $horizon > 365)) {
            throw new HttpError(400, 'Il periodo prenotabile deve essere tra 1 e 365 giorni');
        }
        $enabled = $this->optInt($b, 'booking_enabled');
        if (array_key_exists('daily_summary', $b)) {
            $this->db->metaSet('nosummary_' . $this->acct, $b['daily_summary'] ? '0' : '1');
        }
        $values = [
            'business_name' => $name !== null ? trim($name) : null,
            'category' => $category,
            'slug' => $slug,
            'shop_email' => $email,
            'shop_phone' => $this->text($b, 'shop_phone', 64),
            'shop_whatsapp' => $this->text($b, 'shop_whatsapp', 64),
            'shop_address' => $this->text($b, 'shop_address', 255),
            'shop_description' => $this->text($b, 'shop_description', 1000),
            'shop_hours' => $this->text($b, 'shop_hours', 500),
            'slot_minutes' => $slot,
            'booking_notice_hours' => $notice,
            'booking_horizon_days' => $horizon,
            'booking_enabled' => $enabled !== null ? ($enabled ? 1 : 0) : null,
        ];
        if (($b['remove_logo'] ?? false) === true) {
            $values['logo_mime'] = '';
            $values['logo_data'] = null;
        }
        $logoUrl = $this->optString($b, 'logo');
        if ($logoUrl !== null && $logoUrl !== '') {
            $logo = Shop::parseLogo($logoUrl);
            if ($logo === null) {
                throw new HttpError(400, 'Logo non valido: usa un\'immagine PNG, JPEG, WebP o GIF fino a 400 KB.');
            }
            $values['logo_mime'] = $logo['mime'];
            $values['logo_data'] = $logo['data'];
        }
        $sets = [];
        $params = [];
        foreach ($values as $col => $val) {
            if ($val !== null) {
                $sets[] = "$col = ?";
                $params[] = $val;
            } elseif ($col === 'logo_data' && array_key_exists('logo_data', $values) && ($b['remove_logo'] ?? false) === true) {
                $sets[] = 'logo_data = NULL';
            }
        }
        if ($sets) {
            $params[] = $this->acct;
            $this->db->exec('UPDATE accounts SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
        }
        return [200, ['account' => $this->accountPayload()]];
    }

    private function changePassword(array $m, array $q, array $b): array
    {
        $result = Auth::changePassword($this->db, $this->acct, $this->reqString($b, 'current_password'), $this->reqString($b, 'new_password'));
        if (!$result['ok']) {
            throw new HttpError(400, $result['error'] ?? 'Password non valida');
        }
        return [200, ['ok' => true]];
    }

    // ── Statistiche ──────────────────────────────────────────────────

    private function count(string $sql, array $params = []): int
    {
        return (int)($this->db->row($sql, $params)['c'] ?? 0);
    }

    private function stats(array $m, array $q, array $b): array
    {
        $today = date('Y-m-d');
        $since = date('Y-m-d', strtotime('-30 days'));
        $A = $this->acct;
        $value = $this->db->row("SELECT COALESCE(SUM(total_price), 0) AS t FROM appointments WHERE status = 'completed' AND account_id = ?", [$A]);
        return [200, [
            'appointments' => $this->count('SELECT COUNT(*) AS c FROM appointments WHERE account_id = ?', [$A]),
            'clients' => $this->count('SELECT COUNT(*) AS c FROM clients WHERE account_id = ?', [$A]),
            'staff' => $this->count('SELECT COUNT(*) AS c FROM staff WHERE active = 1 AND account_id = ?', [$A]),
            'services' => $this->count('SELECT COUNT(*) AS c FROM services WHERE active = 1 AND account_id = ?', [$A]),
            'products' => $this->count('SELECT COUNT(*) AS c FROM products WHERE account_id = ?', [$A]),
            'today_appointments' => $this->count('SELECT COUNT(*) AS c FROM appointments WHERE scheduled_date = ? AND account_id = ?', [$today, $A]),
            'upcoming_appointments' => $this->count("SELECT COUNT(*) AS c FROM appointments WHERE status IN ('booked', 'confirmed') AND scheduled_date >= ? AND account_id = ?", [$today, $A]),
            'completed_appointments' => $this->count("SELECT COUNT(*) AS c FROM appointments WHERE status = 'completed' AND account_id = ?", [$A]),
            'revenue' => (float)($value['t'] ?? 0),
            'completed_30d' => $this->count("SELECT COUNT(*) AS c FROM appointments WHERE status = 'completed' AND scheduled_date >= ? AND scheduled_date <= ? AND account_id = ?", [$since, $today, $A]),
            'cancelled_30d' => $this->count("SELECT COUNT(*) AS c FROM appointments WHERE status = 'cancelled' AND scheduled_date >= ? AND scheduled_date <= ? AND account_id = ?", [$since, $today, $A]),
            'no_show_30d' => $this->count("SELECT COUNT(*) AS c FROM appointments WHERE status = 'no_show' AND scheduled_date >= ? AND scheduled_date <= ? AND account_id = ?", [$since, $today, $A]),
            'online_30d' => $this->count("SELECT COUNT(*) AS c FROM appointments WHERE source LIKE 'online%' AND status <> 'cancelled' AND scheduled_date >= ? AND scheduled_date <= ? AND account_id = ?", [$since, $today, $A]),
            'pending_requests' => $this->count("SELECT COUNT(*) AS c FROM appointments WHERE status = 'requested' AND account_id = ?", [$A]),
            'low_stock_products' => $this->count('SELECT COUNT(*) AS c FROM products WHERE stock <= low_stock_alert AND account_id = ?', [$A]),
        ]];
    }

    // ── Appuntamenti ─────────────────────────────────────────────────

    private const APT_SELECT = "SELECT a.*, cl.name AS client_name, cl.phone AS client_phone, cl.email AS client_email,
            s.name AS staff_name, s.color AS staff_color
        FROM appointments a
        LEFT JOIN clients cl ON cl.id = a.client_id
        LEFT JOIN staff s ON s.id = a.staff_id";

    private function appointmentServices(int $aptId): array
    {
        return $this->db->all(
            'SELECT aps.*, sv.name AS service_name FROM appointment_services aps
             LEFT JOIN services sv ON sv.id = aps.service_id
             WHERE aps.appointment_id = ? ORDER BY aps.id',
            [$aptId]
        );
    }

    private function fetchAppointment(int $id): ?array
    {
        return $this->db->row(self::APT_SELECT . ' WHERE a.id = ? AND a.account_id = ?', [$id, $this->acct]);
    }

    private function listAppointments(array $m, array $q, array $b): array
    {
        [, $limit, $offset] = $this->paging($q);
        $where = 'WHERE a.account_id = ?';
        $params = [$this->acct];
        if (!empty($q['search'])) {
            $where .= " AND (a.identifier LIKE ? OR cl.name LIKE ?)";
            $s = $this->like((string)$q['search']);
            array_push($params, $s, $s);
        }
        if (!empty($q['status'])) {
            $where .= ' AND a.status = ?';
            $params[] = (string)$q['status'];
        }
        if (!empty($q['date'])) {
            $where .= ' AND a.scheduled_date = ?';
            $params[] = (string)$q['date'];
        }
        if (!empty($q['staff_id'])) {
            $where .= ' AND a.staff_id = ?';
            $params[] = (int)$q['staff_id'];
        }
        $total = $this->count("SELECT COUNT(*) AS c FROM appointments a LEFT JOIN clients cl ON cl.id = a.client_id $where", $params);
        $rows = $this->db->all(
            self::APT_SELECT . " $where ORDER BY a.scheduled_date DESC, a.start_time ASC LIMIT $limit OFFSET $offset",
            $params
        );
        return [200, ['appointments' => $rows, 'total' => $total]];
    }

    private function calendar(array $m, array $q, array $b): array
    {
        if (empty($q['start']) || empty($q['end'])) {
            throw new HttpError(400, 'Parametri start e end obbligatori');
        }
        $start = $this->date((string)$q['start'], 'start');
        $end = $this->date((string)$q['end'], 'end');
        $rows = $this->db->all(
            self::APT_SELECT . " WHERE a.account_id = ? AND a.scheduled_date >= ? AND a.scheduled_date <= ? AND a.status <> 'cancelled'
             ORDER BY a.start_time ASC",
            [$this->acct, $start, $end]
        );
        foreach ($rows as &$row) {
            $row['appointment_services'] = $this->appointmentServices((int)$row['id']);
        }
        unset($row);
        $blocked = $this->db->all(
            'SELECT b.*, s.name AS staff_name FROM blocked_slots b
             LEFT JOIN staff s ON s.id = b.staff_id
             WHERE b.account_id = ? AND b.blocked_date >= ? AND b.blocked_date <= ?
             ORDER BY b.start_time ASC',
            [$this->acct, $start, $end]
        );
        return [200, ['appointments' => $rows, 'blocked_slots' => $blocked]];
    }

    private function getAppointment(array $m, array $q, array $b): array
    {
        $id = $this->id($m);
        $apt = $this->fetchAppointment($id);
        if (!$apt) {
            throw new HttpError(404, 'Non trovato');
        }
        $apt['appointment_services'] = $this->appointmentServices($id);
        $apt['appointment_notes'] = $this->db->all(
            'SELECT * FROM appointment_notes WHERE appointment_id = ? ORDER BY created_at DESC, id DESC',
            [$id]
        );
        $apt['whatsapp'] = $this->whatsappLinks($apt);
        return [200, ['appointment' => $apt]];
    }

    /** Link WhatsApp già scritti (conferma / rifiuto), da inviare a mano dal titolare. @return array{confirm:string,decline:string} */
    private function whatsappLinks(array $apt): array
    {
        $acct = Auth::account($this->db, $this->acct) ?? [];
        $name = (string)($apt['client_name'] ?? '');
        $phone = (string)($apt['client_phone'] ?? '');
        return [
            'confirm' => Notify::whatsappUrl($acct, $apt, $name, $phone, true),
            'decline' => Notify::whatsappUrl($acct, $apt, $name, $phone, false),
        ];
    }

    /** @return array<string,mixed> */
    private function decide(int $id, bool $approve, array $b): array
    {
        $apt = $this->fetchAppointment($id) ?? throw new HttpError(404, 'Non trovato');
        if ($apt['status'] !== 'requested') {
            throw new HttpError(409, 'Questa richiesta è già stata gestita.');
        }
        $acct = Auth::account($this->db, $this->acct) ?? throw new HttpError(404, 'Non trovato');
        if ($approve) {
            $staffId = $this->optInt($b, 'staff_id') ?? ($apt['staff_id'] !== null ? (int)$apt['staff_id'] : null);
            if ($staffId === null) {
                $first = $this->db->row('SELECT id FROM staff WHERE account_id = ? AND active = 1 ORDER BY id LIMIT 1', [$this->acct]);
                $staffId = $first !== null ? (int)$first['id'] : null;
            }
            if ($staffId === null || !$this->exists('staff', $staffId)) {
                throw new HttpError(400, 'Scegli il collaboratore');
            }
            $start = $this->optString($b, 'start_time') ?? (string)$apt['start_time'];
            $duration = max(1, (toMinutes((string)$apt['end_time']) ?? 0) - (toMinutes((string)$apt['start_time']) ?? 0));
            if (toMinutes($start) === null) {
                throw new HttpError(400, 'Gli orari devono essere nel formato HH:MM');
            }
            $end = addMinutes($start, $duration);
            if (($b['allow_conflict'] ?? false) !== true) {
                $conflicts = findConflicts($start, $end, $this->busyFor($staffId, (string)$apt['scheduled_date'], $id));
                if ($conflicts) {
                    throw new HttpError(409, describeConflicts($this->staffName($staffId), $conflicts), ['conflicts' => $conflicts]);
                }
            }
            $this->db->exec("UPDATE appointments SET status = 'confirmed', staff_id = ?, start_time = ?, end_time = ? WHERE id = ? AND account_id = ?", [$staffId, $start, $end, $id, $this->acct]);
        } else {
            $this->db->exec("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND account_id = ?", [$id, $this->acct]);
        }
        $apt = $this->fetchAppointment($id) ?? $apt;
        $sent = Notify::decision($this->cfg, $acct, $apt, ['name' => $apt['client_name'], 'email' => $apt['client_email']], Booking::serviceNames($this->db, $id), $approve);
        $apt['whatsapp'] = $this->whatsappLinks($apt);
        return [200, ['appointment' => $apt, 'email_sent' => $sent, 'whatsapp_url' => $approve ? $apt['whatsapp']['confirm'] : $apt['whatsapp']['decline']]];
    }

    private function approveAppointment(array $m, array $q, array $b): array
    {
        return $this->decide($this->id($m), true, $b);
    }

    private function rejectAppointment(array $m, array $q, array $b): array
    {
        return $this->decide($this->id($m), false, $b);
    }

    // ── Orari di lavoro ──────────────────────────────────────────────

    private function listWorkingHours(array $m, array $q, array $b): array
    {
        $out = [];
        foreach ($this->db->all('SELECT staff_id, weekday, start_time, end_time FROM working_hours WHERE account_id = ? ORDER BY staff_id, weekday, start_time', [$this->acct]) as $r) {
            $out[(int)$r['staff_id']][] = ['weekday' => (int)$r['weekday'], 'start_time' => $r['start_time'], 'end_time' => $r['end_time']];
        }
        return [200, ['hours' => (object)$out]];
    }

    private function setWorkingHours(array $m, array $q, array $b): array
    {
        $staffId = $this->id($m);
        if (!$this->exists('staff', $staffId)) {
            throw new HttpError(404, 'Collaboratore non trovato');
        }
        $list = $b['hours'] ?? null;
        if (!is_array($list) || count($list) > 70) {
            throw new HttpError(400, 'Orari non validi');
        }
        $clean = [];
        foreach ($list as $h) {
            $day = is_array($h) ? ($h['weekday'] ?? null) : null;
            $from = is_array($h) ? ($h['start_time'] ?? null) : null;
            $to = is_array($h) ? ($h['end_time'] ?? null) : null;
            if (!is_int($day) || $day < 1 || $day > 7 || !is_string($from) || !is_string($to)) {
                throw new HttpError(400, 'Orari non validi');
            }
            $f = toMinutes($from);
            $t = toMinutes($to);
            if ($f === null || $t === null || $t <= $f) {
                throw new HttpError(400, 'In ogni fascia l\'orario di fine deve essere dopo quello di inizio');
            }
            $clean[] = [$day, addMinutes('00:00', $f), addMinutes('00:00', $t)];
        }
        $this->db->exec('DELETE FROM working_hours WHERE staff_id = ? AND account_id = ?', [$staffId, $this->acct]);
        foreach ($clean as [$day, $from, $to]) {
            $this->db->exec('INSERT INTO working_hours (account_id, staff_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?, ?)', [$this->acct, $staffId, $day, $from, $to]);
        }
        return [200, ['ok' => true]];
    }

    /** Cosa occupa già un collaboratore in una data: altri appuntamenti e orari bloccati. */
    private function busyFor(int $staffId, string $date, ?int $excludeId = null): array
    {
        $sql = "SELECT a.start_time, a.end_time, cl.name AS client_name
                FROM appointments a LEFT JOIN clients cl ON cl.id = a.client_id
                WHERE a.account_id = ? AND a.staff_id = ? AND a.scheduled_date = ? AND a.status <> 'cancelled'";
        $params = [$this->acct, $staffId, $date];
        if ($excludeId !== null) {
            $sql .= ' AND a.id <> ?';
            $params[] = $excludeId;
        }
        $busy = [];
        foreach ($this->db->all($sql, $params) as $a) {
            $busy[] = [
                'kind' => 'appointment',
                'start_time' => $a['start_time'],
                'end_time' => $a['end_time'],
                'label' => $a['client_name'] ?: 'altra prenotazione',
            ];
        }
        $blocks = $this->db->all(
            'SELECT start_time, end_time, reason FROM blocked_slots WHERE account_id = ? AND staff_id = ? AND blocked_date = ?',
            [$this->acct, $staffId, $date]
        );
        foreach ($blocks as $bl) {
            $busy[] = [
                'kind' => 'blocked',
                'start_time' => $bl['start_time'],
                'end_time' => $bl['end_time'],
                'label' => $bl['reason'] ?: 'bloccato',
            ];
        }
        return $busy;
    }

    private function staffName(int $staffId): string
    {
        return (string)($this->db->row('SELECT name FROM staff WHERE id = ? AND account_id = ?', [$staffId, $this->acct])['name'] ?? '');
    }

    private function createAppointment(array $m, array $q, array $b): array
    {
        $clientId = $this->optInt($b, 'client_id');
        if ($clientId === null) {
            throw new HttpError(400, 'Campo obbligatorio: client_id');
        }
        $date = $this->date($this->reqString($b, 'scheduled_date'), 'scheduled_date');
        $staffId = $this->optInt($b, 'staff_id');
        $startTime = $this->optString($b, 'start_time') ?? '09:00';
        $notes = $this->optString($b, 'notes') ?? '';
        $recurring = $this->optInt($b, 'is_recurring') ?? 0;
        $interval = $this->optString($b, 'recurrence_interval') ?? '';
        $allow = ($b['allow_conflict'] ?? false) === true;
        $serviceIds = [];
        foreach (($b['service_ids'] ?? []) as $sid) {
            if (!is_int($sid)) {
                throw new HttpError(400, 'service_ids deve contenere numeri interi');
            }
            $serviceIds[] = $sid;
        }

        if (!$this->exists('clients', $clientId)) {
            throw new HttpError(400, 'Cliente non trovato');
        }
        if ($staffId !== null && !$this->exists('staff', $staffId)) {
            throw new HttpError(400, 'Collaboratore non trovato');
        }

        $totalDuration = 60;
        $totalPrice = 0.0;
        $services = [];
        if ($serviceIds) {
            $in = implode(',', array_fill(0, count($serviceIds), '?'));
            $services = $this->db->all("SELECT id, duration, price FROM services WHERE account_id = ? AND id IN ($in)", array_merge([$this->acct], $serviceIds));
            $totalDuration = array_sum(array_map(fn($s) => (int)$s['duration'], $services));
            $totalPrice = array_sum(array_map(fn($s) => (float)$s['price'], $services));
        }

        $start = toMinutes($startTime);
        if ($start === null) {
            throw new HttpError(400, 'Gli orari devono essere nel formato HH:MM');
        }
        if ($totalDuration <= 0) {
            throw new HttpError(400, "L'appuntamento deve avere una durata positiva");
        }
        if ($start + $totalDuration >= 24 * 60) {
            throw new HttpError(400, "L'appuntamento deve iniziare e finire nello stesso giorno");
        }
        $endTime = addMinutes($startTime, $totalDuration);

        if ($staffId !== null && !$allow) {
            $conflicts = findConflicts($startTime, $endTime, $this->busyFor($staffId, $date));
            if ($conflicts) {
                throw new HttpError(409, describeConflicts($this->staffName($staffId), $conflicts), ['conflicts' => $conflicts]);
            }
        }

        $identifier = 'APP-' . $this->db->nextCounter($this->acct);

        $this->db->exec(
            'INSERT INTO appointments (account_id, identifier, client_id, staff_id, scheduled_date, start_time, end_time, total_price, notes, is_recurring, recurrence_interval)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$this->acct, $identifier, $clientId, $staffId, $date, $startTime, $endTime, $totalPrice, $notes, $recurring, $interval]
        );
        $aptId = $this->db->lastId();
        $byId = [];
        foreach ($services as $s) {
            $byId[(int)$s['id']] = $s;
        }
        foreach ($serviceIds as $sid) {
            if (isset($byId[$sid])) {
                $this->db->exec(
                    'INSERT INTO appointment_services (appointment_id, service_id, price, duration) VALUES (?, ?, ?, ?)',
                    [$aptId, $sid, $byId[$sid]['price'], $byId[$sid]['duration']]
                );
            }
        }
        return [201, ['appointment' => $this->fetchAppointment($aptId)]];
    }

    private function updateAppointment(array $m, array $q, array $b): array
    {
        $id = $this->id($m);
        $existing = $this->db->row(
            'SELECT staff_id, scheduled_date, start_time, end_time, status FROM appointments WHERE id = ? AND account_id = ?',
            [$id, $this->acct]
        );
        if (!$existing) {
            throw new HttpError(404, 'Non trovato');
        }
        $allow = ($b['allow_conflict'] ?? false) === true;

        // Cambio dei servizi: ricalcola durata e totale.
        $newServices = null;
        if (array_key_exists('service_ids', $b)) {
            $ids = [];
            foreach ((is_array($b['service_ids']) ? $b['service_ids'] : []) as $sid) {
                if (!is_int($sid)) {
                    throw new HttpError(400, 'service_ids deve contenere numeri interi');
                }
                $ids[$sid] = $sid;
            }
            $ids = array_values($ids);
            if ($ids === [] || count($ids) > 20) {
                throw new HttpError(400, 'Scegli almeno un servizio');
            }
            $newServices = $this->db->all('SELECT id, duration, price FROM services WHERE account_id = ? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$this->acct], $ids));
            if (count($newServices) !== count($ids)) {
                throw new HttpError(400, 'Servizio non trovato');
            }
        }

        $patch = [
            'client_id' => $this->optInt($b, 'client_id'),
            'status' => $this->optString($b, 'status'),
            'scheduled_date' => ($d = $this->optString($b, 'scheduled_date')) !== null ? $this->date($d, 'scheduled_date') : null,
            'start_time' => $this->optString($b, 'start_time'),
            'end_time' => $this->optString($b, 'end_time'),
            'total_price' => $this->optNumber($b, 'total_price'),
            'notes' => $this->optString($b, 'notes'),
        ];
        if ($patch['status'] !== null && !in_array($patch['status'], self::STATUSES, true)) {
            throw new HttpError(400, 'Stato non valido');
        }
        if ($patch['client_id'] !== null && !$this->exists('clients', $patch['client_id'])) {
            throw new HttpError(400, 'Cliente non trovato');
        }
        $staffProvided = array_key_exists('staff_id', $b);
        $newStaff = $staffProvided ? $this->optInt($b, 'staff_id') : null;
        if ($newStaff !== null && !$this->exists('staff', $newStaff)) {
            throw new HttpError(400, 'Collaboratore non trovato');
        }

        // Dove finisce l'appuntamento dopo la modifica.
        $staffId = $staffProvided ? $newStaff : ($existing['staff_id'] !== null ? (int)$existing['staff_id'] : null);
        $date = $patch['scheduled_date'] ?? $existing['scheduled_date'];
        $startTime = $patch['start_time'] ?? $existing['start_time'];
        $endTime = $patch['end_time'] ?? $existing['end_time'];

        if ($newServices !== null) {
            $sum = array_sum(array_map(static fn($r): int => (int)$r['duration'], $newServices));
            if ($sum <= 0) {
                throw new HttpError(400, "L'appuntamento deve avere una durata positiva");
            }
            if ($patch['total_price'] === null) {
                $patch['total_price'] = array_sum(array_map(static fn($r): float => (float)$r['price'], $newServices));
            }
            if ($patch['end_time'] === null) {
                $st = toMinutes($startTime);
                if ($st === null || $st + $sum >= 24 * 60) {
                    throw new HttpError(400, "L'appuntamento deve iniziare e finire nello stesso giorno");
                }
                $endTime = addMinutes($startTime, $sum);
                $patch['end_time'] = $endTime;
            }
        }

        // Spostare un appuntamento ne mantiene la durata.
        if ($newServices === null && $patch['start_time'] !== null && $patch['start_time'] !== $existing['start_time'] && $patch['end_time'] === null) {
            $was = toMinutes($existing['start_time']);
            $wasEnd = toMinutes($existing['end_time']);
            $duration = ($was !== null && $wasEnd !== null) ? max($wasEnd - $was, 0) : 0;
            $endTime = addMinutes($startTime, $duration);
            $patch['end_time'] = $endTime;
        }

        $currentStaff = $existing['staff_id'] !== null ? (int)$existing['staff_id'] : null;
        $moved = $staffId !== $currentStaff || $date !== $existing['scheduled_date']
            || $startTime !== $existing['start_time'] || $endTime !== $existing['end_time'];
        $status = $patch['status'] ?? $existing['status'];
        $restored = $existing['status'] === 'cancelled' && $status !== 'cancelled';

        // Una sovrapposizione già accettata non deve bloccare note, check-in,
        // completamento o annullamento.
        if ($moved || $restored) {
            $s = toMinutes($startTime);
            $e = toMinutes($endTime);
            if ($s === null || $e === null) {
                throw new HttpError(400, 'Gli orari devono essere nel formato HH:MM');
            }
            if ($e <= $s) {
                throw new HttpError(400, "L'appuntamento deve finire dopo l'inizio, nello stesso giorno");
            }
            if ($status !== 'cancelled' && $staffId !== null && !$allow) {
                $conflicts = findConflicts($startTime, $endTime, $this->busyFor($staffId, $date, $id));
                if ($conflicts) {
                    throw new HttpError(409, describeConflicts($this->staffName($staffId), $conflicts), ['conflicts' => $conflicts]);
                }
            }
        }

        [$sets, $params] = $this->sets($patch);
        if ($staffProvided) {
            $sets[] = 'staff_id = ?';
            $params[] = $newStaff;
        }
        $sets[] = 'updated_at = ' . $this->db->now();
        $params[] = $id;
        $params[] = $this->acct;
        $this->db->exec('UPDATE appointments SET ' . implode(', ', $sets) . ' WHERE id = ? AND account_id = ?', $params);
        if ($newServices !== null) {
            $this->db->exec('DELETE FROM appointment_services WHERE appointment_id = ?', [$id]);
            foreach ($newServices as $sv) {
                $this->db->exec('INSERT INTO appointment_services (appointment_id, service_id, price, duration) VALUES (?, ?, ?, ?)', [$id, (int)$sv['id'], $sv['price'], $sv['duration']]);
            }
        }
        return [200, ['ok' => true]];
    }

    private function deleteAppointment(array $m, array $q, array $b): array
    {
        $this->db->exec('DELETE FROM appointments WHERE id = ? AND account_id = ?', [$this->id($m), $this->acct]);
        return [200, ['ok' => true]];
    }

    private function addNote(array $m, array $q, array $b): array
    {
        $id = $this->id($m);
        $content = $this->reqString($b, 'content');
        if (!$this->exists('appointments', $id)) {
            throw new HttpError(404, 'Non trovato');
        }
        $this->db->exec('INSERT INTO appointment_notes (appointment_id, content) VALUES (?, ?)', [$id, $content]);
        return [201, ['ok' => true]];
    }

    private function deleteNote(array $m, array $q, array $b): array
    {
        $this->db->exec(
            'DELETE FROM appointment_notes WHERE id = ? AND appointment_id IN (SELECT id FROM appointments WHERE account_id = ?)',
            [$this->id($m), $this->acct]
        );
        return [200, ['ok' => true]];
    }

    // ── Clienti ──────────────────────────────────────────────────────

    private function listClients(array $m, array $q, array $b): array
    {
        [, $limit, $offset] = $this->paging($q);
        $where = 'WHERE c.account_id = ?';
        $params = [$this->acct];
        if (!empty($q['search'])) {
            $where .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
            $s = $this->like((string)$q['search']);
            array_push($params, $s, $s, $s);
        }
        $total = $this->count("SELECT COUNT(*) AS c FROM clients c $where", $params);
        $rows = $this->db->all(
            "SELECT c.*, (SELECT COUNT(*) FROM appointments WHERE client_id = c.id) AS appointment_count
             FROM clients c $where ORDER BY c.name ASC LIMIT $limit OFFSET $offset",
            $params
        );
        return [200, ['clients' => $rows, 'total' => $total]];
    }

    private function allClients(array $m, array $q, array $b): array
    {
        return [200, ['clients' => $this->db->all('SELECT id, name FROM clients WHERE account_id = ? ORDER BY name ASC', [$this->acct])]];
    }

    private function getClient(array $m, array $q, array $b): array
    {
        $id = $this->id($m);
        $client = $this->db->row('SELECT * FROM clients WHERE id = ? AND account_id = ?', [$id, $this->acct]);
        if (!$client) {
            throw new HttpError(404, 'Non trovato');
        }
        $names = $this->db->serviceNamesSql('a');
        $appointments = $this->db->all(
            "SELECT a.*, s.name AS staff_name, s.color AS staff_color,
                    $names AS service_names,
                    (SELECT an.content FROM appointment_notes an
                     WHERE an.appointment_id = a.id
                     ORDER BY an.created_at DESC, an.id DESC LIMIT 1) AS latest_note
             FROM appointments a LEFT JOIN staff s ON s.id = a.staff_id
             WHERE a.client_id = ? AND a.account_id = ? ORDER BY a.scheduled_date DESC, a.start_time DESC LIMIT 50",
            [$id, $this->acct]
        );
        return [200, ['client' => $client, 'appointments' => $appointments]];
    }

    private function createClient(array $m, array $q, array $b): array
    {
        $this->db->exec(
            'INSERT INTO clients (account_id, name, email, phone, notes) VALUES (?, ?, ?, ?, ?)',
            [
                $this->acct,
                $this->reqString($b, 'name'),
                $this->optString($b, 'email') ?? '',
                $this->optString($b, 'phone') ?? '',
                $this->optString($b, 'notes') ?? '',
            ]
        );
        return [201, ['client' => $this->db->row('SELECT * FROM clients WHERE id = ? AND account_id = ?', [$this->db->lastId(), $this->acct])]];
    }

    private function updateClient(array $m, array $q, array $b): array
    {
        $id = $this->id($m);
        $name = $this->optString($b, 'name');
        if ($name !== null && trim($name) === '') {
            throw new HttpError(400, 'Il nome non può essere vuoto');
        }
        [$sets, $params] = $this->sets([
            'name' => $name !== null ? trim($name) : null,
            'email' => $this->optString($b, 'email'),
            'phone' => $this->optString($b, 'phone'),
            'notes' => $this->optString($b, 'notes'),
        ]);
        if ($sets) {
            $sets[] = 'updated_at = ' . $this->db->now();
            $params[] = $id;
            $params[] = $this->acct;
            $this->db->exec('UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = ? AND account_id = ?', $params);
        }
        return [200, ['ok' => true]];
    }

    private function deleteClient(array $m, array $q, array $b): array
    {
        $this->db->exec('DELETE FROM clients WHERE id = ? AND account_id = ?', [$this->id($m), $this->acct]);
        return [200, ['ok' => true]];
    }

    // ── Personale ────────────────────────────────────────────────────

    private function listStaff(array $m, array $q, array $b): array
    {
        $rows = $this->db->all(
            "SELECT s.*,
                    COUNT(a.id) AS appointment_count,
                    COALESCE(SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_appointment_count,
                    COALESCE(SUM(CASE WHEN a.status = 'completed' THEN a.total_price ELSE 0 END), 0) AS completed_service_value
             FROM staff s LEFT JOIN appointments a ON a.staff_id = s.id AND a.account_id = s.account_id
             WHERE s.account_id = ?
             GROUP BY s.id ORDER BY s.name ASC",
            [$this->acct]
        );
        foreach ($rows as &$r) {
            $r['appointment_count'] = (int)$r['appointment_count'];
            $r['completed_appointment_count'] = (int)$r['completed_appointment_count'];
            $r['completed_service_value'] = (float)$r['completed_service_value'];
        }
        unset($r);
        return [200, ['staff' => $rows]];
    }

    private function allStaff(array $m, array $q, array $b): array
    {
        return [200, ['staff' => $this->db->all('SELECT id, name, color FROM staff WHERE active = 1 AND account_id = ? ORDER BY name ASC', [$this->acct])]];
    }

    private function createStaff(array $m, array $q, array $b): array
    {
        $this->db->exec(
            'INSERT INTO staff (account_id, name, email, phone, title, color) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $this->acct,
                $this->reqString($b, 'name'),
                $this->optString($b, 'email') ?? '',
                $this->optString($b, 'phone') ?? '',
                $this->optString($b, 'title') ?? '',
                $this->color($this->optString($b, 'color')) ?: '#7c3aed',
            ]
        );
        $newId = $this->db->lastId();
        Schema::defaultHours($this->db, $this->acct, $newId);
        return [201, ['staff' => $this->db->row('SELECT * FROM staff WHERE id = ? AND account_id = ?', [$newId, $this->acct])]];
    }

    private function updateStaff(array $m, array $q, array $b): array
    {
        $id = $this->id($m);
        [$sets, $params] = $this->sets([
            'name' => $this->optString($b, 'name'),
            'email' => $this->optString($b, 'email'),
            'phone' => $this->optString($b, 'phone'),
            'title' => $this->optString($b, 'title'),
            'color' => $this->color($this->optString($b, 'color')),
            'active' => $this->optInt($b, 'active'),
        ]);
        if ($sets) {
            $params[] = $id;
            $params[] = $this->acct;
            $this->db->exec('UPDATE staff SET ' . implode(', ', $sets) . ' WHERE id = ? AND account_id = ?', $params);
        }
        return [200, ['ok' => true]];
    }

    private function deleteStaff(array $m, array $q, array $b): array
    {
        $this->db->exec('DELETE FROM staff WHERE id = ? AND account_id = ?', [$this->id($m), $this->acct]);
        return [200, ['ok' => true]];
    }

    // ── Servizi ──────────────────────────────────────────────────────

    private function listServices(array $m, array $q, array $b): array
    {
        return [200, ['services' => $this->db->all('SELECT * FROM services WHERE account_id = ? ORDER BY category ASC, name ASC', [$this->acct])]];
    }

    private function createService(array $m, array $q, array $b): array
    {
        $this->db->exec(
            'INSERT INTO services (account_id, name, description, duration, price, color, category, online, auto_confirm) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->acct,
                $this->reqString($b, 'name'),
                $this->optString($b, 'description') ?? '',
                $this->optInt($b, 'duration') ?: 60,
                $this->optNumber($b, 'price') ?? 0.0,
                $this->color($this->optString($b, 'color')) ?: '#6b7280',
                $this->optString($b, 'category') ?? '',
                ($this->optInt($b, 'online') ?? 1) ? 1 : 0,
                ($this->optInt($b, 'auto_confirm') ?? 0) ? 1 : 0,
            ]
        );
        return [201, ['service' => $this->db->row('SELECT * FROM services WHERE id = ? AND account_id = ?', [$this->db->lastId(), $this->acct])]];
    }

    private function updateService(array $m, array $q, array $b): array
    {
        $id = $this->id($m);
        $duration = $this->optInt($b, 'duration');
        if ($duration !== null && $duration <= 0) {
            throw new HttpError(400, 'La durata deve essere positiva');
        }
        $price = $this->optNumber($b, 'price');
        if ($price !== null && $price < 0) {
            throw new HttpError(400, 'Il prezzo non può essere negativo');
        }
        [$sets, $params] = $this->sets([
            'name' => $this->optString($b, 'name'),
            'description' => $this->optString($b, 'description'),
            'duration' => $duration,
            'price' => $price,
            'color' => $this->color($this->optString($b, 'color')),
            'category' => $this->optString($b, 'category'),
            'active' => $this->optInt($b, 'active'),
            'online' => ($o = $this->optInt($b, 'online')) !== null ? ($o ? 1 : 0) : null,
            'auto_confirm' => ($o = $this->optInt($b, 'auto_confirm')) !== null ? ($o ? 1 : 0) : null,
        ]);
        if ($sets) {
            $params[] = $id;
            $params[] = $this->acct;
            $this->db->exec('UPDATE services SET ' . implode(', ', $sets) . ' WHERE id = ? AND account_id = ?', $params);
        }
        return [200, ['ok' => true]];
    }

    private function deleteService(array $m, array $q, array $b): array
    {
        $this->db->exec('DELETE FROM services WHERE id = ? AND account_id = ?', [$this->id($m), $this->acct]);
        return [200, ['ok' => true]];
    }

    // ── Orari bloccati ───────────────────────────────────────────────

    private function createBlockedSlot(array $m, array $q, array $b): array
    {
        $staffId = $this->optInt($b, 'staff_id');
        if ($staffId === null) {
            throw new HttpError(400, 'Campo obbligatorio: staff_id');
        }
        $date = $this->date($this->reqString($b, 'blocked_date'), 'blocked_date');
        $start = $this->reqString($b, 'start_time');
        $end = $this->reqString($b, 'end_time');
        $reason = $this->optString($b, 'reason') ?? '';
        $allow = ($b['allow_conflict'] ?? false) === true;

        $s = toMinutes($start);
        $e = toMinutes($end);
        if ($s === null || $e === null) {
            throw new HttpError(400, 'Gli orari devono essere nel formato HH:MM');
        }
        if ($e <= $s) {
            throw new HttpError(400, 'Il blocco orario deve finire dopo l\'inizio, nello stesso giorno');
        }
        if (!$this->exists('staff', $staffId)) {
            throw new HttpError(400, 'Collaboratore non trovato');
        }
        if (!$allow) {
            $conflicts = findConflicts($start, $end, $this->busyFor($staffId, $date));
            if ($conflicts) {
                throw new HttpError(409, describeConflicts($this->staffName($staffId), $conflicts, 'block'), ['conflicts' => $conflicts]);
            }
        }
        $this->db->exec(
            'INSERT INTO blocked_slots (account_id, staff_id, blocked_date, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?, ?)',
            [$this->acct, $staffId, $date, $start, $end, $reason]
        );
        return [201, ['ok' => true]];
    }

    private function deleteBlockedSlot(array $m, array $q, array $b): array
    {
        $this->db->exec('DELETE FROM blocked_slots WHERE id = ? AND account_id = ?', [$this->id($m), $this->acct]);
        return [200, ['ok' => true]];
    }

    // ── Richieste da approvare e chiusure ───────────────────────────

    /** Richieste online in attesa, con i dati per rispondere subito dalla dashboard. */
    private function listRequests(array $m, array $q, array $b): array
    {
        $rows = $this->db->all(
            self::APT_SELECT . " WHERE a.account_id = ? AND a.status = 'requested' AND a.scheduled_date >= ? ORDER BY a.scheduled_date, a.start_time LIMIT 20",
            [$this->acct, date('Y-m-d')]
        );
        foreach ($rows as $i => $r) {
            $rows[$i]['services'] = Booking::serviceNames($this->db, (int)$r['id']);
            $rows[$i]['whatsapp'] = $this->whatsappLinks($r);
        }
        return [200, ['requests' => $rows]];
    }

    private const CLOSURE = '[Chiusura] ';

    /** Periodi di chiusura (ferie, festività) a partire da oggi, uniti per giorni consecutivi. */
    private function listClosures(array $m, array $q, array $b): array
    {
        $rows = $this->db->all(
            "SELECT DISTINCT blocked_date, reason FROM blocked_slots WHERE account_id = ? AND start_time = '00:00' AND end_time = '23:59' AND reason LIKE ? AND blocked_date >= ? ORDER BY blocked_date",
            [$this->acct, self::CLOSURE . '%', date('Y-m-d')]
        );
        $out = [];
        foreach ($rows as $r) {
            $reason = substr((string)$r['reason'], strlen(self::CLOSURE));
            $n = count($out) - 1;
            if ($n >= 0 && $out[$n]['reason'] === $reason && date('Y-m-d', strtotime($out[$n]['to'] . ' +1 day')) === $r['blocked_date']) {
                $out[$n]['to'] = $r['blocked_date'];
            } else {
                $out[] = ['from' => $r['blocked_date'], 'to' => $r['blocked_date'], 'reason' => $reason];
            }
        }
        return [200, ['closures' => $out]];
    }

    /** Chiude l'attività per uno o più giorni: nessuno potrà prenotare online in quel periodo. */
    private function createClosure(array $m, array $q, array $b): array
    {
        $from = $this->date($this->reqString($b, 'from'), 'from');
        $to = $this->date($this->optString($b, 'to') ?: $from, 'to');
        $reason = mb_substr(trim($this->optString($b, 'reason') ?? ''), 0, 100);
        if ($to < $from) {
            throw new HttpError(400, 'La data di fine deve essere uguale o dopo quella di inizio');
        }
        if ($from < date('Y-m-d')) {
            throw new HttpError(400, 'Puoi chiudere solo da oggi in poi');
        }
        if ((strtotime($to) - strtotime($from)) / 86400 > 365) {
            throw new HttpError(400, 'Il periodo di chiusura non può superare un anno');
        }
        $staff = $this->db->all('SELECT id FROM staff WHERE account_id = ? AND active = 1', [$this->acct]);
        if ($staff === []) {
            throw new HttpError(400, 'Aggiungi prima almeno un collaboratore');
        }
        $affected = $this->count("SELECT COUNT(*) AS c FROM appointments WHERE account_id = ? AND scheduled_date >= ? AND scheduled_date <= ? AND status IN ('requested', 'booked', 'confirmed')", [$this->acct, $from, $to]);
        for ($t = strtotime($from . ' 12:00:00'); date('Y-m-d', $t) <= $to; $t += 86400) {
            $day = date('Y-m-d', $t);
            if ($this->db->row("SELECT id FROM blocked_slots WHERE account_id = ? AND blocked_date = ? AND start_time = '00:00' AND end_time = '23:59' AND reason = ? LIMIT 1", [$this->acct, $day, self::CLOSURE . $reason]) !== null) {
                continue;
            }
            foreach ($staff as $st) {
                $this->db->exec(
                    "INSERT INTO blocked_slots (account_id, staff_id, blocked_date, start_time, end_time, reason) VALUES (?, ?, ?, '00:00', '23:59', ?)",
                    [$this->acct, (int)$st['id'], $day, self::CLOSURE . $reason]
                );
            }
        }
        return [201, ['ok' => true, 'affected_appointments' => $affected]];
    }

    private function deleteClosure(array $m, array $q, array $b): array
    {
        $from = $this->date((string)($q['from'] ?? ''), 'from');
        $to = $this->date((string)($q['to'] ?? $from), 'to');
        $this->db->exec(
            "DELETE FROM blocked_slots WHERE account_id = ? AND start_time = '00:00' AND end_time = '23:59' AND reason = ? AND blocked_date >= ? AND blocked_date <= ?",
            [$this->acct, self::CLOSURE . (string)($q['reason'] ?? ''), $from, $to]
        );
        return [200, ['ok' => true]];
    }

    // ── Prodotti ─────────────────────────────────────────────────────

    private function listProducts(array $m, array $q, array $b): array
    {
        [, $limit, $offset] = $this->paging($q);
        $where = 'WHERE p.account_id = ?';
        $params = [$this->acct];
        if (!empty($q['search'])) {
            $where .= " AND (p.name LIKE ? OR p.brand LIKE ? OR p.sku LIKE ?)";
            $s = $this->like((string)$q['search']);
            array_push($params, $s, $s, $s);
        }
        if (!empty($q['category'])) {
            $where .= ' AND p.category = ?';
            $params[] = (string)$q['category'];
        }
        $total = $this->count("SELECT COUNT(*) AS c FROM products p $where", $params);
        $rows = $this->db->all("SELECT * FROM products p $where ORDER BY p.name ASC LIMIT $limit OFFSET $offset", $params);
        return [200, ['products' => $rows, 'total' => $total]];
    }

    private function nonNegative(?float $v, string $field): ?float
    {
        if ($v !== null && $v < 0) {
            throw new HttpError(400, "Il campo $field non può essere negativo");
        }
        return $v;
    }

    private function nonNegativeInt(?int $v, string $field): ?int
    {
        if ($v !== null && $v < 0) {
            throw new HttpError(400, "Il campo $field non può essere negativo");
        }
        return $v;
    }

    private function createProduct(array $m, array $q, array $b): array
    {
        $this->db->exec(
            'INSERT INTO products (account_id, name, brand, category, sku, price, cost, stock, low_stock_alert) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->acct,
                $this->reqString($b, 'name'),
                $this->optString($b, 'brand') ?? '',
                $this->optString($b, 'category') ?? '',
                $this->optString($b, 'sku') ?? '',
                $this->nonNegative($this->optNumber($b, 'price'), 'price') ?? 0.0,
                $this->nonNegative($this->optNumber($b, 'cost'), 'cost') ?? 0.0,
                $this->nonNegativeInt($this->optInt($b, 'stock'), 'stock') ?? 0,
                $this->nonNegativeInt($this->optInt($b, 'low_stock_alert'), 'low_stock_alert') ?? 5,
            ]
        );
        return [201, ['product' => $this->db->row('SELECT * FROM products WHERE id = ? AND account_id = ?', [$this->db->lastId(), $this->acct])]];
    }

    private function updateProduct(array $m, array $q, array $b): array
    {
        $id = $this->id($m);
        [$sets, $params] = $this->sets([
            'name' => $this->optString($b, 'name'),
            'brand' => $this->optString($b, 'brand'),
            'category' => $this->optString($b, 'category'),
            'sku' => $this->optString($b, 'sku'),
            'price' => $this->nonNegative($this->optNumber($b, 'price'), 'price'),
            'cost' => $this->nonNegative($this->optNumber($b, 'cost'), 'cost'),
            'stock' => $this->nonNegativeInt($this->optInt($b, 'stock'), 'stock'),
            'low_stock_alert' => $this->nonNegativeInt($this->optInt($b, 'low_stock_alert'), 'low_stock_alert'),
        ]);
        if ($sets) {
            $sets[] = 'updated_at = ' . $this->db->now();
            $params[] = $id;
            $params[] = $this->acct;
            $this->db->exec('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ? AND account_id = ?', $params);
        }
        return [200, ['ok' => true]];
    }

    private function deleteProduct(array $m, array $q, array $b): array
    {
        $this->db->exec('DELETE FROM products WHERE id = ? AND account_id = ?', [$this->id($m), $this->acct]);
        return [200, ['ok' => true]];
    }
}
