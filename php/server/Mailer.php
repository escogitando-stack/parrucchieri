<?php
declare(strict_types=1);

namespace App;

/**
 * Invio email. Di norma usa mail() del server (su cPanel funziona senza altro).
 * In config.php: 'mail' => ['from' => 'no-reply@appuntamenti4me.it', 'driver' => 'mail'].
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
            $body = $text;
        } else {
            $b = 'b' . bin2hex(random_bytes(8));
            $headers[] = "Content-Type: multipart/alternative; boundary=\"$b\"";
            $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n$text\r\n--$b\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n$html\r\n--$b--";
        }
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . self::from($cfg));
        if (!$ok) {
            error_log('[appuntamenti] invio email non riuscito verso ' . $to);
        }
        return $ok;
    }
}
