<?php
declare(strict_types=1);

use App\Auth;
use App\Admin;
use App\Billing;
use App\Pages;
use App\Booking;
use App\Shop;
use App\Availability;

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

// Annullamento dal link ricevuto per email.
if (preg_match('#^/annulla/([a-f0-9]{32})$#', $path, $mm)) {
    $apt = Booking::byToken($db, $mm[1]);
    $notice = null;
    if ($method === 'POST' && $apt !== null) {
        $notice = Booking::cancelByToken($db, $cfg, $mm[1]) ? 'La prenotazione è stata annullata.' : 'Non è stato possibile annullare questa prenotazione.';
        $apt = Booking::byToken($db, $mm[1]);
    }
    if ($apt === null) {
        http_response_code(404);
    }
    echo Shop::cancelPage($cfg, $apt, $mm[1], $notice);
    exit;
}

// Pagina pubblica del negozio: /nome, con logo, orari e prenotazione online.
if (preg_match('#^/([a-z0-9][a-z0-9-]{1,78}[a-z0-9])(?:/(logo|giorni|orari|prenota))?$#', $path, $mm)) {
    $shopAcct = Booking::accountBySlug($db, $mm[1]);
    if ($shopAcct !== null) {
        $sub = $mm[2] ?? '';
        $live = Auth::plan($shopAcct)['state'] !== 'pending' && Auth::plan($shopAcct)['state'] !== 'suspended';
        if (!$live) {
            http_response_code(404);
            echo Shop::notFound($cfg);
            exit;
        }
        if ($sub === '') {
            echo Shop::page($cfg, $shopAcct, Availability::services($db, (int)$shopAcct['id']), Availability::staff($db, (int)$shopAcct['id']), Booking::open($cfg, $shopAcct));
            exit;
        }
        if ($sub === 'logo') {
            $img = Shop::logo($shopAcct);
            if ($img === null) {
                http_response_code(404);
                exit;
            }
            header('Content-Type: ' . $img['mime']);
            header('Cache-Control: public, max-age=3600');
            echo $img['data'];
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        $json = static function (int $status, array $data): never {
            http_response_code($status);
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        };
        $staffParam = (int)($_GET['staff'] ?? 0) ?: null;
        $pick = $sub === 'prenota' ? null : Availability::pick($db, (int)$shopAcct['id'], explode(',', (string)($_GET['servizi'] ?? '')));
        if ($sub === 'giorni' && $method === 'GET') {
            $json(200, ['days' => $pick !== null && Booking::open($cfg, $shopAcct) ? Availability::days($db, $shopAcct, (string)($_GET['mese'] ?? ''), $pick['duration'], $staffParam) : []]);
        }
        if ($sub === 'orari' && $method === 'GET') {
            $slots = [];
            if ($pick !== null && Booking::open($cfg, $shopAcct)) {
                foreach (Availability::slots($db, $shopAcct, (string)($_GET['data'] ?? ''), $pick['duration'], $staffParam) as $time => $sid) {
                    $slots[] = $time;
                }
            }
            $json(200, ['slots' => $slots]);
        }
        if ($sub === 'prenota' && $method === 'POST') {
            $in = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($in)) {
                $json(400, ['error' => 'Richiesta non valida.']);
            }
            $email = (string)($in['email'] ?? '');
            if (trim((string)($in['website'] ?? '')) !== '') {
                // Campo trappola per i robot: si risponde come se fosse andata bene.
                $json(200, ['ok' => true, 'message' => 'Richiesta inviata!']);
            }
            $res = Booking::create($db, $cfg, $shopAcct, $in, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
            if (!$res['ok']) {
                $json(400, ['error' => $res['error'] ?? 'Richiesta non riuscita.']);
            }
            $free = !empty($in['free']);
            $json(200, ['ok' => true, 'message' => ($free ? 'Richiesta inviata! Il negozio la valuterà e ti risponderà' : 'Richiesta inviata! Il negozio la confermerà appena possibile e ti scriverà') . ' all\'indirizzo ' . $email . '.']);
        }
        $json(405, ['error' => 'Metodo non consentito']);
    }
}

if ($accountId === null) {
    if ($path === '/admin') {
        $_SESSION['after_login'] = '/admin';
        redirect('/login');
    }
    if ($path === '/') {
        echo Pages::landing($cfg, App\Plans::active($db));
        exit;
    }
    redirect('/login');
}

$acct = Auth::account($db, $accountId);
if ($acct === null) {
    Auth::logout();
    redirect('/login');
}
$plan = Auth::effectivePlan($cfg, $acct);

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
        $action = (string)($_POST['azione'] ?? '');
        $target = (int)($_POST['id'] ?? 0);
        if (str_starts_with($action, 'plan_')) {
            if ($action === 'plan_save') {
                $err = App\Plans::save($db, $target, $_POST);
                if ($err === null) {
                    $notice = $target === 0 ? 'Piano creato.' : 'Piano aggiornato.';
                } else {
                    $error = $err;
                }
            } elseif ($action === 'plan_delete' && App\Plans::delete($db, $target)) {
                $notice = 'Piano eliminato. I negozi che lo avevano già ricevuto restano attivi fino alla loro scadenza.';
            } else {
                $error = 'Operazione non riuscita.';
            }
            goto render_admin;
        }
        $row = Auth::account($db, $target);
        $name = Pages::h((string)($row['business_name'] ?? ''));
        if ($row === null) {
            $error = 'Negozio non trovato.';
        } elseif ($action === 'approve') {
            if (Admin::approve($db, $cfg, $target)) {
                $notice = "<b>$name</b> approvato: parte la prova gratuita e gli abbiamo scritto una email.";
            } else {
                $error = 'Questo negozio è già approvato.';
            }
        } elseif ($action === 'extend' && Admin::extend($db, $target, (int)($_POST['days'] ?? 0))) {
            $notice = "Prova prolungata per <b>$name</b>.";
        } elseif ($action === 'assign') {
            $planRow = App\Plans::find($db, (int)($_POST['plan_id'] ?? 0));
            if ($planRow !== null && App\Plans::assign($db, $target, $planRow['id'])) {
                $notice = 'Piano <b>' . Pages::h($planRow['name']) . "</b> assegnato a <b>$name</b>.";
            } else {
                $error = 'Scegli un piano valido.';
            }
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
    render_admin:
    $all = Admin::rows($db, null, $cfg);
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
        $error,
        App\Plans::all($db)
    );
    exit;
}

if ($plan['state'] === 'pending' && !Auth::isAdmin($cfg, $acct) && $path !== '/admin') {
    echo Pages::pending($cfg, $acct);
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
                $chosen = ctype_digit((string)($_POST['piano'] ?? '')) ? App\Plans::find($db, (int)$_POST['piano']) : null;
                if ($chosen !== null) {
                    if (!$chosen['active'] || $chosen['stripe_price_id'] === '') {
                        throw new RuntimeException('Piano non acquistabile online');
                    }
                    redirect(Billing::checkoutUrlForPrice($cfg, $acct, $chosen['stripe_price_id'], baseUrl($cfg)));
                }
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
    echo Pages::billing($cfg, $acct, $plan, $notice, $error, App\Plans::active($db));
    exit;
}

if ($plan['state'] === 'pending' && !Auth::isAdmin($cfg, $acct)) {
    echo Pages::pending($cfg, $acct);
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
