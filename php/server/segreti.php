<?php
/**
 * Cifratura a riposo dei segreti salvati nel database (chiavi API, webhook, password SMTP…).
 * La chiave (32 byte casuali) sta in config.key.php, FUORI dal database e dal repository:
 * chi ottiene solo un dump del DB (backup, SQL injection, phpMyAdmin) vede valori inutilizzabili.
 * Formato salvato: enc1:<base64(iv . tag . testo cifrato)>, AES-256-GCM.
 * I valori vecchi in chiaro continuano a funzionare e vengono cifrati automaticamente.
 * Se la chiave non si può creare (cartella non scrivibile) i valori restano in chiaro e segreto_attivo() è false.
 */
const SEGRETO_PREFISSO = 'enc1:';

function segreto_file(): string { return SEGRETI_DIR . '/config.key.php'; }

/** Chiave binaria a 32 byte, creata alla prima richiesta; null se non disponibile */
function segreto_chiave(): ?string {
    static $k = false;
    if ($k !== false) return $k;
    $k = null;
    if (!function_exists('openssl_encrypt')) return null;
    $f = segreto_file();
    $b = is_file($f) ? (string)(@include $f) : '';
    if ($b === '') {
        $nuova = base64_encode(random_bytes(32));
        $ok = false;
        if ($h = @fopen($f, 'x')) {   // 'x': non sovrascrive mai una chiave esistente
            $ok = fwrite($h, "<?php\n// Chiave di cifratura dei segreti nel database. NON cancellarla e NON metterla nel repository.\nreturn '" . $nuova . "';\n") !== false;
            fclose($h); @chmod($f, 0600);
        }
        $b = $ok ? $nuova : (is_file($f) ? (string)(@include $f) : '');
    }
    $raw = base64_decode($b, true);
    return ($raw !== false && strlen($raw) === 32) ? ($k = $raw) : null;
}
function segreto_attivo(): bool { return segreto_chiave() !== null; }
function segreto_e_cifrato(string $v): bool { return str_starts_with($v, SEGRETO_PREFISSO); }

function segreto_cifra(string $v): string {
    if ($v === '' || segreto_e_cifrato($v) || ($k = segreto_chiave()) === null) return $v;
    $iv = random_bytes(12); $tag = '';
    $c = openssl_encrypt($v, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
    return $c === false ? $v : SEGRETO_PREFISSO . base64_encode($iv . $tag . $c);
}
/** Valore in chiaro. I valori non cifrati (vecchi) tornano com'erano; se la chiave manca o non corrisponde: '' */
function segreto_decifra(string $v): string {
    if (!segreto_e_cifrato($v)) return $v;
    $k = segreto_chiave(); $b = base64_decode(substr($v, strlen(SEGRETO_PREFISSO)), true);
    if ($k === null || $b === false || strlen($b) < 29) return '';
    $p = openssl_decrypt(substr($b, 28), 'aes-256-gcm', $k, OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16));
    return $p === false ? '' : $p;
}
