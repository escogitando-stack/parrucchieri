<?php
declare(strict_types=1);

namespace App;

/**
 * Invio email. Di norma usa mail() del server (su cPanel funziona senza altro).
 * In config.php: 'mail' => ['from' => 'no-reply@appuntamenti4me.it', 'driver' => 'mail'].
 * Per un server SMTP (consigliato per la consegna): 'driver' => 'smtp', 'host' => 'mail.tuodominio.it',
 * 'port' => 587, 'secure' => 'tls' ('tls' = STARTTLS, 'ssl' = porta 465, 'none'), 'user' => '...', 'pass' => '...'.
 * Con driver 'array' (test) i messaggi non partono: finiscono in Mailer::$sent.
 */
final class Mailer
{
    /** @var array<int,array{to:string,subject:string,text:string,html:string,reply_to:string}> */
    public static array $sent = [];

    /** Forza un driver (usato dai test). */
    public static ?string $driver = null;

    /** Toglie a capo e caratteri di controllo: impedisce di aggiungere intestazioni false. */
    private static function line(string $s): string
    {
        return trim((string)preg_replace('/[\r\n\x00-\x1f]+/', ' ', $s));
    }

    /** @param array<string,mixed> $cfg */
    public static function from(array $cfg): string
    {
        $from = (string)($cfg['mail']['from'] ?? '');
        if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }
        $host = (string)parse_url((string)($cfg['base_url'] ?? ''), PHP_URL_HOST);
        if ($host === '') {
            $host = preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }
        return 'no-reply@' . $host;
    }

    /**
     * @param array<string,mixed> $cfg
     * @return bool true se l'email è stata passata al server di posta
     */
    public static function send(array $cfg, string $to, string $subject, string $text, string $html = '', string $replyTo = '', string $fromName = ''): bool
    {
        $to = self::line($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $replyTo = filter_var(self::line($replyTo), FILTER_VALIDATE_EMAIL) ? self::line($replyTo) : '';
        $subject = self::line($subject);
        if ((self::$driver ?? ($cfg['mail']['driver'] ?? 'mail')) === 'array') {
            self::$sent[] = ['to' => $to, 'subject' => $subject, 'text' => $text, 'html' => $html, 'reply_to' => $replyTo];
            return true;
        }
        $name = self::line($fromName !== '' ? $fromName : (string)($cfg['site_name'] ?? 'Appuntamenti4me'));
        $headers = ['MIME-Version: 1.0', 'From: =?UTF-8?B?' . base64_encode($name) . '?= <' . self::from($cfg) . '>'];
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        if ($html === '') {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = rtrim(chunk_split(base64_encode($text), 76, "\r\n"));
        } else {
            $b = 'b' . bin2hex(random_bytes(8));
            $headers[] = "Content-Type: multipart/alternative; boundary=\"$b\"";
            $enc = static fn(string $t): string => rtrim(chunk_split(base64_encode($t), 76, "\r\n"));
            $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($text) . "\r\n--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($html) . "\r\n--$b--";
        }
        if ((self::$driver ?? ($cfg['mail']['driver'] ?? 'mail')) === 'smtp') {
            $error = '';
            $ok = self::smtp($cfg, $to, $subject, $headers, $body, $error);
            if (!$ok) {
                error_log('[appuntamenti] smtp verso ' . $to . ': ' . $error);
            }
            return $ok;
        }
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . self::from($cfg));
        if (!$ok) {
            error_log('[appuntamenti] invio email non riuscito verso ' . $to);
        }
        return $ok;
    }

    /** Ultima risposta di un server SMTP (anche su più righe). @param resource $fp */
    private static function reply($fp, string &$error): ?int
    {
        $code = null;
        while (($line = fgets($fp, 1024)) !== false) {
            $code = (int)substr($line, 0, 3);
            if (strlen($line) < 4 || $line[3] !== '-') {
                return $code;
            }
        }
        $error = 'connessione interrotta';
        return null;
    }

    /** @param resource $fp @return bool true se il server risponde col codice atteso */
    private static function cmd($fp, string $line, array $expect, string &$error): bool
    {
        if ($line !== '' && fwrite($fp, $line . "\r\n") === false) {
            $error = 'scrittura non riuscita';
            return false;
        }
        $code = self::reply($fp, $error);
        if ($code === null || !in_array($code, $expect, true)) {
            $error = $error !== '' ? $error : 'risposta inattesa ' . $code . ' a "' . (str_starts_with($line, 'AUTH') ? 'AUTH' : substr($line, 0, 20)) . '"';
            return false;
        }
        return true;
    }

    /**
     * Invio diretto a un server SMTP. @param array<string,mixed> $cfg @param array<int,string> $headers
     */
    private static function smtp(array $cfg, string $to, string $subject, array $headers, string $body, string &$error): bool
    {
        $m = (array)($cfg['mail'] ?? []);
        $host = (string)($m['host'] ?? '');
        $secure = (string)($m['secure'] ?? 'tls');
        $port = (int)($m['port'] ?? ($secure === 'ssl' ? 465 : 587));
        if ($host === '') {
            $error = 'host SMTP mancante';
            return false;
        }
        $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
        if ($fp === false) {
            $error = "connessione a $host:$port non riuscita ($errstr)";
            return false;
        }
        stream_set_timeout($fp, 20);
        try {
            $me = preg_replace('/[^a-z0-9.-]/i', '', (string)parse_url((string)($cfg['base_url'] ?? ''), PHP_URL_HOST)) ?: 'localhost';
            if (self::reply($fp, $error) !== 220 || !self::cmd($fp, 'EHLO ' . $me, [250], $error)) {
                return false;
            }
            if ($secure === 'tls') {
                if (!self::cmd($fp, 'STARTTLS', [220], $error) || !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    $error = $error ?: 'STARTTLS non riuscito';
                    return false;
                }
                if (!self::cmd($fp, 'EHLO ' . $me, [250], $error)) {
                    return false;
                }
            }
            $user = (string)($m['user'] ?? '');
            if ($user !== '') {
                if (!self::cmd($fp, 'AUTH LOGIN', [334], $error) || !self::cmd($fp, base64_encode($user), [334], $error) || !self::cmd($fp, base64_encode((string)($m['pass'] ?? '')), [235], $error)) {
                    $error = 'autenticazione rifiutata: ' . $error;
                    return false;
                }
            }
            $from = self::from($cfg);
            $head = array_merge($headers, [
                'To: ' . $to,
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'Date: ' . date('r'),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
            ]);
            $data = implode("\r\n", $head) . "\r\n\r\n" . $body;
            $data = preg_replace('/(?<!\r)\n/', "\r\n", $data) ?? $data;
            $data = preg_replace('/^\./m', '..', $data) ?? $data;
            if (!self::cmd($fp, 'MAIL FROM:<' . $from . '>', [250], $error) || !self::cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251], $error) || !self::cmd($fp, 'DATA', [354], $error)) {
                return false;
            }
            fwrite($fp, $data . "\r\n.\r\n");
            $ok = self::cmd($fp, '', [250], $error);
            $quit = '';
            self::cmd($fp, 'QUIT', [221], $quit);
            return $ok;
        } finally {
            fclose($fp);
        }
    }
}
