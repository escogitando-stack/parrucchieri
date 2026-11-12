<?php
declare(strict_types=1);

namespace App;

/**
 * Attività automatiche, da far partire con cron.php (per esempio ogni ora):
 * promemoria ai clienti, riepilogo serale ai titolari, email di fine prova e di configurazione.
 * Ogni invio avviene una sola volta (stato salvato nel database).
 */
final class Automation
{
    /**
     * @param array<string,mixed> $cfg
     * @return array{reminders:int,summaries:int,trial:int,onboarding:int}
     */
    public static function run(Db $db, array $cfg, ?int $now = null): array
    {
        $now ??= time();
        return [
            'reminders' => self::reminders($db, $cfg, $now),
            'summaries' => self::summaries($db, $cfg, $now),
            'trial' => self::trialMails($db, $cfg, $now),
            'onboarding' => self::onboarding($db, $cfg, $now),
            'reviews' => self::reviewRequests($db, $cfg, $now),
        ];
    }

    private static function hourReached(array $cfg, string $key, int $default, int $now): bool
    {
        return (int)date('G', $now) >= (int)($cfg[$key] ?? $default);
    }

    /** @param array<string,mixed> $acct */
    private static function active(array $cfg, array $acct): bool
    {
        return Auth::canUse($cfg, $acct) && Auth::plan($acct)['state'] !== 'pending';
    }

    /** Promemoria del giorno prima (dalle 17, o da 'reminder_hour'). */
    private static function reminders(Db $db, array $cfg, int $now): int
    {
        if (!self::hourReached($cfg, 'reminder_hour', 17, $now)) {
            return 0;
        }
        $tomorrow = date('Y-m-d', $now + 86400);
        $rows = $db->all(
            "SELECT a.*, c.name AS client_name, c.email AS client_email, s.name AS staff_name FROM appointments a
             JOIN clients c ON c.id = a.client_id LEFT JOIN staff s ON s.id = a.staff_id
             WHERE a.scheduled_date = ? AND a.status IN ('confirmed', 'booked') AND a.reminded = 0 AND c.email <> ''
             ORDER BY a.id LIMIT 300",
            [$tomorrow]
        );
        $sent = 0;
        $accounts = [];
        foreach ($rows as $apt) {
            $aid = (int)$apt['account_id'];
            $accounts[$aid] ??= Auth::account($db, $aid);
            $acct = $accounts[$aid];
            if ($acct === null || !self::active($cfg, $acct)) {
                continue;
            }
            if (Notify::reminder($cfg, $acct, $apt, ['name' => $apt['client_name'], 'email' => $apt['client_email']], Booking::serviceNames($db, (int)$apt['id']))) {
                $db->exec('UPDATE appointments SET reminded = 1 WHERE id = ?', [(int)$apt['id']]);
                $sent++;
            }
        }
        return $sent;
    }

    /** Riepilogo serale (dalle 20, o da 'summary_hour'), solo se c'è qualcosa da dire. */
    private static function summaries(Db $db, array $cfg, int $now): int
    {
        if (!self::hourReached($cfg, 'summary_hour', 20, $now)) {
            return 0;
        }
        $today = date('Y-m-d', $now);
        $tomorrow = date('Y-m-d', $now + 86400);
        $sent = 0;
        foreach ($db->all('SELECT * FROM accounts WHERE suspended = 0 AND approved = 1 ORDER BY id') as $acct) {
            $aid = (int)$acct['id'];
            if (!self::active($cfg, $acct) || $db->metaGet('nosummary_' . $aid) === '1' || $db->metaGet('summary_' . $aid) === $today) {
                continue;
            }
            $rows = $db->all(
                "SELECT a.id, a.start_time, c.name AS client_name, c.phone AS client_phone, s.name AS staff_name FROM appointments a
                 JOIN clients c ON c.id = a.client_id LEFT JOIN staff s ON s.id = a.staff_id
                 WHERE a.account_id = ? AND a.scheduled_date = ? AND a.status IN ('confirmed', 'booked') ORDER BY a.start_time",
                [$aid, $tomorrow]
            );
            $pending = (int)$db->row("SELECT COUNT(*) AS c FROM appointments WHERE account_id = ? AND status = 'requested' AND scheduled_date >= ?", [$aid, $today])['c'];
            if ($rows === [] && $pending === 0) {
                continue;
            }
            foreach ($rows as $i => $r) {
                $rows[$i]['services'] = Booking::serviceNames($db, (int)$r['id']);
            }
            $db->metaSet('summary_' . $aid, $today);
            if (Notify::dailySummary($cfg, $acct, $tomorrow, $rows, $pending)) {
                $sent++;
            }
        }
        return $sent;
    }

