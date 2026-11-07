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
use App\Auth;
use App\Billing;
use App\Pages;

date_default_timezone_set('Europe/Rome');

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        fwrite(STDOUT, "  ok   $name\n");
    } else {
        $failed++;
        fwrite(STDOUT, "  FAIL $name" . ($detail ? " -> $detail" : '') . "\n");
    }
}

function newDb(): Db
{
    $db = Db::connect(['driver' => 'sqlite', 'path' => ':memory:']);
    Schema::install($db);
    return $db;
}

/** Crea un negozio (senza passare dalla sessione) con i servizi della categoria. */
function addAccount(Db $db, string $email, string $category = 'parrucchiere'): int
{
    $db->exec(
        'INSERT INTO accounts (email, password_hash, business_name, category, trial_ends_at) VALUES (?, ?, ?, ?, ?)',
        [$email, password_hash('segretissima', PASSWORD_DEFAULT), 'Negozio ' . $email, $category, date('Y-m-d H:i:s', time() + 30 * 86400)]
    );
    $id = $db->lastId();
    Schema::seedAccount($db, $id, $category);
    return $id;
}

function fresh(string $category = 'parrucchiere'): Api
{
    $db = newDb();
    return new Api($db, addAccount($db, 'a@example.com', $category));
}

/** @return array{0:int,1:array} */
function call(Api $api, string $method, string $path, array $query = [], ?array $body = null): array
{
    return $api->handle($method, $path, $query, $body);
}

// ── Installazione ────────────────────────────────────────────────────
fwrite(STDOUT, "Installazione e dati iniziali\n");
$api = fresh();
[$s, $d] = call($api, 'GET', '/api/services');
check('8 servizi da parrucchiere', $s === 200 && count($d['services']) === 8);
check('prezzi numerici', is_float($d['services'][0]['price']) || is_int($d['services'][0]['price']));
[$s, $d] = call($api, 'GET', '/api/staff/all');
check('un collaboratore iniziale', count($d['staff']) === 1 && $d['staff'][0]['name'] === 'Titolare');

// ── Clienti ──────────────────────────────────────────────────────────
fwrite(STDOUT, "Clienti\n");
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
fwrite(STDOUT, "Servizi, personale, prodotti\n");
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
fwrite(STDOUT, "Appuntamenti\n");
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
fwrite(STDOUT, "Agenda e statistiche\n");
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
fwrite(STDOUT, "Eliminazioni\n");
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
fwrite(STDOUT, "Routing\n");
[$s, $d] = call($api, 'GET', '/api/inesistente');
check('rotta sconosciuta → 404', $s === 404 && isset($d['error']));
[$s] = call($api, 'PATCH', '/api/clients');
check('metodo non previsto → 404', $s === 404);

// ── Identificativi senza buchi/doppioni ──────────────────────────────
fwrite(STDOUT, "Contatore\n");
$a = fresh();
$c = call($a, 'POST', '/api/clients', [], ['name' => 'T'])[1]['client']['id'];
$ids = [];
for ($i = 0; $i < 5; $i++) {
    $ids[] = call($a, 'POST', '/api/appointments', [], ['client_id' => $c, 'scheduled_date' => '2030-01-01'])[1]['appointment']['identifier'];
}
check('identificativi sequenziali', $ids === ['APP-1', 'APP-2', 'APP-3', 'APP-4', 'APP-5'], implode(',', $ids));

// ── Scheduling (unit) ────────────────────────────────────────────────
fwrite(STDOUT, "Scheduling\n");
check('toMinutes valido', App\toMinutes('09:30') === 570);
check('toMinutes non valido', App\toMinutes('24:00') === null && App\toMinutes('9') === null && App\toMinutes(null) === null);
check('addMinutes', App\addMinutes('09:30', 45) === '10:15');
$busy = [['kind' => 'appointment', 'start_time' => '10:00', 'end_time' => '11:00', 'label' => 'A']];
check('intervalli semiaperti', App\findConflicts('11:00', '12:00', $busy) === [] && App\findConflicts('10:59', '12:00', $busy) !== []);
check('candidato non valido → nessun conflitto', App\findConflicts('12:00', '11:00', $busy) === []);

