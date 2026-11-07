<?php
declare(strict_types=1);

/**
 * Test di integrazione dell'API PHP su SQLite in memoria.
 * Esecuzione: php php/tests/run.php
 */

require __DIR__ . '/../server/bootstrap.php';

use App\Api;
use App\Db;
use App\Schema;

date_default_timezone_set('Europe/Rome');

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  ok   $name\n";
    } else {
        $failed++;
        echo "  FAIL $name" . ($detail ? " -> $detail" : '') . "\n";
    }
}

function fresh(bool $demo = false): Api
{
    $db = Db::connect(['driver' => 'sqlite', 'path' => ':memory:']);
    Schema::install($db, $demo);
    return new Api($db);
}

/** @return array{0:int,1:array} */
function call(Api $api, string $method, string $path, array $query = [], ?array $body = null): array
{
    return $api->handle($method, $path, $query, $body);
}

// ── Installazione ────────────────────────────────────────────────────
echo "Installazione e dati iniziali\n";
$api = fresh();
[$s, $d] = call($api, 'GET', '/api/services');
check('8 servizi da parrucchiere', $s === 200 && count($d['services']) === 8);
check('prezzi numerici', is_float($d['services'][0]['price']) || is_int($d['services'][0]['price']));
[$s, $d] = call($api, 'GET', '/api/staff/all');
check('un collaboratore iniziale', count($d['staff']) === 1 && $d['staff'][0]['name'] === 'Titolare');
$demo = fresh(true);
[$s, $d] = call($demo, 'GET', '/api/clients');
check('demo: 4 clienti', $d['total'] === 4);
[$s, $d] = call($demo, 'GET', '/api/staff/all');
check('demo: 4 collaboratori', count($d['staff']) === 4);

// ── Clienti ──────────────────────────────────────────────────────────
echo "Clienti\n";
$api = fresh();
[$s, $d] = call($api, 'POST', '/api/clients', [], ['name' => 'Anna Verdi', 'phone' => '333 000']);
check('crea cliente (201)', $s === 201 && $d['client']['name'] === 'Anna Verdi');
$cid = $d['client']['id'];
check('id è intero', is_int($cid));
[$s] = call($api, 'POST', '/api/clients', [], ['name' => '  ']);
check('nome vuoto → 400', $s === 400);
[$s] = call($api, 'POST', '/api/clients', [], []);
check('senza nome → 400', $s === 400);
[$s, $d] = call($api, 'PUT', "/api/clients/$cid", [], ['notes' => 'Allergica al ammoniaca', 'unknown' => 'x; DROP TABLE clients']);
check('modifica cliente', $s === 200 && $d['ok'] === true);
[$s, $d] = call($api, 'GET', "/api/clients/$cid");
check('legge cliente con note', $d['client']['notes'] === 'Allergica al ammoniaca');
[$s, $d] = call($api, 'GET', '/api/clients', ['search' => 'verdi']);
check('ricerca cliente', $d['total'] === 1 && $d['clients'][0]['appointment_count'] === 0);
[$s, $d] = call($api, 'GET', '/api/clients/all');
check('elenco clienti per menu', count($d['clients']) === 1);
[$s] = call($api, 'GET', '/api/clients/9999');
check('cliente inesistente → 404', $s === 404);

// ── Servizi / personale / prodotti ───────────────────────────────────
echo "Servizi, personale, prodotti\n";
[$s, $d] = call($api, 'POST', '/api/staff', [], ['name' => 'Marco', 'color' => '#3b82f6', 'title' => 'Barbiere']);
check('crea collaboratore', $s === 201 && $d['staff']['active'] === 1);
$marco = $d['staff']['id'];
[$s] = call($api, 'POST', '/api/staff', [], ['name' => 'X', 'color' => 'rosso']);
check('colore non valido → 400', $s === 400);
[$s, $d] = call($api, 'PUT', "/api/staff/$marco", [], ['active' => 0]);
[$s, $d] = call($api, 'GET', '/api/staff/all');
check('collaboratore disattivato escluso dal menu', count($d['staff']) === 1);
call($api, 'PUT', "/api/staff/$marco", [], ['active' => 1]);

