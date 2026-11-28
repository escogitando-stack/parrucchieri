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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Demo pubblica (/esempio/api/...): stessa API, ma sul database usa e getta del visitatore.
$demo = null;
if (str_starts_with($path, App\Demo::BASE . '/api/')) {
    $demo = App\Demo::available() ? App\Demo::open() : null;
    if ($demo === null) {
        respond(401, ['error' => 'La demo è scaduta: ricarica la pagina per ricominciare.', 'code' => 'demo_expired']);
    }
    $path = substr($path, strlen(App\Demo::BASE));
    $cfgReal = $cfg;
    $cfg = App\Demo::cfg($cfg);
    $cfg['is_demo'] = true;
    $accountId = $demo[1];
} else {
    $accountId = Auth::accountId();
    if ($accountId === null) {
        respond(401, ['error' => 'Non autorizzato: effettua di nuovo l\'accesso']);
    }
}

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
    $db = $demo !== null ? $demo[0] : App\connect($cfg);
    try {   // foto consentite in demo/prova: le sceglie l'amministratore (si legge dal database vero)
        $cfg['demo_max_photos'] = App\Admin::demoMaxPhotos($demo !== null ? App\connect($cfgReal) : $db);
    } catch (Throwable $e) {
        $cfg['demo_max_photos'] = null;
    }
    $acct = Auth::account($db, $accountId);
    if ($acct === null) {
        respond(401, ['error' => 'Non autorizzato: effettua di nuovo l\'accesso']);
    }
    if (!Auth::canUse($cfg, $acct)) {
        $suspended = Auth::plan($acct)['state'] === 'suspended';
        respond(402, [
            'error' => $suspended ? 'Account sospeso.' : 'Prova gratuita terminata: attiva un abbonamento per continuare.',
            'code' => $suspended ? 'suspended' : 'subscription_required',
        ]);
    }
    $api = new App\Api($db, $accountId, $cfg);
    [$status, $data] = $api->handle($method, $path, $_GET, $body);
    if ($demo !== null && isset($data['account']) && is_array($data['account'])) {
        // Nella demo la pagina del negozio è quella di prova, non un indirizzo vero.
        $data['account']['shop_url'] = App\Demo::shopUrl($cfg);
        $data['account']['email'] = 'demo@example.com';
    }
    respond($status, $data);
} catch (Throwable $e) {
    error_log('[appuntamenti] ' . $e);
    respond(500, ['error' => 'Errore del server']);
}