// ── Categorie ────────────────────────────────────────────────────────
fwrite(STDOUT, "Categorie di attività\n");
foreach (array_keys(App\Categories::all()) as $key) {
    $x = fresh($key);
    [$s, $d] = call($x, 'GET', '/api/services');
    check("$key: servizi di partenza", $s === 200 && count($d['services']) >= 3);
    [$s, $d] = call($x, 'GET', '/api/account');
    check("$key: termini dell'interfaccia", $d['account']['category'] === $key && !empty($d['account']['terms']['service']) && !empty($d['account']['terms']['staffs']));
}
$m = fresh('meccanico');
check('meccanico: interventi e ricambi', call($m, 'GET', '/api/account')[1]['account']['terms']['products'] === 'Ricambi');
check('categoria sconosciuta → generica', App\Categories::get('boh')['label'] === 'Altra attività' && !App\Categories::valid('boh'));
[$s, $d] = call($m, 'PUT', '/api/account', [], ['category' => 'tattoo', 'business_name' => 'Inchiostro']);
check('cambio categoria e nome', $s === 200 && $d['account']['terms']['staffs'] === 'Tatuatori' && $d['account']['business_name'] === 'Inchiostro');
check('categoria non valida rifiutata', call($m, 'PUT', '/api/account', [], ['category' => 'xx'])[0] === 400);
check('nome vuoto rifiutato', call($m, 'PUT', '/api/account', [], ['business_name' => '  '])[0] === 400);

// ── Più negozi: i dati restano separati ──────────────────────────────
fwrite(STDOUT, "Isolamento tra negozi\n");
$db = newDb();
$idA = addAccount($db, 'a@example.com');
$idB = addAccount($db, 'b@example.com', 'tattoo');
$A = new Api($db, $idA);
$B = new Api($db, $idB);
$ca = call($A, 'POST', '/api/clients', [], ['name' => 'Cliente di A'])[1]['client']['id'];
$cb = call($B, 'POST', '/api/clients', [], ['name' => 'Cliente di B'])[1]['client']['id'];
$staffA = call($A, 'GET', '/api/staff/all')[1]['staff'][0]['id'];
$staffB = call($B, 'GET', '/api/staff/all')[1]['staff'][0]['id'];
$svcA = call($A, 'GET', '/api/services')[1]['services'][0]['id'];
$aptA = call($A, 'POST', '/api/appointments', [], ['client_id' => $ca, 'staff_id' => $staffA, 'scheduled_date' => '2030-05-01', 'start_time' => '10:00', 'service_ids' => [$svcA]])[1]['appointment'];
check('B vede solo i propri clienti', call($B, 'GET', '/api/clients')[1]['total'] === 1 && call($B, 'GET', '/api/clients')[1]['clients'][0]['name'] === 'Cliente di B');
check('B non legge il cliente di A', call($B, 'GET', "/api/clients/$ca")[0] === 404);
check('B non legge l\'appuntamento di A', call($B, 'GET', "/api/appointments/{$aptA['id']}")[0] === 404);
check('B non vede appuntamenti di A in lista/agenda', call($B, 'GET', '/api/appointments')[1]['total'] === 0
    && call($B, 'GET', '/api/calendar', ['start' => '2030-05-01', 'end' => '2030-05-01'])[1]['appointments'] === []);