[$s, $d] = call($api, 'POST', '/api/services', [], ['name' => 'Meches', 'duration' => 120, 'price' => 80.5, 'description' => 'Con cuffia']);
check('crea servizio con descrizione', $s === 201 && $d['service']['description'] === 'Con cuffia' && $d['service']['duration'] === 120);
$svcNew = $d['service']['id'];
[$s] = call($api, 'PUT', "/api/services/$svcNew", [], ['duration' => 0]);
check('durata 0 → 400', $s === 400);
[$s] = call($api, 'PUT', "/api/services/$svcNew", [], ['active' => 0]);
check('disattiva servizio', $s === 200);

[$s, $d] = call($api, 'POST', '/api/products', [], ['name' => 'Shampoo', 'price' => 12.9, 'stock' => 2, 'low_stock_alert' => 5]);
check('crea prodotto', $s === 201 && $d['product']['stock'] === 2);
$pid = $d['product']['id'];
[$s] = call($api, 'POST', '/api/products', [], ['name' => 'Neg', 'price' => -1]);
check('prezzo negativo → 400', $s === 400);
[$s, $d] = call($api, 'GET', '/api/products', ['search' => 'sham']);
check('ricerca prodotti', $d['total'] === 1);
[$s, $d] = call($api, 'GET', '/api/stats');
check('scorta bassa nelle statistiche', $d['low_stock_products'] === 1 && $d['products'] === 1);

// ── Appuntamenti ─────────────────────────────────────────────────────
echo "Appuntamenti\n";
$services = call($api, 'GET', '/api/services')[1]['services'];
$byName = [];
foreach ($services as $sv) { $byName[$sv['name']] = $sv; }
$taglio = $byName['Taglio donna'];   // 60 min, 45 €
$colore = $byName['Colore'];         // 90 min, 60 €
$staffId = array_values(array_filter(call($api, 'GET', '/api/staff/all')[1]['staff'], fn($x) => $x['name'] === 'Titolare'))[0]['id'];
$day = date('Y-m-d');

[$s, $d] = call($api, 'POST', '/api/appointments', [], [
    'client_id' => $cid, 'staff_id' => $staffId, 'scheduled_date' => $day,
    'start_time' => '10:00', 'service_ids' => [$taglio['id'], $colore['id']],
]);
check('crea appuntamento (201)', $s === 201, json_encode($d));
$apt = $d['appointment'];
check('durata e fine calcolate (10:00 + 150 min = 12:30)', $apt['end_time'] === '12:30', $apt['end_time'] ?? '');
check('totale 105 €', (float)$apt['total_price'] === 105.0);
check('identificativo APP-1', $apt['identifier'] === 'APP-1');
check('nome cliente incluso', $apt['client_name'] === 'Anna Verdi');
$aptId = $apt['id'];

[$s, $d] = call($api, 'POST', '/api/appointments', [], [
    'client_id' => $cid, 'staff_id' => $staffId, 'scheduled_date' => $day,
    'start_time' => '11:00', 'service_ids' => [$taglio['id']],
]);
check('sovrapposizione → 409', $s === 409 && !empty($d['conflicts']), json_encode($d));
check('409 elenca l\'appuntamento in conflitto', ($d['conflicts'][0]['kind'] ?? '') === 'appointment' && ($d['conflicts'][0]['label'] ?? '') === 'Anna Verdi');

[$s, $d] = call($api, 'POST', '/api/appointments', [], [
    'client_id' => $cid, 'staff_id' => $staffId, 'scheduled_date' => $day,
    'start_time' => '12:30', 'service_ids' => [$taglio['id']],
]);
check('appuntamento consecutivo NON è un conflitto', $s === 201, json_encode($d));
$aptB = $d['appointment']['id'];
check('identificativo APP-2', $d['appointment']['identifier'] === 'APP-2');

