<?php
declare(strict_types=1);

namespace App;

/** Account dei negozi: registrazione, accesso, sessione e stato dell'abbonamento. */
final class Auth
{
    public const MIN_PASSWORD = 8;

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $lifetime = 60 * 60 * 24 * 30;
        ini_set('session.gc_maxlifetime', (string)$lifetime);
        ini_set('session.use_strict_mode', '1');
        session_name('appuntamenti');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Id del negozio con la sessione aperta, oppure null. */
    public static function accountId(): ?int
    {
        self::start();
        $id = $_SESSION['account_id'] ?? null;
        return is_int($id) && $id > 0 ? $id : null;
    }

    public static function isLoggedIn(): bool
    {
        return self::accountId() !== null;
    }

    private static function openSession(int $accountId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION = ['account_id' => $accountId];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], true);
        }
        session_destroy();
    }

    /** @return array<string,mixed>|null */
    public static function account(Db $db, int $id): ?array
    {
        return $db->row('SELECT * FROM accounts WHERE id = ?', [$id]);
    }

    /**
     * Prova gratuita o abbonamento: 'trial', 'active', 'expired' oppure 'suspended' (bloccato dall'amministratore).
     *
     * @param array<string,mixed> $acct
     * @return array{state:string,days_left:int,ends_at:string}
     */
    public static function plan(array $acct, ?int $now = null): array
    {
        $now ??= time();
        if (!empty($acct['suspended'])) {
            return ['state' => 'suspended', 'days_left' => 0, 'ends_at' => ''];
        }
        $grace = 3 * 86400; // tolleranza se il rinnovo arriva in ritardo
        $status = (string)($acct['plan_status'] ?? 'trial');
        $paidUntil = (string)($acct['paid_until'] ?? '');
        $trialEnd = (string)($acct['trial_ends_at'] ?? '');
        if ($status === 'active') {
            $end = $paidUntil !== '' ? (int)strtotime($paidUntil) : PHP_INT_MAX - $grace;
            if ($now <= $end + $grace) {
                return ['state' => 'active', 'days_left' => 0, 'ends_at' => $paidUntil];
            }
        } elseif ($status === 'trial' && $trialEnd !== '') {
            $end = (int)strtotime($trialEnd);
            if ($now <= $end) {
                return ['state' => 'trial', 'days_left' => (int)ceil(($end - $now) / 86400), 'ends_at' => $trialEnd];
            }
        }
        return ['state' => 'expired', 'days_left' => 0, 'ends_at' => $status === 'active' ? $paidUntil : $trialEnd];
    }

    /** @param array<string,mixed> $cfg @param array<string,mixed> $acct */
    public static function isAdmin(array $cfg, array $acct): bool
    {
        $admins = array_map(static fn($e) => strtolower(trim((string)$e)), array_merge((array)($cfg['admin_emails'] ?? []), [(string)($cfg['admin_email'] ?? '')]));
        $admins = array_filter($admins, static fn($e) => $e !== '');
        return in_array(strtolower((string)($acct['email'] ?? '')), $admins, true);
    }

    /** Può usare l'agenda? Gli amministratori sì, sempre (non pagano il proprio accesso). */
    public static function canUse(array $cfg, array $acct): bool
    {
        if (self::isAdmin($cfg, $acct) && empty($acct['suspended'])) {
            return true;
        }
        return in_array(self::plan($acct)['state'], ['trial', 'active'], true);
    }

    /**
     * Crea il negozio, avvia la prova e apre la sessione.
     *
     * @param array<string,mixed> $cfg
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function register(Db $db, array $cfg, string $business, string $category, string $email, string $password): array
    {
        $business = trim($business);
        $email = strtolower(trim($email));
        if ($business === '' || mb_strlen($business) > 120) {
            return ['ok' => false, 'error' => 'Inserisci il nome della tua attività.'];
        }
        if (!Categories::valid($category)) {
            return ['ok' => false, 'error' => 'Scegli il tipo di attività.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            return ['ok' => false, 'error' => 'Inserisci un indirizzo email valido.'];
        }
        if (strlen($password) < self::MIN_PASSWORD) {
            return ['ok' => false, 'error' => 'La password deve avere almeno ' . self::MIN_PASSWORD . ' caratteri.'];
        }
        if ($db->row('SELECT id FROM accounts WHERE email = ?', [$email]) !== null) {
            return ['ok' => false, 'error' => 'Esiste già un account con questa email. Prova ad accedere.'];
        }

        $days = max(1, (int)($cfg['trial_days'] ?? 30));
        $first = $db->row('SELECT id FROM accounts LIMIT 1') === null;
        $db->exec(
            'INSERT INTO accounts (email, password_hash, business_name, category, plan_status, slug, trial_ends_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$email, password_hash($password, PASSWORD_DEFAULT), $business, $category, 'trial', Booking::makeSlug($db, $business), date('Y-m-d H:i:s', time() + $days * 86400)]
        );
        $id = $db->lastId();
        // Il primo negozio eredita i dati inseriti prima dell'arrivo degli account.
        if ($first && Schema::hasUnownedData($db)) {
            Schema::adoptUnownedData($db, $id);
        } else {
            Schema::seedAccount($db, $id, $category);
        }
        self::openSession($id);
        $db->exec('UPDATE accounts SET last_login_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $id]);
        return ['ok' => true, 'id' => $id];
    }

    /**
     * Amministratore definito in config.php (admin_email + admin_password o admin_password_hash):
     * al primo accesso corretto l'account viene creato, poi la password del config resta valida.
     */
    private static function configAdminMatches(array $cfg, string $email, string $password): bool
    {
        $adminEmail = strtolower(trim((string)($cfg['admin_email'] ?? '')));
        if ($adminEmail === '' || strtolower(trim($email)) !== $adminEmail) {
            return false;
        }
        $plain = (string)($cfg['admin_password'] ?? '');
        $hash = (string)($cfg['admin_password_hash'] ?? '');
        if ($plain !== '' && hash_equals($plain, $password)) {
            return true;
        }
        return $hash !== '' && password_verify($password, $hash);
    }

    /** @return array{ok:bool,id?:int} */
    public static function login(Db $db, string $email, string $password, array $cfg = []): array
    {
        if (self::configAdminMatches($cfg, $email, $password)) {
            $e = strtolower(trim($email));
            $acct = $db->row('SELECT id, password_hash FROM accounts WHERE email = ?', [$e]);
            if ($acct === null) {
                $r = self::register($db, ['allow_registration' => true, 'trial_days' => 30], 'Amministrazione', 'altro', $e, $password);
                return $r['ok'] ? ['ok' => true, 'id' => (int)$r['id']] : ['ok' => false];
            }
            // La password del config è quella valida: tiene allineato l'account.
            if (!password_verify($password, (string)$acct['password_hash'])) {
                $db->exec('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int)$acct['id']]);
            }
        }
        $acct = $db->row('SELECT id, password_hash FROM accounts WHERE email = ?', [strtolower(trim($email))]);
        // Si verifica sempre un hash, anche se l'email non esiste, per non far capire chi è registrato.
        $hash = $acct['password_hash'] ?? '$2y$10$QtfLZrm/LZSWtIGfsvpmceT9nDJ6.nhWgsNBqAkSqQfD2TSDeMx9W';
        if (!password_verify($password, (string)$hash) || $acct === null) {
            sleep(1); // rallenta i tentativi a raffica
            return ['ok' => false];
        }
        self::openSession((int)$acct['id']);
        $db->exec('UPDATE accounts SET last_login_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), (int)$acct['id']]);
        return ['ok' => true, 'id' => (int)$acct['id']];
    }

    /** @return array{ok:bool,error?:string} */
    public static function changePassword(Db $db, int $accountId, string $current, string $new): array
    {
        $acct = self::account($db, $accountId);
        if ($acct === null || !password_verify($current, (string)$acct['password_hash'])) {
            sleep(1);
            return ['ok' => false, 'error' => 'La password attuale non è corretta.'];
        }
        if (strlen($new) < self::MIN_PASSWORD) {
            return ['ok' => false, 'error' => 'La nuova password deve avere almeno ' . self::MIN_PASSWORD . ' caratteri.'];
        }
        $db->exec('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $accountId]);
        return ['ok' => true];
    }
}