[$s] = call($B, 'POST', '/api/appointments', [], ['client_id' => $ca, 'scheduled_date' => '2030-05-02']);
check('B non prenota con un cliente di A', $s === 400);
[$s] = call($B, 'POST', '/api/appointments', [], ['client_id' => $cb, 'staff_id' => $staffA, 'scheduled_date' => '2030-05-02']);
check('B non usa un collaboratore di A', $s === 400);
[$s] = call($B, 'POST', '/api/appointments', [], ['client_id' => $cb, 'scheduled_date' => '2030-05-02', 'service_ids' => [$svcA]]);
check('B non usa un servizio di A', $s === 400);
$apt = call($B, 'POST', '/api/appointments', [], ['client_id' => $cb, 'scheduled_date' => '2030-05-02'])[1]['appointment'];
check('contatori indipendenti', $apt['identifier'] === 'APP-1' && $aptA['identifier'] === 'APP-1');
call($B, 'PUT', "/api/clients/$ca", [], ['name' => 'Hackerato']);
call($B, 'DELETE', "/api/clients/$ca");
call($B, 'PUT', "/api/appointments/{$aptA['id']}", [], ['status' => 'cancelled']);
call($B, 'DELETE', "/api/appointments/{$aptA['id']}");
call($B, 'DELETE', "/api/staff/$staffA");
call($B, 'DELETE', "/api/services/$svcA");
call($B, 'POST', "/api/appointments/{$aptA['id']}/notes", [], ['content' => 'x']);
check('modifiche/eliminazioni di B non toccano i dati di A',
    call($A, 'GET', "/api/clients/$ca")[1]['client']['name'] === 'Cliente di A'
    && call($A, 'GET', "/api/appointments/{$aptA['id']}")[1]['appointment']['status'] === 'booked'
    && count(call($A, 'GET', '/api/staff/all')[1]['staff']) === 1
    && count(call($A, 'GET', '/api/services')[1]['services']) === 8);
$noteB = call($A, 'POST', "/api/appointments/{$aptA['id']}/notes", [], ['content' => 'nota di A'])[0] === 201;
$noteId = call($A, 'GET', "/api/appointments/{$aptA['id']}")[1]['appointment']['appointment_notes'][0]['id'];
call($B, 'DELETE', "/api/notes/$noteId");
check('B non cancella le note di A', count(call($A, 'GET', "/api/appointments/{$aptA['id']}")[1]['appointment']['appointment_notes']) === 1);
[$s] = call($B, 'POST', '/api/blocked-slots', [], ['staff_id' => $staffA, 'blocked_date' => '2030-05-01', 'start_time' => '09:00', 'end_time' => '10:00']);
check('B non blocca orari di un collaboratore di A', $s === 400);
check('statistiche separate', call($A, 'GET', '/api/stats')[1]['clients'] === 1 && call($B, 'GET', '/api/stats')[1]['appointments'] === 1);
check('stessa email non registrabile due volte', (function () use ($db) { try { addAccount($db, 'a@example.com'); return false; } catch (Throwable) { return true; } })());

// ── Registrazione e accesso ──────────────────────────────────────────
fwrite(STDOUT, "Registrazione e accesso\n");
$db = newDb();
$cfg = ['trial_days' => 30];
check('password troppo corta', !Auth::register($db, $cfg, 'Salone', 'parrucchiere', 'x@example.com', 'corta')['ok']);
check('email non valida', !Auth::register($db, $cfg, 'Salone', 'parrucchiere', 'non-una-email', 'password123')['ok']);
check('nome vuoto', !Auth::register($db, $cfg, ' ', 'parrucchiere', 'x@example.com', 'password123')['ok']);
check('categoria non valida', !Auth::register($db, $cfg, 'Salone', 'xx', 'x@example.com', 'password123')['ok']);
$r = Auth::register($db, $cfg, 'Officina Rossi', 'meccanico', 'Rossi@Example.com', 'password123');
check('registrazione riuscita', $r['ok'] && Auth::accountId() === $r['id']);
check('email salvata in minuscolo', $db->row('SELECT email FROM accounts WHERE id = ?', [$r['id']])['email'] === 'rossi@example.com');
check('servizi e collaboratore creati', $db->row('SELECT COUNT(*) AS c FROM services WHERE account_id = ?', [$r['id']])['c'] >= 5
    && $db->row('SELECT title FROM staff WHERE account_id = ?', [$r['id']])['title'] === 'Meccanico');
