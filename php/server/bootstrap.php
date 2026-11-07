<?php
declare(strict_types=1);

namespace App;

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Api.php';
require_once __DIR__ . '/Auth.php';

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
    $flag = __DIR__ . '/.installed';
    if (!is_file($flag)) {
        Schema::install($db, (bool)($cfg['seed_demo'] ?? false));
        @file_put_contents($flag, date('c'));
    }
    return $db;
}

function securityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}
