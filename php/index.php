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

// Indicazioni per i motori di ricerca: home e pagine dei negozi attivi, mai le demo.
if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nDisallow: /admin\nDisallow: /api/\nSitemap: " . baseUrl($cfg) . "/sitemap.xml\n";
    exit;
}
if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    $base = baseUrl($cfg);
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $xml .= '<url><loc>' . htmlspecialchars($base . '/', ENT_XML1) . '</loc></url>' . "\n";
    foreach ($db->all("SELECT id, email, slug, plan_status, trial_ends_at, paid_until, suspended, approved FROM accounts WHERE approved = 1 AND suspended = 0 AND slug <> '' ORDER BY id") as $a) {
        if (Admin::isDemo($db, (int)$a['id']) || Auth::isAdmin($cfg, $a) || !in_array(Auth::plan($a)['state'], ['trial', 'active'], true)) {
            continue;
        }
        $xml .= '<url><loc>' . htmlspecialchars($base . '/' . $a['slug'], ENT_XML1) . '</loc></url>' . "\n";
    }
    echo $xml . '</urlset>';
    exit;
}

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

// Password dimenticata: richiesta del link e scelta della nuova password.
if ($path === '/password-dimenticata') {
    $notice = null;
    if ($method === 'POST') {
        Auth::requestReset($db, $cfg, (string)($_POST['email'] ?? ''));
        $notice = 'Se l\'indirizzo è registrato, ti abbiamo mandato una email con il link. Controlla anche la cartella spam.';
    }
    echo Pages::forgot($cfg, $notice);
    exit;
}
if (preg_match('#^/reimposta/([a-f0-9]{32})$#', $path, $mm)) {
    $valid = Auth::resetValid($db, $mm[1]);
    if ($method === 'POST' && $valid) {
        $res = Auth::resetPassword($db, $mm[1], (string)($_POST['password'] ?? ''));
        if ($res['ok']) {
            echo Pages::resetForm($cfg, $mm[1], true, null, true);
            exit;
        }
        http_response_code(400);
        echo Pages::resetForm($cfg, $mm[1], true, $res['error'] ?? null);
        exit;
    }
    if (!$valid) {
        http_response_code(404);
    }
    echo Pages::resetForm($cfg, $mm[1], $valid);
    exit;
}

// L'amministratore approva un nuovo negozio dal link nell'email.
if (preg_match('#^/approva/(\d+)/([a-f0-9]{32})$#', $path, $mm)) {
    $target = App\Notify::verify($cfg, 'approva|' . (int)$mm[1], $mm[2]) ? Auth::account($db, (int)$mm[1]) : null;
    $notice = null;
    if ($target !== null && $method === 'POST') {
        $notice = App\Admin::approve($db, $cfg, (int)$target['id']) ? 'Negozio approvato: gli abbiamo scritto una email e parte la prova gratuita.' : 'Questo negozio era già approvato.';
        $target = Auth::account($db, (int)$target['id']);
    }
    if ($target === null) {
        http_response_code(404);
    }
    echo Pages::approveLink($cfg, $target, $mm[2], $notice);
    exit;
}

// Il titolare approva o rifiuta una richiesta dal link firmato ricevuto per email.
if (preg_match('#^/gestisci/([a-f0-9]{32})/([a-f0-9]{32})$#', $path, $mm)) {
    $apt = Booking::byManageLink($db, $cfg, $mm[1], $mm[2]);
    $notice = null;
    $isErr = false;
    if ($apt !== null && $method === 'POST') {
        $res = Booking::decide($db, $cfg, $apt, ($_POST['azione'] ?? '') === 'approva');
        $notice = $res['ok'] ? (($_POST['azione'] ?? '') === 'approva' ? 'Prenotazione confermata: il cliente è stato avvisato.' : 'Richiesta rifiutata: il cliente è stato avvisato.') : (string)$res['error'];
        $isErr = !$res['ok'];
        $apt = Booking::byToken($db, $mm[1]);
    }
    if ($apt === null) {
        http_response_code(404);
    }
    echo Shop::managePage($cfg, $apt, $mm[1], $mm[2], $apt !== null ? Booking::serviceNames($db, (int)$apt['id']) : '', $notice, $isErr);
    exit;
}