check('email duplicata rifiutata', !Auth::register($db, $cfg, 'Altro', 'altro', 'rossi@example.com', 'password123')['ok']);
Auth::logout();
check('uscita dalla sessione', Auth::accountId() === null);
check('accesso con password giusta', Auth::login($db, ' ROSSI@example.com ', 'password123')['ok'] && Auth::accountId() === $r['id']);
Auth::logout();
check('password errata', !Auth::login($db, 'rossi@example.com', 'sbagliata')['ok'] && Auth::accountId() === null);
check('email sconosciuta', !Auth::login($db, 'nessuno@example.com', 'password123')['ok']);
check('cambio password: attuale errata', !Auth::changePassword($db, $r['id'], 'no', 'nuovapassword1')['ok']);
check('cambio password: nuova corta', !Auth::changePassword($db, $r['id'], 'password123', 'x')['ok']);
check('cambio password riuscito', Auth::changePassword($db, $r['id'], 'password123', 'nuovapassword1')['ok']
    && Auth::login($db, 'rossi@example.com', 'nuovapassword1')['ok']);
$api = new Api($db, $r['id']);
[$s] = call($api, 'POST', '/api/account/password', [], ['current_password' => 'nuovapassword1', 'new_password' => 'terzapassword1']);
check('cambio password da API', $s === 200 && call($api, 'POST', '/api/account/password', [], ['current_password' => 'x', 'new_password' => 'terzapassword1'])[0] === 400);

// ── Prova gratuita e abbonamento ─────────────────────────────────────
fwrite(STDOUT, "Prova gratuita e abbonamento\n");
$now = strtotime('2030-01-10 12:00:00');
$trial = ['plan_status' => 'trial', 'trial_ends_at' => '2030-02-09 12:00:00', 'paid_until' => ''];
$p = Auth::plan($trial, $now);
check('prova: 30 giorni rimasti', $p['state'] === 'trial' && $p['days_left'] === 30);
check('prova scaduta', Auth::plan($trial, strtotime('2030-02-09 12:00:01'))['state'] === 'expired');
check('abbonamento attivo con rinnovo futuro', Auth::plan(['plan_status' => 'active', 'paid_until' => '2030-03-01 00:00:00', 'trial_ends_at' => ''], $now)['state'] === 'active');
check('abbonamento: 3 giorni di tolleranza', Auth::plan(['plan_status' => 'active', 'paid_until' => '2030-01-08 12:00:00', 'trial_ends_at' => ''], $now)['state'] === 'active'
    && Auth::plan(['plan_status' => 'active', 'paid_until' => '2030-01-01 12:00:00', 'trial_ends_at' => ''], $now)['state'] === 'expired');
check('stato scaduto', Auth::plan(['plan_status' => 'expired'], $now)['state'] === 'expired');
check('account appena creato è in prova', call($api, 'GET', '/api/account')[1]['account']['plan']['state'] === 'trial');

$secret = 'whsec_test';
$payload = '{"id":"evt_1","type":"checkout.session.completed"}';
$sig = 't=' . $now . ',v1=' . hash_hmac('sha256', $now . '.' . $payload, $secret);
check('firma Stripe valida', Billing::verifySignature($payload, $sig, $secret, $now));
check('firma Stripe errata', !Billing::verifySignature($payload, $sig, 'altro', $now) && !Billing::verifySignature($payload . 'x', $sig, $secret, $now));
check('firma Stripe scaduta', !Billing::verifySignature($payload, $sig, $secret, $now + 1000));
check('firma Stripe mancante', !Billing::verifySignature($payload, '', $secret, $now));
check('pagamenti disattivi senza chiavi', !Billing::enabled([]) && Billing::enabled(['stripe' => ['secret_key' => 'sk', 'webhook_secret' => 'wh', 'price_monthly' => 'price_1']]));

$db->exec("UPDATE accounts SET trial_ends_at = '2000-01-01 00:00:00' WHERE id = ?", [$r['id']]);
check('prova scaduta → scaduto', Auth::plan(Auth::account($db, $r['id']))['state'] === 'expired');
Billing::handleEvent($db, ['type' => 'checkout.session.completed', 'data' => ['object' => ['mode' => 'subscription', 'client_reference_id' => (string)$r['id'], 'customer' => 'cus_1', 'subscription' => 'sub_1']]]);
check('pagamento completato → attivo', Auth::plan(Auth::account($db, $r['id']))['state'] === 'active'
    && Auth::account($db, $r['id'])['stripe_customer_id'] === 'cus_1');