[$s, $d] = call($api, 'POST', '/api/appointments', [], [
    'client_id' => $cid, 'staff_id' => $staffId, 'scheduled_date' => $day,
    'start_time' => '11:00', 'service_ids' => [$taglio['id']], 'allow_conflict' => true,
]);
check('allow_conflict permette la sovrapposizione', $s === 201);
$aptC = $d['appointment']['id'];

[$s, $d] = call($api, 'POST', '/api/appointments', [], [
    'client_id' => $cid, 'scheduled_date' => $day, 'start_time' => '10:00', 'service_ids' => [$taglio['id']],
]);
check('senza collaboratore nessun conflitto', $s === 201 && $d['appointment']['staff_id'] === null);

[$s] = call($api, 'POST', '/api/appointments', [], ['client_id' => $cid, 'scheduled_date' => 'ieri']);
check('data non valida → 400', $s === 400);
[$s] = call($api, 'POST', '/api/appointments', [], ['client_id' => 9999, 'scheduled_date' => $day]);
check('cliente inesistente → 400', $s === 400);
[$s] = call($api, 'POST', '/api/appointments', [], ['client_id' => $cid, 'scheduled_date' => $day, 'start_time' => '25:99']);
check('orario non valido → 400', $s === 400);
[$s] = call($api, 'POST', '/api/appointments', [], ['client_id' => $cid, 'scheduled_date' => $day, 'start_time' => '23:00', 'service_ids' => [$colore['id']]]);
check('oltre mezzanotte → 400', $s === 400);

[$s, $d] = call($api, 'GET', "/api/appointments/$aptId");
check('dettaglio con servizi', count($d['appointment']['appointment_services']) === 2 && $d['appointment']['appointment_services'][0]['service_name'] === 'Taglio donna');

call($api, 'POST', "/api/appointments/$aptId/notes", [], ['content' => 'Porta le foto']);
call($api, 'POST', "/api/appointments/$aptId/notes", [], ['content' => 'Seconda nota']);
[$s, $d] = call($api, 'GET', "/api/appointments/$aptId");
check('note in ordine inverso', count($d['appointment']['appointment_notes']) === 2 && $d['appointment']['appointment_notes'][0]['content'] === 'Seconda nota');
[$s] = call($api, 'DELETE', '/api/notes/' . $d['appointment']['appointment_notes'][0]['id']);
check('elimina nota', $s === 200);
[$s] = call($api, 'POST', '/api/appointments/9999/notes', [], ['content' => 'x']);
check('nota su appuntamento inesistente → 404', $s === 404);

// Sposta: mantiene la durata
[$s, $d] = call($api, 'PUT', "/api/appointments/$aptB", [], ['start_time' => '15:00', 'scheduled_date' => $day]);
check('sposta senza conflitto', $s === 200, json_encode($d));
$moved = call($api, 'GET', "/api/appointments/$aptB")[1]['appointment'];
check('spostando mantiene la durata (15:00-16:00)', $moved['start_time'] === '15:00' && $moved['end_time'] === '16:00', $moved['end_time']);
[$s, $d] = call($api, 'PUT', "/api/appointments/$aptB", [], ['start_time' => '10:30']);
check('spostare su un orario occupato → 409', $s === 409);
[$s] = call($api, 'PUT', "/api/appointments/$aptB", [], ['start_time' => '10:30', 'allow_conflict' => true]);
check('spostare con allow_conflict', $s === 200);

