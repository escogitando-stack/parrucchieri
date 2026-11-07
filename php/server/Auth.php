<?php
declare(strict_types=1);

namespace App;

/** Accesso protetto da password (una sola utenza amministratore). */
final class Auth
{
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
        session_name('parrucchieri');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function isLoggedIn(): bool
    {
        self::start();
        return ($_SESSION['auth'] ?? false) === true;
    }

    /** @param array<string,mixed> $cfg */
    public static function attempt(array $cfg, string $password): bool
    {
        $hash = trim((string)($cfg['admin_password_hash'] ?? ''));
        $plain = (string)($cfg['admin_password'] ?? '');
        $ok = false;
        if ($hash !== '') {
            $ok = password_verify($password, $hash);
        } elseif ($plain !== '') {
            $ok = hash_equals($plain, $password);
        }
        if (!$ok) {
            sleep(1); // rallenta i tentativi a raffica
            return false;
        }
        self::start();
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        return true;
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

    public static function loginPage(?string $error = null, bool $configured = true): string
    {
        $err = $error ? '<p class="err">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';
        $warn = $configured ? '' : '<p class="err">Password amministratore non configurata: imposta <code>admin_password</code> in <code>server/config.php</code>.</p>';
        return <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Accedi · Gestionale Parrucchieri</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f6f4f3;font:16px/1.4 system-ui,-apple-system,"Segoe UI",sans-serif;color:#1c1917}
  form{width:min(92vw,360px);background:#fff;border:1px solid #e7e5e4;border-radius:14px;padding:28px;box-shadow:0 6px 24px rgba(0,0,0,.06)}
  h1{margin:0 0 4px;font-size:20px}
  p.sub{margin:0 0 20px;color:#78716c;font-size:14px}
  label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}
  input{width:100%;height:44px;padding:0 12px;border:1px solid #d6d3d1;border-radius:10px;font-size:16px}
  input:focus{outline:2px solid #ec4899;outline-offset:1px}
  button{margin-top:16px;width:100%;height:44px;border:0;border-radius:10px;background:#be185d;color:#fff;font-size:16px;font-weight:600;cursor:pointer}
  button:hover{background:#9d174d}
  .err{margin:0 0 14px;padding:10px 12px;border-radius:8px;background:#fef2f2;color:#b91c1c;font-size:14px}
</style>
</head>
<body>
<form method="post" action="/login">
  <h1>Gestionale Parrucchieri</h1>
  <p class="sub">Inserisci la password per accedere all'agenda.</p>
  $warn$err
  <label for="password">Password</label>
  <input id="password" name="password" type="password" autocomplete="current-password" autofocus required>
  <button type="submit">Accedi</button>
</form>
</body>
</html>
HTML;
    }
}