$end = time() + 30 * 86400;
Billing::handleEvent($db, ['type' => 'customer.subscription.updated', 'data' => ['object' => ['id' => 'sub_1', 'customer' => 'cus_1', 'status' => 'active', 'current_period_end' => $end]]]);
check('rinnovo aggiorna la scadenza', Auth::account($db, $r['id'])['paid_until'] === date('Y-m-d H:i:s', $end));
Billing::handleEvent($db, ['type' => 'customer.subscription.updated', 'data' => ['object' => ['id' => 'sub_1', 'customer' => 'cus_1', 'status' => 'past_due', 'current_period_end' => $end]]]);
check('pagamento fallito → scaduto', Auth::account($db, $r['id'])['plan_status'] === 'expired');
Billing::handleEvent($db, ['type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_sconosciuta', 'customer' => 'cus_x', 'status' => 'canceled']]]);
check('evento di un abbonamento sconosciuto ignorato', true);

// ── Pagine pubbliche ─────────────────────────────────────────────────
fwrite(STDOUT, "Pagine pubbliche\n");
$pub = ['site_name' => 'Appuntamenti4me', 'trial_days' => 30, 'pricing' => ['monthly_label' => '19 € al mese'], 'terms_url' => 'https://example.com/termini'];
$land = Pages::landing($pub);
check('vetrina: prova e categorie', str_contains($land, '30 giorni') && str_contains($land, 'Officina meccanica') && str_contains($land, 'Studio tatuaggi') && str_contains($land, '19 € al mese'));
$reg = Pages::register($pub, 'Errore <b>x</b>', ['business' => '"><script>', 'email' => 'a@b.it', 'category' => 'tattoo']);
check('registrazione: moduli e termini', str_contains($reg, 'name="category"') && str_contains($reg, 'name="terms"') && str_contains($reg, 'name="website"'));
check('registrazione escapa l\'HTML', !str_contains($reg, '<script>') && !str_contains($reg, '<b>x</b>'));
check('registrazione ricorda la categoria', str_contains($reg, 'value="tattoo" selected'));
check('accesso escapa l\'HTML', !str_contains(Pages::login($pub, '<i>x</i>', '"><script>'), '<script>'));
$acct = Auth::account($db, $r['id']);
$bill = Pages::billing(['stripe' => ['secret_key' => 'sk', 'webhook_secret' => 'wh', 'price_monthly' => 'price_1', 'price_yearly' => 'price_2']] + $pub, ['plan_status' => 'expired'] + $acct, ['state' => 'expired', 'days_left' => 0, 'ends_at' => ''], null, null);
check('abbonamento scaduto: pulsanti di acquisto', str_contains($bill, 'terminata') && str_contains($bill, 'value="mensile"') && str_contains($bill, 'value="annuale"'));
check('abbonamento senza Stripe: messaggio', str_contains(Pages::billing($pub, $acct, ['state' => 'trial', 'days_left' => 5, 'ends_at' => '2030-01-01 00:00:00']), 'non sono ancora attivi'));

