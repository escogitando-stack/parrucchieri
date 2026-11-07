<?php
/**
 * Router per provare l'app in locale con il server integrato di PHP, imitando .htaccess:
 *
 *   pnpm build && scripts/assemble.sh /tmp/parrucchieri-site
 *   cp php/server/config.sample.php /tmp/parrucchieri-site/server/config.php   # poi modificalo
 *   php -S localhost:8080 -t /tmp/parrucchieri-site scripts/dev-router.php
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = $_SERVER['DOCUMENT_ROOT'];

if (preg_match('#^/(server/|app\.html$)#', $path)) {
    http_response_code(403);
    exit('Vietato');
}
if (preg_match('#^/api(/|$)#', $path)) {
    require $root . '/api.php';
    return true;
}
if ($path !== '/' && is_file($root . $path) && !str_ends_with($path, '.php')) {
    return false; // file statico
}
require $root . '/index.php';
return true;