// Il cliente sposta la prenotazione dal link ricevuto per email.
if (preg_match('#^/sposta/([a-f0-9]{32})(?:/(giorni|orari|primo))?$#', $path, $mm)) {
    $apt = Booking::byToken($db, $mm[1]);
    $own = $apt !== null ? Auth::account($db, (int)$apt['account_id']) : null;
    $sub = $mm[2] ?? '';
    if ($sub === '' && $method === 'GET') {
        if ($apt === null || $own === null) {
            http_response_code(404);
        }
        $curIds = $apt !== null ? array_map(static fn(array $r): int => (int)$r['service_id'], $db->all('SELECT service_id FROM appointment_services WHERE appointment_id = ? ORDER BY id', [(int)$apt['id']])) : [];
        echo Shop::reschedulePage($cfg, $own !== null ? $apt : null, $mm[1], $own !== null ? Availability::services($db, (int)$own['id']) : [], $curIds);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    $json = static function (int $status, mixed $data): never {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    };
    if ($apt === null || $own === null || !Booking::changeable($apt) || !Booking::open($cfg, $own)) {
        $json(404, ['error' => 'Questa prenotazione non può più essere spostata.']);
    }
    $dur = Booking::duration($apt);
    if ($method === 'GET' && isset($_GET['servizi']) && $_GET['servizi'] !== '') {
        $np = Availability::pick($db, (int)$own['id'], explode(',', (string)$_GET['servizi']));
        if ($np === null) {
            $json(400, ['error' => 'Scegli almeno un servizio tra quelli disponibili.']);
        }
        $dur = $np['duration'];
    }
    $exclude = (int)$apt['id'];
    if ($sub === 'giorni' && $method === 'GET') {
        $json(200, ['days' => Availability::days($db, $own, (string)($_GET['mese'] ?? ''), $dur, null, null, $exclude)]);
    }
    if ($sub === 'orari' && $method === 'GET') {
        $json(200, ['slots' => array_keys(Availability::slots($db, $own, (string)($_GET['data'] ?? ''), $dur, null, null, $exclude))]);
    }
    if ($sub === 'primo' && $method === 'GET') {
        $json(200, Availability::first($db, $own, $dur, null, null, $exclude) ?? new stdClass());
    }
    if ($sub === '' && $method === 'POST') {
        $in = json_decode((string)file_get_contents('php://input'), true);
        $res = is_array($in) ? Booking::reschedule($db, $cfg, $mm[1], (string)($in['date'] ?? ''), (string)($in['time'] ?? ''), null, isset($in['service_ids']) && is_array($in['service_ids']) ? $in['service_ids'] : null) : ['ok' => false, 'error' => 'Richiesta non valida.'];
        if (!$res['ok']) {
            $json(400, ['error' => $res['error'] ?? 'Spostamento non riuscito.']);
        }
        $json(200, ['ok' => true, 'message' => $res['status'] === 'confirmed' ? 'Prenotazione spostata e confermata. Ti abbiamo scritto il riepilogo.' : 'Richiesta di spostamento inviata: il negozio la confermerà appena possibile.']);
    }
    $json(405, ['error' => 'Metodo non consentito']);
}

// Calendario (.ics) della prenotazione, dal link nell'email del cliente.
if (preg_match('#^/calendario/([a-f0-9]{32})\.ics$#', $path, $mm)) {
    $apt = Booking::byToken($db, $mm[1]);
    $own = $apt !== null ? Auth::account($db, (int)$apt['account_id']) : null;
    if ($apt === null || $own === null) {
        http_response_code(404);
        exit('Non trovato');
    }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="prenotazione.ics"');
    echo Booking::ics($own, $apt, Booking::serviceNames($db, (int)$apt['id']));
    exit;
}

// Pagina pubblica del negozio: /nome, con logo, orari e prenotazione online.
if (preg_match('#^/([a-z0-9][a-z0-9-]{1,78}[a-z0-9])(?:/(logo|copertina|giorni|orari|prenota|privacy|primo|qr\.svg|foto/\d{1,9}))?$#', $path, $mm)) {
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
            $shopAcct['_hours'] = Availability::hoursSummary($db, (int)$shopAcct['id']);
            $shopAcct['_demo'] = Admin::isDemo($db, (int)$shopAcct['id']);
            $shopAcct['_photos'] = Shop::photoIds($db, (int)$shopAcct['id']);
            if ($shopAcct['_demo']) {
                header('X-Robots-Tag: noindex, nofollow');
            }
            echo Shop::page($cfg, $shopAcct, Availability::services($db, (int)$shopAcct['id']), Availability::staff($db, (int)$shopAcct['id']), Booking::open($cfg, $shopAcct));
            exit;
        }
        if ($sub === 'qr.svg') {
            $svg = App\Qr::svg(App\Notify::shopUrl($cfg, $shopAcct), 480);
            if ($svg === null) {
                http_response_code(404);
                exit;
            }
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=3600');
            echo $svg;
            exit;
        }
        if ($sub === 'privacy') {
            echo Shop::privacyPage($cfg, $shopAcct);
            exit;
        }
        if ($sub === 'logo' || $sub === 'copertina' || str_starts_with($sub, 'foto/')) {
            $img = match (true) {
                $sub === 'logo' => Shop::logo($shopAcct),
                $sub === 'copertina' => Shop::cover($shopAcct),
                default => Shop::photo($db, (int)$shopAcct['id'], (int)substr($sub, 5)),
            };
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
        if ($sub === 'primo' && $method === 'GET') {
            $f = $pick !== null && Booking::open($cfg, $shopAcct) ? Availability::first($db, $shopAcct, $pick['duration'], $staffParam) : null;
            $json(200, $f ?? new stdClass());
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
            $where = $res['email'] !== '' ? ' all\'indirizzo ' . $res['email'] : ' al numero ' . $res['phone'];
            if (!empty($res['auto'])) {
                $msg = 'Prenotazione confermata!' . ($res['email'] !== '' ? ' Ti abbiamo scritto' . $where . ' con il riepilogo.' : ' Ti aspettiamo.');
            } else {
                $msg = ($free ? 'Richiesta inviata! Il negozio la valuterà e ti risponderà' : 'Richiesta inviata! Il negozio la confermerà appena possibile e ti scriverà') . $where . '.';
            }
            $json(200, ['ok' => true, 'message' => $msg]);
        }
        $json(405, ['error' => 'Metodo non consentito']);
    }
}

