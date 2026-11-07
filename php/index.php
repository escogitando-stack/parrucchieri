<?php
declare(strict_types=1);

use App\Auth;
use App\Admin;
use App\Billing;
use App\Pages;

require __DIR__ . '/server/bootstrap.php';

$cfg = App\loadConfig();
App\securityHeaders();
$path = rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
header('Cache-Control: no-store');

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function baseUrl(array $cfg): string
{
    if (!empty($cfg['base_url'])) {
        return rtrim((string)$cfg['base_url'], '/');
    }
    return (Auth::isHttps() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// I moduli devono arrivare dal nostro sito (il webhook di Stripe non è un modulo).
if ($method === 'POST' && $path !== '/stripe-webhook' && ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin') === 'cross-site') {
    http_response_code(403);
    exit('Richiesta non consentita');
}

$db = App\connect($cfg);

// Notifiche di pagamento da Stripe.
if ($path === '/stripe-webhook') {
    $payload = (string)file_get_contents('php://input');
    $secret = (string)($cfg['stripe']['webhook_secret'] ?? '');
    if ($method !== 'POST' || $secret === '' || !Billing::verifySignature($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $secret)) {
        http_response_code(400);
        exit('Firma non valida');
    }
    $event = json_decode($payload, true);
    if (is_array($event)) {
        try {
            Billing::handleEvent($db, $event);
        } catch (Throwable $e) {
            error_log('[appuntamenti] webhook: ' . $e);
            http_response_code(500);
            exit('Errore');
        }
    }
    http_response_code(200);
    exit('ok');
}

if ($path === '/logout') {
    Auth::logout();
    redirect('/');
}

$accountId = Auth::accountId();

if ($path === '/login') {
    if ($accountId !== null) {
        redirect('/');
    }
    if ($method === 'POST') {
        $email = (string)($_POST['email'] ?? '');
        if (Auth::login($db, $email, (string)($_POST['password'] ?? ''), $cfg)['ok']) {
            $next = (string)($_SESSION['after_login'] ?? '');
            unset($_SESSION['after_login']);
            redirect($next === '/admin' ? '/admin' : '/');
        }
        http_response_code(401);
        echo Pages::login($cfg, 'Email o password non corrette.', $email);
        exit;
    }
    echo Pages::login($cfg);
    exit;
}

if ($path === '/contatti') {
    echo Pages::contact($cfg);
    exit;
}

if ($path === '/registrati' || $path === '/register') {
    if ($accountId !== null) {
        redirect('/');
    }
    if (!($cfg['allow_registration'] ?? true)) {
        http_response_code(403);
        echo Pages::closed($cfg);
        exit;
    }
    if ($method === 'POST') {
        $old = [
            'business' => (string)($_POST['business'] ?? ''),
            'category' => (string)($_POST['category'] ?? ''),
            'email' => (string)($_POST['email'] ?? ''),
        ];
        $needsTerms = !empty($cfg['terms_url']) || !empty($cfg['privacy_url']);
        if (($_POST['website'] ?? '') !== '') {
            $result = ['ok' => false, 'error' => 'Registrazione non riuscita.']; // campo trappola per i bot
        } elseif ($needsTerms && empty($_POST['terms'])) {
            $result = ['ok' => false, 'error' => 'Per continuare devi accettare i termini.'];
        } else {
            $result = Auth::register($db, $cfg, $old['business'], $old['category'], $old['email'], (string)($_POST['password'] ?? ''));
        }
        if ($result['ok']) {
            redirect('/');
        }
        http_response_code(400);
        echo Pages::register($cfg, $result['error'] ?? 'Registrazione non riuscita.', $old);
        exit;
    }
    echo Pages::register($cfg);
    exit;
}

if ($accountId === null) {
    if ($path === '/admin') {
        $_SESSION['after_login'] = '/admin';
        redirect('/login');
    }
    if ($path === '/') {
        echo Pages::landing($cfg);
        exit;
    }
    redirect('/login');
}

$acct = Auth::account($db, $accountId);
if ($acct === null) {
    Auth::logout();
    redirect('/login');
}
$plan = Auth::plan($acct);

// Pannello amministratore (solo per le email elencate in config.php → admin_emails).
if ($path === '/admin') {
    if (!Auth::isAdmin($cfg, $acct)) {
        http_response_code(404);
        exit('Non trovato');
    }
    $notice = null;
    $error = null;
    if ($method === 'POST') {
        if (!Admin::checkCsrf((string)($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            exit('Richiesta non valida: ricarica la pagina e riprova.');
        }
        $target = (int)($_POST['id'] ?? 0);
        $row = Auth::account($db, $target);
        $name = Pages::h((string)($row['business_name'] ?? ''));
        $action = (string)($_POST['azione'] ?? '');
        if ($row === null) {
            $error = 'Negozio non trovato.';
        } elseif ($action === 'extend' && Admin::extend($db, $target, (int)($_POST['days'] ?? 0))) {
            $notice = "Prova prolungata per <b>$name</b>.";
        } elseif ($action === 'activate' && Admin::activate($db, $target, (string)($_POST['until'] ?? ''))) {
            $notice = "<b>$name</b> attivato fino al " . Pages::h(date('d/m/Y', (int)strtotime((string)$_POST['until']))) . '.';
        } elseif ($action === 'suspend') {
            $suspend = empty($row['suspended']);
            if ($target === $accountId) {
                $error = 'Non puoi sospendere il tuo stesso account.';
            } else {
                Admin::setSuspended($db, $target, $suspend);
                $notice = "<b>$name</b> " . ($suspend ? 'sospeso.' : 'riattivato.');
            }
        } elseif ($action === 'reset') {
            $pwd = Admin::resetPassword($db, $target);
            $notice = "Nuova password per <b>$name</b> (" . Pages::h((string)$row['email']) . '): <code class="pw">' . Pages::h((string)$pwd) . '</code> Comunicala al cliente: potrà cambiarla dalle Impostazioni.';
        } elseif ($action === 'delete') {
            if ($target === $accountId) {
                $error = 'Non puoi eliminare il tuo stesso account.';
            } else {
                Admin::delete($db, $target);
                $notice = "Account <b>$name</b> eliminato insieme a tutti i suoi dati.";
            }
        } else {
            $error = 'Operazione non riuscita: controlla i valori inseriti.';
        }
    }
    $all = Admin::rows($db);
    $stats = Admin::stats($all);
    $q = trim((string)($_GET['q'] ?? ''));
    $state = (string)($_GET['stato'] ?? '');
    $filtered = array_values(array_filter($all, static function (array $r) use ($q, $state): bool {
        if ($state !== '' && $r['plan']['state'] !== $state) {
            return false;
        }
        return $q === '' || stripos((string)$r['business_name'], $q) !== false || stripos((string)$r['email'], $q) !== false;
    }));
    $perPage = 25;
    $pages = max(1, (int)ceil(count($filtered) / $perPage));
    $page = min($pages, max(1, (int)($_GET['pagina'] ?? 1)));
    echo Pages::admin(
        $cfg,
        array_slice($filtered, ($page - 1) * $perPage, $perPage),
        $stats,
        ['q' => $q, 'state' => $state, 'page' => $page, 'pages' => $pages, 'shown' => count($filtered)],
        Admin::csrfToken(),
        $accountId,
        $notice,
        $error
    );
    exit;
}

// Abbonamento: visibile anche a prova scaduta.
if ($path === '/abbonamento' || $path === '/abbonamento/acquista' || $path === '/abbonamento/gestisci') {
    $error = null;
    if ($method === 'POST') {
        try {
            if (!Billing::enabled($cfg)) {
                throw new RuntimeException('Pagamenti non configurati');
            }
            if ($path === '/abbonamento/acquista') {
                $piano = ($_POST['piano'] ?? '') === 'annuale' ? 'annuale' : 'mensile';
                redirect(Billing::checkoutUrl($cfg, $acct, $piano, baseUrl($cfg)));
            }
            if (empty($acct['stripe_customer_id'])) {
                throw new RuntimeException('Nessun abbonamento da gestire');
            }
            redirect(Billing::portalUrl($cfg, $acct, baseUrl($cfg)));
        } catch (RuntimeException $e) {
            error_log('[appuntamenti] ' . $e->getMessage());
            $error = 'Non è stato possibile aprire la pagina di pagamento. Riprova tra poco.';
        }
    }
    $notice = null;
    if (($_GET['esito'] ?? '') === 'ok') {
        $notice = $plan['state'] === 'active'
            ? 'Grazie! Il pagamento è andato a buon fine.'
            : 'Grazie! Stiamo confermando il pagamento: se la pagina non si aggiorna, ricaricala tra qualche istante.';
    } elseif (($_GET['esito'] ?? '') === 'annullato') {
        $error = 'Pagamento annullato: non è stato addebitato nulla.';
    }
    echo Pages::billing($cfg, $acct, $plan, $notice, $error);
    exit;
}

if (!Auth::canUse($cfg, $acct)) {
    redirect('/abbonamento');
}

$app = __DIR__ . '/app.html';
if (!is_file($app)) {
    http_response_code(500);
    echo 'Interfaccia non trovata (app.html): il deploy non è completo.';
    exit;
}
// Le rotte dell'app (/calendar, /clients/3, ...) servono tutte la stessa pagina.
header('Content-Type: text/html; charset=utf-8');
readfile($app);