// ── Aggiornamento da una versione senza account ──────────────────────
fwrite(STDOUT, "Aggiornamento da versione precedente\n");
$old = Db::connect(['driver' => 'sqlite', 'path' => ':memory:']);
foreach ([
    "CREATE TABLE clients (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL DEFAULT '', phone TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE staff (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL DEFAULT '', phone TEXT NOT NULL DEFAULT '', title TEXT NOT NULL DEFAULT '', color TEXT NOT NULL DEFAULT '#7c3aed', active INTEGER NOT NULL DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE services (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT NOT NULL DEFAULT '', duration INTEGER NOT NULL DEFAULT 60, price REAL NOT NULL DEFAULT 0, color TEXT NOT NULL DEFAULT '#6b7280', category TEXT NOT NULL DEFAULT '', active INTEGER NOT NULL DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE appointments (id INTEGER PRIMARY KEY AUTOINCREMENT, identifier TEXT NOT NULL UNIQUE, client_id INTEGER NOT NULL, staff_id INTEGER NULL, status TEXT NOT NULL DEFAULT 'booked', scheduled_date TEXT NOT NULL, start_time TEXT NOT NULL DEFAULT '09:00', end_time TEXT NOT NULL DEFAULT '10:00', total_price REAL NOT NULL DEFAULT 0, notes TEXT NOT NULL DEFAULT '', is_recurring INTEGER NOT NULL DEFAULT 0, recurrence_interval TEXT NOT NULL DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE appointment_services (id INTEGER PRIMARY KEY AUTOINCREMENT, appointment_id INTEGER NOT NULL, service_id INTEGER NOT NULL, price REAL NOT NULL DEFAULT 0, duration INTEGER NOT NULL DEFAULT 60)",
    "CREATE TABLE appointment_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, appointment_id INTEGER NOT NULL, content TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE blocked_slots (id INTEGER PRIMARY KEY AUTOINCREMENT, staff_id INTEGER NOT NULL, blocked_date TEXT NOT NULL, start_time TEXT NOT NULL, end_time TEXT NOT NULL, reason TEXT NOT NULL DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, brand TEXT NOT NULL DEFAULT '', category TEXT NOT NULL DEFAULT '', sku TEXT NOT NULL DEFAULT '', price REAL NOT NULL DEFAULT 0, cost REAL NOT NULL DEFAULT 0, stock INTEGER NOT NULL DEFAULT 0, low_stock_alert INTEGER NOT NULL DEFAULT 5, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE _meta (meta_key TEXT NOT NULL PRIMARY KEY, meta_value TEXT NOT NULL)",
    "INSERT INTO _meta VALUES ('appointment_counter', '7')",
    "INSERT INTO clients (name) VALUES ('Cliente storico')",
    "INSERT INTO staff (name) VALUES ('Giulia')",
    "INSERT INTO services (name) VALUES ('Taglio')",
    "INSERT INTO appointments (identifier, client_id, scheduled_date) VALUES ('APP-7', 1, '2030-01-01')",
] as $sql) {
    $old->exec($sql);
}
Schema::ensure($old);
check('migrazione: versione e tabella account', $old->metaGet('schema_version') === '2' && $old->tableExists('accounts') && $old->columnExists('clients', 'account_id'));
check('migrazione: dati esistenti senza proprietario', Schema::hasUnownedData($old));
Schema::ensure($old);
check('migrazione ripetibile', $old->metaGet('schema_version') === '2');
$first = Auth::register($old, ['trial_days' => 30], 'Salone Giulia', 'parrucchiere', 'giulia@example.com', 'password123');
check('primo negozio eredita i dati', $first['ok'] && !Schema::hasUnownedData($old)
    && $old->row('SELECT COUNT(*) AS c FROM clients WHERE account_id = ?', [$first['id']])['c'] === 1
    && $old->row('SELECT COUNT(*) AS c FROM services WHERE account_id = ?', [$first['id']])['c'] === 1);
$oldApi = new Api($old, $first['id']);
$cl = call($oldApi, 'GET', '/api/clients/all')[1]['clients'][0]['id'];
check('contatore prosegue dopo la migrazione', call($oldApi, 'POST', '/api/appointments', [], ['client_id' => $cl, 'scheduled_date' => '2030-02-01'])[1]['appointment']['identifier'] === 'APP-8');
Auth::logout();
$second = Auth::register($old, ['trial_days' => 30], 'Tattoo Joe', 'tattoo', 'joe@example.com', 'password123');
check('secondo negozio parte pulito con i suoi servizi', $second['ok']
    && $old->row('SELECT COUNT(*) AS c FROM clients WHERE account_id = ?', [$second['id']])['c'] === 0
    && $old->row('SELECT COUNT(*) AS c FROM services WHERE account_id = ?', [$second['id']])['c'] === 7);
Auth::logout();


fwrite(STDOUT, "\n$passed ok, $failed falliti\n");
exit($failed === 0 ? 0 : 1);