if ($accountId === null) {
    if ($path === '/admin') {
        Auth::start();
        $_SESSION['after_login'] = '/admin';
        redirect('/login');
    }
    if ($path === '/') {
        echo Pages::landing($cfg, App\Plans::active($db), Admin::showcase($db));
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

// L'amministratore esce dal negozio che stava guardando.
if ($path === '/admin/torna') {
    Auth::stopImpersonating($db, $cfg);
    redirect('/admin');
}

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
        if ($action === 'duplicate') {
            $in = $_POST;
            $up = $_FILES['logo'] ?? null;
            if (is_array($up) && ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string)$up['tmp_name'])) {
                $raw = (string)file_get_contents((string)$up['tmp_name']);
                $info = @getimagesizefromstring($raw);
                $in['logo'] = 'data:' . (string)($info['mime'] ?? 'application/octet-stream') . ';base64,' . base64_encode($raw);
            } elseif (is_array($up) && ($up['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $in['logo'] = 'x'; // caricamento non riuscito: verrà segnalato come logo non valido
            }
            $upc = $_FILES['cover'] ?? null;
            if (is_array($upc) && ($upc['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string)$upc['tmp_name'])) {
                $raw = (string)file_get_contents((string)$upc['tmp_name']);
                $info = @getimagesizefromstring($raw);
                $in['cover'] = 'data:' . (string)($info['mime'] ?? 'application/octet-stream') . ';base64,' . base64_encode($raw);
            } elseif (is_array($upc) && ($upc['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $in['cover'] = 'x';
            }
            $in['photos'] = [];
            $ups = $_FILES['photos'] ?? null;
            if (is_array($ups) && is_array($ups['tmp_name'] ?? null)) {
                foreach ($ups['tmp_name'] as $k => $tmp) {
                    if (($ups['error'][$k] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if (($ups['error'][$k] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$tmp)) {
                        $in['photos'][] = 'x';
                        continue;
                    }
                    $raw = (string)file_get_contents((string)$tmp);
                    $info = @getimagesizefromstring($raw);
                    $in['photos'][] = 'data:' . (string)($info['mime'] ?? 'application/octet-stream') . ';base64,' . base64_encode($raw);
                }
            }
            $res = Admin::duplicate($db, $cfg, $target, $in);
            if ($res['ok']) {
                $new = Auth::account($db, (int)$res['id']) ?? [];
                $csrf = Pages::h(Admin::csrfToken());
                $notice = 'Demo <b>' . Pages::h((string)$new['business_name']) . '</b> creata. Accesso: <b>' . Pages::h((string)$res['email']) . '</b> · password <code class="pw">' . Pages::h((string)$res['password']) . '</code>'
                    . '<br>Pagina di prenotazione: <a href="' . Pages::h(App\Notify::shopUrl($cfg, $new)) . '" target="_blank" rel="noopener">' . Pages::h(App\Notify::shopUrl($cfg, $new)) . '</a>'
                    . '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">'
                    . '<a class="mini" style="text-decoration:none;background:#dcfce7;border-color:#86efac;color:#166534" href="' . Pages::h(Admin::demoWhatsappUrl($db, $cfg, $new)) . '" target="_blank" rel="noopener">Invia la demo su WhatsApp</a>'
                    . '<a class="mini" style="text-decoration:none" href="/' . Pages::h((string)$new['slug']) . '/qr.svg" download="qr-' . Pages::h((string)$new['slug']) . '.svg">Scarica il QR code</a>'
                    . '<a class="mini" style="text-decoration:none" href="' . Pages::h(App\Notify::shopUrl($cfg, $new)) . '" target="_blank" rel="noopener">Apri la pagina</a>'
                    . '<form method="post" action="/admin" style="margin:0"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="azione" value="impersonate"><input type="hidden" name="id" value="' . (int)$res['id'] . '"><button class="mini">Entra e personalizza servizi e prezzi →</button></form></div>';
            } else {
                $error = $res['error'] ?? 'Duplicazione non riuscita.';
                $dupOld = $_POST;
            }
            goto render_admin;
        }
        if ($action === 'cat_save' || $action === 'cat_delete') {
            $err = $action === 'cat_save' ? App\Categories::save($db, $target, $_POST) : App\Categories::delete($db, $target, (string)($_POST['move_to'] ?? ''));
            if ($err === null) {
                $notice = $action === 'cat_delete' ? 'Categoria eliminata.' : ($target === 0 ? 'Categoria aggiunta.' : 'Categoria aggiornata.');
            } else {
                $error = $err;
                $catOld = $_POST;
            }
            goto render_admin;
        }
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
        } elseif ($action === 'impersonate') {
            if (Auth::impersonate($db, $cfg, $accountId, $target)) {
                redirect('/');
            }
            $error = 'Non puoi entrare in questo negozio.';
        } elseif ($action === 'deliver') {
            $err = Admin::deliver($db, $cfg, $target, (string)($_POST['email'] ?? ''), (int)($_POST['plan_id'] ?? 0));
            if ($err === null) {
                $notice = "<b>$name</b> consegnato a <b>" . Pages::h(strtolower(trim((string)$_POST['email']))) . '</b>: gli abbiamo scritto una email con il link per scegliere la password (valido 7 giorni).'
                    . ((int)($_POST['plan_id'] ?? 0) > 0 ? ' Piano attivato.' : ' Resta in prova: puoi assegnare un piano da Azioni.');
            } else {
                $error = $err;
            }
        } elseif ($action === 'showcase') {
            $notice = Admin::toggleShowcase($db, $target) ? "<b>$name</b> ora compare tra gli esempi della home." : "<b>$name</b> tolto dagli esempi della home.";
        } elseif ($action === 'email') {
            $err = Admin::changeEmail($db, $target, (string)($_POST['email'] ?? ''));
            if ($err === null) {
                $notice = "Email di accesso di <b>$name</b> cambiata in <b>" . Pages::h(strtolower(trim((string)$_POST['email']))) . '</b>. Se serve, genera anche una nuova password.';
            } else {
                $error = $err;
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
        App\Plans::all($db),
        [
            'sources' => array_combine(array_column($all, 'id'), array_map(static fn(array $r): string => $r['business_name'] . ' (/' . $r['slug'] . ')' . (!empty($r['is_demo']) ? ' · demo' : ''), $all)),
            'dup' => isset($dupOld) ? (int)($dupOld['id'] ?? 0) : (int)($_GET['duplica'] ?? 0),
            'old' => $dupOld ?? [],
            'categories' => App\Categories::all(),
            'usage' => App\Categories::usage($db),
            'cat_old' => $catOld ?? [],
            'insights' => Admin::insights($all),
        ]
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

// Esportazione degli appuntamenti in CSV (dal pannello: Appuntamenti → Esporta).
if ($path === '/export/appuntamenti.csv') {
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['dal'] ?? '')) ? (string)$_GET['dal'] : date('Y-m-d', strtotime('-90 days'));
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['al'] ?? '')) ? (string)$_GET['al'] : date('Y-m-d', strtotime('+365 days'));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="appuntamenti-' . date('Y-m-d') . '.csv"');
    echo App\Export::appointmentsCsv($db, $accountId, $from, $to);
    exit;
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