// Stato: una sovrapposizione già accettata non blocca cambi di stato
[$s] = call($api, 'PUT', "/api/appointments/$aptC", [], ['status' => 'completed']);
check('completare un appuntamento già sovrapposto è consentito', $s === 200);
[$s] = call($api, 'PUT', "/api/appointments/$aptC", [], ['status' => 'inventato']);
check('stato non valido → 400', $s === 400);
[$s] = call($api, 'PUT', "/api/appointments/$aptC", [], ['status' => 'cancelled']);
[$s, $d] = call($api, 'PUT', "/api/appointments/$aptC", [], ['status' => 'confirmed']);
check('ripristinare un annullato in conflitto → 409', $s === 409);
[$s] = call($api, 'PUT', '/api/appointments/9999', [], ['status' => 'confirmed']);
check('aggiornare inesistente → 404', $s === 404);

// Assegnazione / disassegnazione
[$s] = call($api, 'PUT', "/api/appointments/$aptC", [], ['status' => 'booked', 'staff_id' => null, 'allow_conflict' => true]);
$unassigned = call($api, 'GET', "/api/appointments/$aptC")[1]['appointment'];
check('staff_id null toglie l\'assegnazione', $unassigned['staff_id'] === null);

// ── Agenda, liste, statistiche ───────────────────────────────────────
echo "Agenda e statistiche\n";
[$s, $d] = call($api, 'GET', '/api/calendar', ['start' => $day, 'end' => $day]);
check('agenda restituisce appuntamenti con servizi', $s === 200 && count($d['appointments']) >= 3 && isset($d['appointments'][0]['appointment_services']));
[$s] = call($api, 'GET', '/api/calendar', ['start' => $day]);
check('agenda senza end → 400', $s === 400);

[$s, $d] = call($api, 'POST', '/api/blocked-slots', [], ['staff_id' => $staffId, 'blocked_date' => $day, 'start_time' => '13:00', 'end_time' => '14:00', 'reason' => 'Pranzo']);
check('blocca orario libero', $s === 201);
[$s, $d] = call($api, 'POST', '/api/blocked-slots', [], ['staff_id' => $staffId, 'blocked_date' => $day, 'start_time' => '13:30', 'end_time' => '14:30']);
check('blocco su blocco → 409', $s === 409 && $d['conflicts'][0]['kind'] === 'blocked');
check('409 del blocco suggerisce "bloccare"', str_contains($d['error'], 'bloccare'));
[$s] = call($api, 'POST', '/api/blocked-slots', [], ['staff_id' => $staffId, 'blocked_date' => $day, 'start_time' => '14:00', 'end_time' => '13:00']);
check('blocco al contrario → 400', $s === 400);
[$s, $d] = call($api, 'POST', '/api/appointments', [], [
    'client_id' => $cid, 'staff_id' => $staffId, 'scheduled_date' => $day, 'start_time' => '13:00', 'service_ids' => [$byName['Taglio uomo']['id']],
]);
check('prenotare su un blocco → 409', $s === 409 && $d['conflicts'][0]['label'] === 'Pranzo');
[$s, $d] = call($api, 'GET', '/api/calendar', ['start' => $day, 'end' => $day]);
check('blocchi nell\'agenda con nome collaboratore', count($d['blocked_slots']) === 1 && $d['blocked_slots'][0]['staff_name'] === 'Titolare');
[$s] = call($api, 'DELETE', '/api/blocked-slots/' . $d['blocked_slots'][0]['id']);
check('elimina blocco', $s === 200);

[$s, $d] = call($api, 'GET', '/api/appointments', ['search' => 'verdi', 'status' => 'booked']);
check('lista filtrata per cliente e stato', $s === 200 && $d['total'] >= 1);
[$s, $d] = call($api, 'GET', '/api/appointments', ['limit' => '1', 'page' => '2']);
check('paginazione', count($d['appointments']) === 1 && $d['total'] > 1);
[$s, $d] = call($api, 'GET', "/api/clients/$cid");
check('storico cliente con nomi servizi e ultima nota', count($d['appointments']) >= 3 && is_string($d['appointments'][0]['service_names'] ?? null) || array_filter($d['appointments'], fn($a) => !empty($a['latest_note'])) !== []);

