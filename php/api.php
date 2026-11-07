<?php
declare(strict_types=1);

use App\Auth;

require __DIR__ . '/server/bootstrap.php';

$cfg = App\loadConfig();
App\securityHeaders();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

$accountId = Auth::accountId();
if ($accountId === null) {
    respond(401, ['error' => 'Non autorizzato: effettua di nuovo l\'accesso']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$body = null;
if ($method !== 'GET' && $method !== 'HEAD') {
    // Difesa da richieste cross-site: le scritture devono essere JSON dello stesso sito.
    $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin';
    $type = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    $hasBody = ($_SERVER['CONTENT_LENGTH'] ?? '0') !== '0';
    if (!in_array($site, ['same-origin', 'none'], true) || ($hasBody && !str_starts_with($type, 'application/json'))) {
        respond(403, ['error' => 'Richiesta non consentita']);
    }
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            respond(400, ['error' => 'JSON non valido']);
        }
    }
}

try {
    $db = App\connect($cfg);
    $acct = Auth::account($db, $accountId);
    if ($acct === null) {
        respond(401, ['error' => 'Non autorizzato: effettua di nuovo l\'accesso']);
    }
    if (Auth::plan($acct)['state'] === 'expired') {
        respond(402, ['error' => 'Prova gratuita terminata: attiva un abbonamento per continuare.', 'code' => 'subscription_required']);
    }
    $api = new App\Api($db, $accountId, $cfg);
    [$status, $data] = $api->handle($method, $path, $_GET, $body);
    respond($status, $data);
} catch (Throwable $e) {
    error_log('[appuntamenti] ' . $e);
    respond(500, ['error' => 'Errore del server']);
}