    /** Fine prova: avviso a 7 giorni (stage 1), a 2 giorni (stage 2), alla scadenza (stage 3). */
    private static function trialMails(Db $db, array $cfg, int $now): int
    {
        $sent = 0;
        foreach ($db->all("SELECT * FROM accounts WHERE suspended = 0 AND approved = 1 AND plan_status = 'trial' ORDER BY id") as $acct) {
            if (Auth::isAdmin($cfg, $acct)) {
                continue;
            }
            $plan = Auth::plan($acct, $now);
            $stage = (int)$acct['trial_mail_stage'];
            $next = $stage;
            if ($plan['state'] === 'trial') {
                if ($plan['days_left'] <= 2 && $stage < 2) {
                    $next = 2;
                } elseif ($plan['days_left'] <= 7 && $stage < 1) {
                    $next = 1;
                }
            } elseif ($plan['state'] === 'expired' && $stage < 3) {
                $next = 3;
            }
            if ($next === $stage) {
                continue;
            }
            $db->exec('UPDATE accounts SET trial_mail_stage = ? WHERE id = ?', [$next, (int)$acct['id']]);
            if ($next === 3 ? Notify::trialExpired($cfg, $acct) : Notify::trialEnding($cfg, $acct, $plan['days_left'])) {
                $sent++;
            }
        }
        return $sent;
    }

    /** Dopo 2 giorni, se il profilo è incompleto, un'email con cosa manca (una volta sola). */
    private static function onboarding(Db $db, array $cfg, int $now): int
    {
        $sent = 0;
        $limit = date('Y-m-d H:i:s', $now - 2 * 86400);
        foreach ($db->all("SELECT * FROM accounts WHERE onboard_mail = 0 AND suspended = 0 AND approved = 1 AND created_at <= ? ORDER BY id", [$limit]) as $acct) {
            $db->exec('UPDATE accounts SET onboard_mail = 1 WHERE id = ?', [(int)$acct['id']]);
            if (Auth::isAdmin($cfg, $acct) || !self::active($cfg, $acct)) {
                continue;
            }
            $missing = [];
            if (trim((string)$acct['shop_phone']) === '' && trim((string)$acct['shop_whatsapp']) === '') {
                $missing[] = 'un numero di telefono o WhatsApp da mostrare ai clienti';
            }
            if (trim((string)$acct['shop_address']) === '') {
                $missing[] = 'l\'indirizzo del negozio';
            }
            if (($acct['logo_mime'] ?? '') === '') {
                $missing[] = 'il logo';
            }
            if (trim((string)$acct['shop_description']) === '') {
                $missing[] = 'una breve descrizione';
            }
            if ($missing !== [] && Notify::onboarding($cfg, $acct, $missing)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Richiesta di recensione (dalle 10, o da 'review_hour') ai clienti con un appuntamento completato
     * negli ultimi 3 giorni, se il negozio ha impostato il link per le recensioni. Una sola volta per appuntamento.
     */
    private static function reviewRequests(Db $db, array $cfg, int $now): int
    {
        if (!self::hourReached($cfg, 'review_hour', 10, $now)) {
            return 0;
        }
        $rows = $db->all(
            "SELECT a.id, a.account_id, a.client_id, c.name AS client_name, c.email AS client_email FROM appointments a
             JOIN clients c ON c.id = a.client_id JOIN accounts acc ON acc.id = a.account_id
             WHERE a.status = 'completed' AND a.review_asked = 0 AND a.scheduled_date >= ? AND a.scheduled_date <= ?
               AND c.email <> '' AND acc.review_url <> ''
             ORDER BY a.id LIMIT 300",
            [date('Y-m-d', $now - 3 * 86400), date('Y-m-d', $now)]
        );
        $sent = 0;
        $asked = [];
        $accounts = [];
        foreach ($rows as $r) {
            $db->exec('UPDATE appointments SET review_asked = 1 WHERE id = ?', [(int)$r['id']]);
            $aid = (int)$r['account_id'];
            $accounts[$aid] ??= Auth::account($db, $aid);
            $acct = $accounts[$aid];
            // Un solo messaggio per cliente, anche se ha più appuntamenti completati.
            if ($acct === null || !self::active($cfg, $acct) || isset($asked[(int)$r['client_id']]) || $db->metaGet('demo_of_' . $aid) !== null) {
                continue;
            }
            $asked[(int)$r['client_id']] = true;
            if (Notify::reviewRequest($cfg, $acct, ['name' => $r['client_name'], 'email' => $r['client_email']])) {
                $sent++;
            }
        }
        return $sent;
    }
}
