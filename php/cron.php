<?php
declare(strict_types=1);

/**
 * Attività automatiche: promemoria ai clienti, riepilogo serale, email di fine prova.
 *
 * Da cPanel → Operazioni cron, ogni ora:
 *   php /home/UTENTE/public_html/cron.php
 * oppure, se il cron può solo aprire un indirizzo (serve 'cron_key' in config.php):
 *   curl -s "https://tuosito.it/cron.php?key=LA_TUA_CHIAVE"
 */

use App\Automation;

require __DIR__ . '/server/bootstrap.php';

$cfg = App\loadConfig();
$cli = PHP_SAPI === 'cli';
if (!$cli) {
    $key = (string)($cfg['cron_key'] ?? '');
    if ($key === '' || !hash_equals($key, (string)($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit('Non consentito');
    }
    header('Content-Type: text/plain; charset=utf-8');
}
$db = App\connect($cfg);
$res = Automation::run($db, $cfg);
echo date('Y-m-d H:i:s') . ' promemoria=' . $res['reminders'] . ' riepiloghi=' . $res['summaries'] . ' prova=' . $res['trial'] . ' configurazione=' . $res['onboarding'] . "\n";
