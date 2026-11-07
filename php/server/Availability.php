<?php
declare(strict_types=1);

namespace App;

require_once __DIR__ . '/scheduling.php';

/** Orari liberi di un negozio: orari di lavoro dei collaboratori meno appuntamenti e blocchi. */
final class Availability
{
    /** Servizi prenotabili online. @return array<int,array<string,mixed>> */
    public static function services(Db $db, int $accountId): array
    {
        return $db->all('SELECT id, name, description, duration, price, category FROM services WHERE account_id = ? AND active = 1 AND online = 1 ORDER BY category, name', [$accountId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function staff(Db $db, int $accountId): array
    {
        return $db->all('SELECT id, name, title, color FROM staff WHERE account_id = ? AND active = 1 ORDER BY name', [$accountId]);
    }

    /**
     * Durata complessiva dei servizi scelti (solo quelli prenotabili online del negozio).
     *
     * @param array<int,mixed> $ids
     * @return array{ids:array<int,int>,duration:int,price:float}|null null se la scelta non è valida
     */
    public static function pick(Db $db, int $accountId, array $ids): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i): bool => $i > 0)));
        if ($ids === [] || count($ids) > 8) {
            return null;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = $db->all("SELECT id, duration, price FROM services WHERE account_id = ? AND active = 1 AND online = 1 AND id IN ($in)", array_merge([$accountId], $ids));
        if (count($rows) !== count($ids)) {
            return null;
        }
        $duration = array_sum(array_map(static fn($r): int => (int)$r['duration'], $rows));
        if ($duration <= 0 || $duration > 600) {
            return null;
        }
        return ['ids' => $ids, 'duration' => $duration, 'price' => (float)array_sum(array_map(static fn($r): float => (float)$r['price'], $rows))];
    }

    /** Fasce di lavoro [inizio, fine] in minuti per un collaboratore in una data. @return array<int,array{0:int,1:int}> */
    private static function ranges(Db $db, int $accountId, int $staffId, string $date): array
    {
        $weekday = (int)date('N', (int)strtotime($date . ' 12:00:00'));
        $out = [];
        foreach ($db->all('SELECT start_time, end_time FROM working_hours WHERE account_id = ? AND staff_id = ? AND weekday = ? ORDER BY start_time', [$accountId, $staffId, $weekday]) as $r) {
            $s = toMinutes((string)$r['start_time']);
            $e = toMinutes((string)$r['end_time']);
            if ($s !== null && $e !== null && $e > $s) {
                $out[] = [$s, $e];
            }
        }
        return $out;
    }

    /** Appuntamenti (anche in attesa di conferma) e blocchi del collaboratore. @return array<int,array{kind:string,start_time:string,end_time:string,label:string}> */
    public static function busy(Db $db, int $accountId, int $staffId, string $date): array
    {
        $busy = [];
        foreach ($db->all("SELECT start_time, end_time FROM appointments WHERE account_id = ? AND staff_id = ? AND scheduled_date = ? AND status <> 'cancelled'", [$accountId, $staffId, $date]) as $a) {
            $busy[] = ['kind' => 'appointment', 'start_time' => (string)$a['start_time'], 'end_time' => (string)$a['end_time'], 'label' => 'occupato'];
        }
        foreach ($db->all('SELECT start_time, end_time FROM blocked_slots WHERE account_id = ? AND staff_id = ? AND blocked_date = ?', [$accountId, $staffId, $date]) as $b) {
            $busy[] = ['kind' => 'blocked', 'start_time' => (string)$b['start_time'], 'end_time' => (string)$b['end_time'], 'label' => 'bloccato'];
        }
        return $busy;
    }

    /** La data si può prenotare (non passata, entro l'orizzonte)? @param array<string,mixed> $acct */
    public static function dateAllowed(array $acct, string $date, ?int $now = null): bool
    {
        $now ??= time();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
            return false;
        }
        $horizon = max(1, (int)($acct['booking_horizon_days'] ?? 60));
        return $date >= date('Y-m-d', $now) && $date <= date('Y-m-d', $now + $horizon * 86400);
    }

    /**
     * Orari di inizio liberi per un collaboratore.
     *
     * @param array<string,mixed> $acct
     * @return array<int,string> "HH:MM"
     */
    public static function slotsForStaff(Db $db, array $acct, int $staffId, string $date, int $duration, ?int $now = null): array
    {
        $now ??= time();
        if (!self::dateAllowed($acct, $date, $now) || $duration <= 0) {
            return [];
        }
        $accountId = (int)$acct['id'];
        $step = max(5, (int)($acct['slot_minutes'] ?? 30));
        $earliest = $now + max(0, (int)($acct['booking_notice_hours'] ?? 0)) * 3600;
        $busy = self::busy($db, $accountId, $staffId, $date);
        $out = [];
        foreach (self::ranges($db, $accountId, $staffId, $date) as [$from, $to]) {
            for ($t = $from; $t + $duration <= $to; $t += $step) {
                $start = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
                if ((int)strtotime("$date $start:00") < $earliest) {
                    continue;
                }
                if (findConflicts($start, addMinutes($start, $duration), $busy) === []) {
                    $out[] = $start;
                }
            }
        }
        return $out;
    }

    /**
     * Orari liberi del giorno: con un collaboratore preciso o, se $staffId è null, con il primo libero.
     *
     * @param array<string,mixed> $acct
     * @return array<string,int> orario → id del collaboratore
     */
    public static function slots(Db $db, array $acct, string $date, int $duration, ?int $staffId, ?int $now = null): array
    {
        $accountId = (int)$acct['id'];
        $ids = $staffId !== null ? [$staffId] : array_map(static fn($s): int => (int)$s['id'], self::staff($db, $accountId));
        $out = [];
        foreach ($ids as $id) {
            if ($staffId !== null && $db->row('SELECT id FROM staff WHERE id = ? AND account_id = ? AND active = 1', [$id, $accountId]) === null) {
                return [];
            }
            foreach (self::slotsForStaff($db, $acct, $id, $date, $duration, $now) as $time) {
                $out[$time] ??= $id;
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * Giorni del mese con almeno un orario libero.
     *
     * @param array<string,mixed> $acct
     * @return array<int,string> date "YYYY-MM-DD"
     */
    public static function days(Db $db, array $acct, string $month, int $duration, ?int $staffId, ?int $now = null): array
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $m) || (int)$m[2] < 1 || (int)$m[2] > 12) {
            return [];
        }
        $n = (int)date('t', (int)strtotime($month . '-01 12:00:00'));
        $out = [];
        for ($d = 1; $d <= $n; $d++) {
            $date = sprintf('%s-%02d', $month, $d);
            if (self::dateAllowed($acct, $date, $now) && self::slots($db, $acct, $date, $duration, $staffId, $now) !== []) {
                $out[] = $date;
            }
        }
        return $out;
    }
}
