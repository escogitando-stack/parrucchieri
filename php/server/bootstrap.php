<?php
declare(strict_types=1);

namespace App;

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Api.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Categories.php';
require_once __DIR__ . '/Themes.php';
require_once __DIR__ . '/Owner.php';
require_once __DIR__ . '/Booking.php';
require_once __DIR__ . '/Availability.php';
require_once __DIR__ . '/Shop.php';
require_once __DIR__ . '/Plans.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Notify.php';
require_once __DIR__ . '/Billing.php';
require_once __DIR__ . '/Pages.php';
require_once __DIR__ . '/Admin.php';
require_once __DIR__ . '/Automation.php';
require_once __DIR__ . '/Qr.php';
require_once __DIR__ . '/Export.php';

/**
 * @return array<string,mixed>
 */
function loadConfig(): array
{
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Configurazione mancante: copia server/config.sample.php in server/config.php e compilalo.";
        exit;
    }
    $cfg = require $file;
    date_default_timezone_set((string)($cfg['timezone'] ?? 'Europe/Rome'));
    return $cfg;
}

function connect(array $cfg): Db
{
    $db = Db::connect($cfg['db'] ?? []);
    try {
        $version = $db->metaGet('schema_version');
    } catch (\Throwable) {
        $version = null; // database ancora vuoto
    }
    if ($version === null || (int)$version < Schema::VERSION) {
        Schema::ensure($db);
    }
    Categories::boot($db);
    return $db;
}

function securityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}
