<?php
declare(strict_types=1);

use App\Auth;

require __DIR__ . '/server/bootstrap.php';

$cfg = App\loadConfig();
App\securityHeaders();
$path = rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$configured = ($cfg['admin_password_hash'] ?? '') !== '';

header('Cache-Control: no-store');

if ($path === '/logout') {
    Auth::logout();
    header('Location: /login');
    exit;
}

if ($path === '/login') {
    if ($method === 'POST') {
        if (Auth::attempt($cfg, (string)($_POST['password'] ?? ''))) {
            header('Location: /');
            exit;
        }
        http_response_code(401);
        echo Auth::loginPage('Password errata.', $configured);
        exit;
    }
    if (Auth::isLoggedIn()) {
        header('Location: /');
        exit;
    }
    echo Auth::loginPage(null, $configured);
    exit;
}

if (!Auth::isLoggedIn()) {
    header('Location: /login');
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
