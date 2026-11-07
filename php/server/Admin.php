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
        }
        unset($r);
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
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $pwd = '';
        for ($i = 0; $i < 12; $i++) {
            $pwd .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
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
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return true;
    }
}