call($api, 'PUT', "/api/appointments/$aptB", [], ['status' => 'completed']);
[$s, $d] = call($api, 'GET', '/api/staff');
$titolare = array_values(array_filter($d['staff'], fn($x) => $x['id'] === $staffId))[0];
check('statistiche collaboratore numeriche', is_int($titolare['appointment_count']) && is_float($titolare['completed_service_value']));
[$s, $d] = call($api, 'GET', '/api/stats');
check('statistiche: tipi corretti', is_int($d['appointments']) && is_float($d['revenue']) && $d['today_appointments'] >= 3);
check('incasso = servizi completati (45 €)', $d['revenue'] === 45.0, (string)$d['revenue']);

// ── Eliminazioni e cascate ───────────────────────────────────────────
echo "Eliminazioni\n";
[$s] = call($api, 'DELETE', "/api/staff/$staffId");
$orphan = call($api, 'GET', "/api/appointments/$aptId")[1]['appointment'];
check('eliminando il collaboratore l\'appuntamento resta, senza assegnazione', $orphan['staff_id'] === null);
[$s] = call($api, 'DELETE', "/api/clients/$cid");
[$s] = call($api, 'GET', "/api/appointments/$aptId");
check('eliminando il cliente spariscono i suoi appuntamenti', $s === 404);
[$s] = call($api, 'DELETE', "/api/products/$pid");
[$s] = call($api, 'DELETE', "/api/services/$svcNew");
check('elimina prodotto e servizio', $s === 200);

// ── Routing ──────────────────────────────────────────────────────────
echo "Routing\n";
[$s, $d] = call($api, 'GET', '/api/inesistente');
check('rotta sconosciuta → 404', $s === 404 && isset($d['error']));
[$s] = call($api, 'PATCH', '/api/clients');
check('metodo non previsto → 404', $s === 404);

// ── Identificativi senza buchi/doppioni ──────────────────────────────
echo "Contatore\n";
$a = fresh();
$c = call($a, 'POST', '/api/clients', [], ['name' => 'T'])[1]['client']['id'];
$ids = [];
for ($i = 0; $i < 5; $i++) {
    $ids[] = call($a, 'POST', '/api/appointments', [], ['client_id' => $c, 'scheduled_date' => '2030-01-01'])[1]['appointment']['identifier'];
}
check('identificativi sequenziali', $ids === ['APP-1', 'APP-2', 'APP-3', 'APP-4', 'APP-5'], implode(',', $ids));

// ── Scheduling (unit) ────────────────────────────────────────────────
echo "Scheduling\n";
check('toMinutes valido', App\toMinutes('09:30') === 570);
check('toMinutes non valido', App\toMinutes('24:00') === null && App\toMinutes('9') === null && App\toMinutes(null) === null);
check('addMinutes', App\addMinutes('09:30', 45) === '10:15');
$busy = [['kind' => 'appointment', 'start_time' => '10:00', 'end_time' => '11:00', 'label' => 'A']];
check('intervalli semiaperti', App\findConflicts('11:00', '12:00', $busy) === [] && App\findConflicts('10:59', '12:00', $busy) !== []);
check('candidato non valido → nessun conflitto', App\findConflicts('12:00', '11:00', $busy) === []);

// ── Auth ─────────────────────────────────────────────────────────────
echo "Autenticazione\n";
$hash = password_hash('segreto', PASSWORD_DEFAULT);
check('hash configurato', strlen($hash) > 20);
$page = App\Auth::loginPage('Password errata.', true);
check('pagina di login in italiano con errore', str_contains($page, 'Accedi') && str_contains($page, 'Password errata.'));
check('pagina di login escapa l\'HTML', !str_contains(App\Auth::loginPage('<script>x</script>'), '<script>x</script>'));
check('avviso se password non configurata', str_contains(App\Auth::loginPage(null, false), 'admin_password_hash'));

echo "\n$passed ok, $failed falliti\n";
exit($failed === 0 ? 0 : 1);
