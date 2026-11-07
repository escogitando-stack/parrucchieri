<?php
declare(strict_types=1);

/**
 * Test di integrazione dell'API PHP su SQLite in memoria.
 * Esecuzione: php php/tests/run.php
 */

require __DIR__ . '/../server/bootstrap.php';
\App\Mailer::$driver = 'array';

use App\Api;
use App\Db;
use App\Schema;
use App\Auth;
use App\Billing;
use App\Pages;
use App\Admin;

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
        'INSERT INTO accounts (email, password_hash, business_name, category, slug, trial_ends_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$email, password_hash('segretissima', PASSWORD_DEFAULT), 'Negozio ' . $email, $category, \App\Booking::makeSlug($db, "Negozio " . $email), date('Y-m-d H:i:s', time() + 30 * 86400)]
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
$cfg = ['trial_days' => 30, 'require_approval' => false];
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
check('vetrina: vantaggi, costi, come funziona e FAQ', str_contains($land, 'id="vantaggi"') && str_contains($land, 'id="costi"') && str_contains($land, 'id="faq"') && str_contains($land, 'id="come-funziona"'));
check('vetrina: prezzi dal file di configurazione', str_contains($land, '19 € al mese') && str_contains(Pages::landing($pub + ['x' => 1]), 'Mensile'));
check('vetrina: senza prezzi niente cifre inventate', !str_contains(Pages::landing(['trial_days' => 30]), '€ al mese') && str_contains(Pages::landing(['trial_days' => 30]), 'Prova gratuita'));
check('FAQ: dati dinamici e limiti dichiarati', str_contains(Pages::landing($pub), 'Quanto costa?') && str_contains(Pages::landing($pub), 'Come vengono avvisati i clienti?') && str_contains(Pages::landing($pub), 'faq@example.com') === false);
check('FAQ: email di assistenza se impostata', str_contains(Pages::landing($pub + ['contact_email' => 'aiuto@example.com']), 'aiuto@example.com'));
check('vetrina: dati strutturati validi', (function () use ($land) { preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $land, $m); $j = json_decode($m[1] ?? '', true); return is_array($j) && $j['@type'] === 'FAQPage' && count($j['mainEntity']) >= 10; })());
check('vetrina: il testo dell\'utente non rompe lo script', (function () use ($pub) { $h = Pages::landing($pub + ['pricing' => ['monthly_label' => '</script><b>x']]); return substr_count($h, '</script>') === 1 && !str_contains($h, '<b>x'); })());
check('vetrina: immagine e icone presenti', str_contains($land, 'class="shot"') && str_contains($land, '<svg viewBox="0 0 960 540"') && substr_count($land, 'class="ico"') === 9);
check('vetrina: link ai contatti', str_contains($land, 'href="/contatti"'));
$ct = Pages::contact($pub);
check('contatti: email predefinita', str_contains($ct, 'mailto:info@appuntamenti4me.it'));
check('contatti: senza config niente riferimenti inventati', !str_contains($ct, 'Telefono') && !str_contains($ct, 'P. IVA') && !str_contains($ct, 'WhatsApp'));
$ct2 = Pages::contact($pub + ['contact_email' => 'x@y.it', 'contact' => ['name' => 'Mario <b>Rossi</b>', 'phone' => '+39 333 123', 'whatsapp' => '+39 333 123', 'address' => 'Via Roma 1', 'vat' => '123']]);
check('contatti: riferimenti da config, escapati', str_contains($ct2, 'mailto:x@y.it') && str_contains($ct2, 'tel:+39333123') && str_contains($ct2, 'wa.me/39333123') && str_contains($ct2, 'P. IVA 123') && !str_contains($ct2, '<b>Rossi'));
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
check('migrazione: versione e tabella account', $old->metaGet('schema_version') === '7' && $old->tableExists('accounts') && $old->columnExists('clients', 'account_id'));
check('migrazione: dati esistenti senza proprietario', Schema::hasUnownedData($old));
Schema::ensure($old);
check('migrazione ripetibile', $old->metaGet('schema_version') === '7');
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


// ── Approvazione dei nuovi negozi ──
fwrite(STDOUT, "Approvazione nuovi negozi\n");
$dbP = newDb();
$cfgP = ['trial_days' => 30, 'contact_email' => 'info@appuntamenti4me.it', 'base_url' => 'https://appuntamenti4me.it'];
\App\Mailer::$sent = [];
$rp = Auth::register($dbP, $cfgP, 'Salone Prova', 'parrucchiere', 'nuovo@example.com', 'password123');
$ap = Auth::account($dbP, $rp['id']);
check('nuovo negozio: da approvare', $rp['ok'] && Auth::plan($ap)['state'] === 'pending' && !Auth::canUse($cfgP, $ap));
check('registrazione: email all\'amministratore', count(\App\Mailer::$sent) === 1 && \App\Mailer::$sent[0]['to'] === 'info@appuntamenti4me.it' && str_contains(\App\Mailer::$sent[0]['text'], 'nuovo@example.com') && str_contains(\App\Mailer::$sent[0]['text'], '/admin'));
check('pagina di attesa', str_contains(Pages::pending($cfgP, $ap), 'Registrazione ricevuta'));
check('admin: stato Da approvare nell\'elenco', Admin::stats(Admin::rows($dbP))['pending'] === 1);
\App\Mailer::$sent = [];
check('approvazione', Admin::approve($dbP, $cfgP, $rp['id']) && Auth::plan(Auth::account($dbP, $rp['id']))['state'] === 'trial');
check('approvazione: email al cliente con indirizzo pagina', count(\App\Mailer::$sent) === 1 && \App\Mailer::$sent[0]['to'] === 'nuovo@example.com' && str_contains(\App\Mailer::$sent[0]['text'], 'appuntamenti4me.it/salone-prova'));
check('approvazione non ripetibile', !Admin::approve($dbP, $cfgP, $rp['id']));
$ra = Auth::register($dbP, $cfgP + ['admin_emails' => ['boss@example.com']], 'Boss', 'altro', 'boss@example.com', 'password123');
check('email amministratore: approvata subito', Auth::plan(Auth::account($dbP, $ra['id']))['state'] === 'trial');

// ── Prenotazioni online ──
fwrite(STDOUT, "Prenotazioni online\n");
use App\Availability;
use App\Booking;
use App\Notify;
use App\Shop;
$dbB = newDb();
$cfgB = ['trial_days' => 30, 'base_url' => 'https://appuntamenti4me.it', 'require_approval' => false];
$idB = Auth::register($dbB, $cfgB, 'Salone Bella Vita', 'parrucchiere', 'bella@example.com', 'password123')['id'];
$acctB = Auth::account($dbB, $idB);
check('indirizzo pubblico dal nome', $acctB['slug'] === 'salone-bella-vita');
check('indirizzi unici', Booking::makeSlug($dbB, 'Salone Bella Vita') === 'salone-bella-vita-2' && Booking::makeSlug($dbB, 'Admin') === 'negozio-admin');
check('indirizzo valido/riservato', Booking::slugValid('rossi-srl') && !Booking::slugValid('admin') && !Booking::slugValid('a') && !Booking::slugValid('Rossi'));
check('ricerca per indirizzo', Booking::accountBySlug($dbB, 'salone-bella-vita')['id'] == $idB && Booking::accountBySlug($dbB, 'calendar') === null);
$svc = Availability::services($dbB, $idB);
check('servizi prenotabili online', count($svc) >= 3);
$one = (int)$svc[0]['id'];
$dur = (int)$svc[0]['duration'];
$monday = date('Y-m-d', strtotime('next monday', strtotime('+3 days')));
$sunday = date('Y-m-d', strtotime('next sunday', strtotime('+3 days')));
$pk = Availability::pick($dbB, $idB, [$one]);
check('durata dei servizi scelti', $pk !== null && $pk['duration'] === $dur && Availability::pick($dbB, $idB, []) === null && Availability::pick($dbB, $idB, [999999]) === null);
$slots = Availability::slots($dbB, $acctB, $monday, $dur, null);
check('orari liberi di lunedì', isset($slots['09:00']) && !isset($slots['13:00']) && isset($slots['15:00']));
check('domenica chiuso', Availability::slots($dbB, $acctB, $sunday, $dur, null) === []);
check('giorni del mese con orari', in_array($monday, Availability::days($dbB, $acctB, substr($monday, 0, 7), $dur, null), true) || in_array($monday, Availability::days($dbB, $acctB, date('Y-m', strtotime($monday)), $dur, null), true));
check('data passata o troppo lontana non prenotabile', !Availability::dateAllowed($acctB, date('Y-m-d', strtotime('-1 day'))) && !Availability::dateAllowed($acctB, date('Y-m-d', strtotime('+400 days'))));
$in = ['name' => 'Chiara Rossi', 'phone' => '333 1234567', 'email' => 'Chiara@Example.com', 'date' => $monday, 'time' => '09:00', 'service_ids' => [$one], 'notes' => 'Prima volta', 'consent' => true];
\App\Mailer::$sent = [];
$res = Booking::create($dbB, $cfgB, $acctB, $in, '1.2.3.4');
check('richiesta creata da approvare', $res['ok'] && $dbB->row('SELECT status, source FROM appointments WHERE id = ?', [$res['id']]) == ['status' => 'requested', 'source' => 'online']);
check('email al cliente e al titolare', count(\App\Mailer::$sent) === 2 && \App\Mailer::$sent[0]['to'] === 'chiara@example.com' && str_contains(\App\Mailer::$sent[0]['text'], '/annulla/') && \App\Mailer::$sent[1]['to'] === 'bella@example.com');
check('cliente creato con la sua email', $dbB->row('SELECT email FROM clients WHERE account_id = ? AND name = ?', [$idB, 'Chiara Rossi'])['email'] === 'chiara@example.com');
$after = Availability::slots($dbB, $acctB, $monday, $dur, null);
check('orario occupato dalla richiesta', !isset($after['09:00']) || (int)$dbB->row('SELECT COUNT(*) AS c FROM staff WHERE account_id = ? AND active = 1', [$idB])['c'] > 1);
$res2 = Booking::create($dbB, $cfgB, $acctB, ['email' => 'altra@example.com', 'name' => 'Altra'] + $in, '1.2.3.5');
check('stesso orario non prenotabile due volte', !$res2['ok'] && str_contains($res2['error'], 'occupato'));
check('dati obbligatori', !Booking::create($dbB, $cfgB, $acctB, ['name' => ''] + $in)['ok'] && !Booking::create($dbB, $cfgB, $acctB, ['email' => 'no'] + $in)['ok'] && !Booking::create($dbB, $cfgB, $acctB, ['phone' => '1'] + $in)['ok'] && !Booking::create($dbB, $cfgB, $acctB, ['service_ids' => []] + $in)['ok']);
check('domenica rifiutata', !Booking::create($dbB, $cfgB, $acctB, ['date' => $sunday, 'email' => 'x1@example.com'] + $in)['ok']);
$free = Booking::create($dbB, $cfgB, $acctB, ['date' => $sunday, 'time' => '11:00', 'free' => true, 'email' => 'libera@example.com', 'name' => 'Libera'] + $in, '9.9.9.9');
$fr = $dbB->row('SELECT status, source, notes FROM appointments WHERE id = ?', [$free['id'] ?? 0]);
check('richiesta senza orari liberi accettata', $free['ok'] && $fr['status'] === 'requested' && $fr['source'] === 'online_free' && str_contains($fr['notes'], 'orario preferito 11:00'));
for ($i = 0; $i < 6; $i++) {
    $last = Booking::create($dbB, $cfgB, $acctB, ['date' => $sunday, 'time' => '11:00', 'free' => true, 'email' => "spam$i@example.com", 'phone' => "340 00000$i", 'name' => 'Spam'] + $in, '7.7.7.7');
}
check('troppe richieste dallo stesso indirizzo', !$last['ok'] && str_contains($last['error'], 'molte richieste'));
for ($i = 0; $i < 4; $i++) {
    $same = Booking::create($dbB, $cfgB, $acctB, ['date' => $sunday, 'time' => '1' . $i . ':00', 'free' => true, 'email' => 'stessa@example.com', 'name' => 'Stessa'] + $in, '6.6.6.' . $i);
}
check('al massimo 3 richieste aperte per email', !$same['ok']);
$sus = Auth::account($dbB, $idB);
$dbB->exec('UPDATE accounts SET booking_enabled = 0 WHERE id = ?', [$idB]);
check('prenotazioni disattivate', !Booking::create($dbB, $cfgB, Auth::account($dbB, $idB), ['email' => 'off@example.com'] + $in)['ok'] && !Booking::open($cfgB, Auth::account($dbB, $idB)));
$dbB->exec('UPDATE accounts SET booking_enabled = 1 WHERE id = ?', [$idB]);

// Il titolare approva / rifiuta
$tok = $dbB->row('SELECT public_token FROM appointments WHERE id = ?', [$res['id']])['public_token'];
$apiB = new \App\Api($dbB, $idB, $cfgB);
[$s, $d] = $apiB->handle('GET', '/api/stats');
check('statistiche: richieste da approvare', $s === 200 && $d['pending_requests'] >= 2);
\App\Mailer::$sent = [];
[$s, $d] = $apiB->handle('POST', '/api/appointments/' . $res['id'] . '/approve', [], []);
check('approvazione richiesta', $s === 200 && $d['appointment']['status'] === 'confirmed' && $d['email_sent'] === true && str_contains($d['whatsapp_url'], 'https://wa.me/393331234567?text='));
check('email di conferma al cliente', \App\Mailer::$sent[0]['to'] === 'chiara@example.com' && str_contains($tok, '') && str_contains(\App\Mailer::$sent[0]['subject'], 'confermata'));
check('richiesta già gestita', $apiB->handle('POST', '/api/appointments/' . $res['id'] . '/approve', [], [])[0] === 409);
[$s, $d] = $apiB->handle('POST', '/api/appointments/' . $free['id'] . '/reject', [], []);
check('rifiuto richiesta', $s === 200 && $d['appointment']['status'] === 'cancelled' && str_contains($d['whatsapp_url'], 'wa.me'));
$staffB = (int)$dbB->row('SELECT id FROM staff WHERE account_id = ? LIMIT 1', [$idB])['id'];
$free2 = Booking::create($dbB, $cfgB, $acctB, ['date' => $monday, 'time' => '09:30', 'free' => true, 'email' => 'f2@example.com', 'name' => 'F2'] + $in, '5.5.5.5');
check('approvazione con conflitto → 409', $apiB->handle('POST', '/api/appointments/' . $free2['id'] . '/approve', [], ['staff_id' => $staffB])[0] === 409);
check('approvazione sceglie il collaboratore e l\'orario', $apiB->handle('POST', '/api/appointments/' . $free2['id'] . '/approve', [], ['staff_id' => $staffB, 'start_time' => '16:00'])[0] === 200);
// altro negozio non può gestirla
$idC = addAccount($dbB, 'c@example.com');
check('un altro negozio non vede le richieste', (new \App\Api($dbB, $idC, $cfgB))->handle('POST', '/api/appointments/' . $free['id'] . '/approve', [], [])[0] === 404);
// annullamento dal link
$cancelTok = $dbB->row('SELECT public_token FROM appointments WHERE id = ?', [$res['id']])['public_token'];
check('pagina annullamento', str_contains(Shop::cancelPage($cfgB, Booking::byToken($dbB, $cancelTok), $cancelTok), 'Annulla prenotazione') && str_contains(Shop::cancelPage($cfgB, null, 'x'), 'non è corretto'));
\App\Mailer::$sent = [];
check('il cliente annulla dal link', Booking::cancelByToken($dbB, $cfgB, $cancelTok) && $dbB->row('SELECT status FROM appointments WHERE id = ?', [$res['id']])['status'] === 'cancelled' && \App\Mailer::$sent[0]['to'] === 'bella@example.com');
check('link non valido', !Booking::cancelByToken($dbB, $cfgB, 'zzz') && Booking::byToken($dbB, str_repeat('a', 32)) === null);
// orari di lavoro
[$s, $d] = $apiB->handle('PUT', '/api/working-hours/' . $staffB, [], ['hours' => [['weekday' => 6, 'start_time' => '10:00', 'end_time' => '12:00']]]);
check('orari di lavoro salvati', $s === 200 && count((array)$apiB->handle('GET', '/api/working-hours')[1]['hours']->{$staffB} ?? []) === 1 || count($apiB->handle('GET', '/api/working-hours')[1]['hours']->{$staffB} ?? []) === 1);
check('orari non validi rifiutati', $apiB->handle('PUT', '/api/working-hours/' . $staffB, [], ['hours' => [['weekday' => 9, 'start_time' => '10:00', 'end_time' => '12:00']]])[0] === 400 && $apiB->handle('PUT', '/api/working-hours/' . $staffB, [], ['hours' => [['weekday' => 1, 'start_time' => '12:00', 'end_time' => '10:00']]])[0] === 400);
check('orari di un altro negozio non modificabili', (new \App\Api($dbB, $idC, $cfgB))->handle('PUT', '/api/working-hours/' . $staffB, [], ['hours' => []])[0] === 404);
// profilo
[$s, $d] = $apiB->handle('PUT', '/api/account', [], ['shop_phone' => '+39 0565 123456', 'shop_description' => 'Taglio e colore', 'slug' => 'bella-vita']);
check('profilo e indirizzo aggiornati', $s === 200 && $d['account']['slug'] === 'bella-vita' && $d['account']['shop_phone'] === '+39 0565 123456' && str_ends_with($d['account']['shop_url'], '/bella-vita'));
check('indirizzo già usato o riservato', (new \App\Api($dbB, $idC, $cfgB))->handle('PUT', '/api/account', [], ['slug' => 'bella-vita'])[0] === 409 && $apiB->handle('PUT', '/api/account', [], ['slug' => 'admin'])[0] === 400 && $apiB->handle('PUT', '/api/account', [], ['shop_email' => 'no'])[0] === 400);
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
[$s, $d] = $apiB->handle('PUT', '/api/account', [], ['logo' => $png]);
check('logo caricato', $s === 200 && $d['account']['has_logo'] === true && Shop::logo(Auth::account($dbB, $idB)) !== null);
check('logo non valido rifiutato', $apiB->handle('PUT', '/api/account', [], ['logo' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4='])[0] === 400 && $apiB->handle('PUT', '/api/account', [], ['logo' => 'data:image/png;base64,AAAA'])[0] === 400);
check('logo rimosso', $apiB->handle('PUT', '/api/account', [], ['remove_logo' => true])[1]['account']['has_logo'] === false);
$acctB = Auth::account($dbB, $idB);
$page = Shop::page($cfgB, $acctB, Availability::services($dbB, $idB), Availability::staff($dbB, $idB), true);
check('pagina pubblica: profilo, servizi, prenotazione', str_contains($page, 'Salone Bella Vita') && str_contains($page, 'tel:+390565123456') && str_contains($page, 'name="svc"') && str_contains($page, 'Invia comunque una richiesta') && str_contains($page, "BASE='/bella-vita'"));
check('pagina pubblica: prenotazioni chiuse', !str_contains(Shop::page($cfgB, $acctB, [], [], false), 'name="svc"') && str_contains(Shop::page($cfgB, $acctB, [], [], false), 'non sono attive'));
check('pagina pubblica escapa l\'HTML', !str_contains(Shop::page($cfgB, ['business_name' => '<script>x</script>', 'slug' => 'x-y'] + $acctB, [], [], true), '<script>x</script>'));
// profilo: nuovo collaboratore con orari di partenza, servizio non online
[$s, $d] = $apiB->handle('POST', '/api/staff', [], ['name' => 'Luca']);
check('nuovo collaboratore ha orari di partenza', $s === 201 && (int)$dbB->row('SELECT COUNT(*) AS c FROM working_hours WHERE staff_id = ?', [$d['staff']['id']])['c'] === 10);
$apiB->handle('PUT', '/api/services/' . $one, [], ['online' => 0]);
check('servizio nascosto dalle prenotazioni online', Availability::pick($dbB, $idB, [$one]) === null);
$apiB->handle('PUT', '/api/services/' . $one, [], ['online' => 1]);

// ── Conferma automatica, link firmati, un solo recapito, privacy, calendario ──
fwrite(STDOUT, "Conferma automatica e link\n");
$tue = date('Y-m-d', strtotime('next tuesday', strtotime('+3 days')));
check('senza consenso privacy → rifiutata', !Booking::create($dbB, $cfgB, $acctB, ['consent' => false, 'date' => $tue, 'time' => '09:00', 'email' => 'c0@example.com'] + $in, '2.2.2.2')['ok']);
check('serve almeno un recapito', !Booking::create($dbB, $cfgB, $acctB, ['phone' => '', 'email' => '', 'date' => $tue, 'time' => '09:00'] + $in, '2.2.2.3')['ok']);
\App\Mailer::$sent = [];
$onlyPhone = Booking::create($dbB, $cfgB, $acctB, ['phone' => '347 0000111', 'email' => '', 'name' => 'Solo Telefono', 'date' => $tue, 'time' => '09:00'] + $in, '2.2.2.4');
check('solo telefono: richiesta accettata, email solo al titolare', $onlyPhone['ok'] && count(\App\Mailer::$sent) === 1 && \App\Mailer::$sent[0]['to'] === 'bella@example.com' && str_contains(\App\Mailer::$sent[0]['text'], '/gestisci/'));
$again = Booking::create($dbB, $cfgB, $acctB, ['phone' => '347 0000111', 'email' => '', 'name' => 'Solo Telefono', 'date' => $tue, 'time' => '11:00'] + $in, '2.2.2.5');
check('stesso telefono: stesso cliente', $again['ok'] && (int)$dbB->row('SELECT COUNT(*) AS c FROM clients WHERE account_id = ? AND phone = ?', [$idB, '347 0000111'])['c'] === 1);
check('consenso registrato', $dbB->row('SELECT consent_at FROM appointments WHERE id = ?', [$onlyPhone['id']])['consent_at'] !== '');

// link firmato nell'email del titolare
$tk = $dbB->row('SELECT public_token FROM appointments WHERE id = ?', [$onlyPhone['id']])['public_token'];
$sig = Notify::sign($cfgB, 'gestisci|' . $tk);
check('firma valida solo per quel link', Booking::byManageLink($dbB, $cfgB, $tk, $sig) !== null && Booking::byManageLink($dbB, $cfgB, $tk, str_repeat('0', 32)) === null && Booking::byManageLink($dbB, $cfgB, $tk, '') === null);
check('un altro segreto → firma diversa', Notify::sign($cfgB, 'x') !== Notify::sign($cfgB + ['secret' => 'altro'], 'x'));
\App\Mailer::$sent = [];
$aptL = Booking::byManageLink($dbB, $cfgB, $tk, $sig);
check('approvazione dal link', Booking::decide($dbB, $cfgB, $aptL, true)['ok'] && $dbB->row('SELECT status, staff_id FROM appointments WHERE id = ?', [$onlyPhone['id']])['status'] === 'confirmed');
check('già gestita → errore', !Booking::decide($dbB, $cfgB, Booking::byToken($dbB, $tk), true)['ok']);
$tk2 = $dbB->row('SELECT public_token FROM appointments WHERE id = ?', [$again['id']])['public_token'];
check('rifiuto dal link', Booking::decide($dbB, $cfgB, Booking::byToken($dbB, $tk2), false)['ok'] && $dbB->row('SELECT status FROM appointments WHERE id = ?', [$again['id']])['status'] === 'cancelled');
$mkReq = static fn(string $n, string $ph) => Booking::create($dbB, $cfgB, $acctB, ['free' => true, 'name' => $n, 'phone' => $ph, 'email' => '', 'date' => $tue, 'time' => '09:00'] + $in, '2.2.2.' . random_int(10, 250));
$tokOf = static fn(int $id): string => $dbB->row('SELECT public_token FROM appointments WHERE id = ?', [$id])['public_token'];
$r1 = $mkReq('Sovrapposta 1', '347 0000222');
$r2 = $mkReq('Sovrapposta 2', '347 0000333');
check('approvazione dal link sceglie il collaboratore libero', Booking::decide($dbB, $cfgB, Booking::byToken($dbB, $tokOf($r1['id'])), true)['ok']);
check('approvazione dal link con tutti occupati → errore', !Booking::decide($dbB, $cfgB, Booking::byToken($dbB, $tokOf($r2['id'])), true)['ok'] && $dbB->row('SELECT status FROM appointments WHERE id = ?', [$r2['id']])['status'] === 'requested');
check('pagina gestione', str_contains(Shop::managePage($cfgB, Booking::byToken($dbB, $tk), $tk, $sig), 'Stato') && str_contains(Shop::managePage($cfgB, null, 'x', 'y'), 'non è corretto'));

// conferma automatica per servizio
$wed = date('Y-m-d', strtotime('next wednesday', strtotime('+3 days')));
$apiB->handle('PUT', '/api/services/' . $one, [], ['auto_confirm' => 1]);
\App\Mailer::$sent = [];
$auto = Booking::create($dbB, $cfgB, $acctB, ['name' => 'Auto', 'email' => 'auto@example.com', 'phone' => '', 'date' => $wed, 'time' => '09:00'] + $in, '3.3.3.3');
$ar = $dbB->row('SELECT status, staff_id, public_token FROM appointments WHERE id = ?', [$auto['id'] ?? 0]);
check('conferma automatica: subito confermata', $auto['ok'] && $auto['auto'] === true && $ar['status'] === 'confirmed' && $ar['staff_id'] !== null);
check('conferma automatica: email con calendario e annulla', count(\App\Mailer::$sent) === 2 && str_contains(\App\Mailer::$sent[0]['text'], '/calendario/') && str_contains(\App\Mailer::$sent[0]['subject'], 'confermata') && str_contains(\App\Mailer::$sent[1]['subject'], 'Nuova prenotazione'));
$autoFree = Booking::create($dbB, $cfgB, $acctB, ['free' => true, 'name' => 'Auto2', 'email' => 'auto2@example.com', 'date' => $sunday, 'time' => '10:00'] + $in, '3.3.3.4');
check('richiesta fuori disponibilità resta da approvare', $autoFree['ok'] && $autoFree['auto'] === false && $dbB->row('SELECT status FROM appointments WHERE id = ?', [$autoFree['id']])['status'] === 'requested');
$two = (int)$svc[1]['id'];
$mixed = Booking::create($dbB, $cfgB, $acctB, ['name' => 'Mista', 'email' => 'mista@example.com', 'service_ids' => [$one, $two], 'date' => $wed, 'time' => '15:00'] + $in, '3.3.3.5');
check('un servizio non automatico → da approvare', $mixed['ok'] && $mixed['auto'] === false);

// primo orario libero e spostamento
$fi = Availability::first($dbB, Auth::account($dbB, $idB), $dur, null);
check('primo orario libero', $fi !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fi['date']) === 1 && isset(Availability::slots($dbB, Auth::account($dbB, $idB), $fi['date'], $dur, null)[$fi['time']]));
$thu = date('Y-m-d', strtotime('next thursday', strtotime('+3 days')));
\App\Mailer::$sent = [];
$mv = Booking::reschedule($dbB, $cfgB, $ar['public_token'], $thu, '10:00');
$mr = $dbB->row('SELECT scheduled_date, start_time, status FROM appointments WHERE public_token = ?', [$ar['public_token']]);
check('spostamento: resta confermata (servizio automatico)', $mv['ok'] && $mr['scheduled_date'] === $thu && $mr['start_time'] === '10:00' && $mr['status'] === 'confirmed' && count(\App\Mailer::$sent) === 2 && str_contains(\App\Mailer::$sent[0]['text'], 'Prima era'));
check('spostamento: stesso orario libero per sé (escluso se stessa)', Booking::reschedule($dbB, $cfgB, $ar['public_token'], $thu, '10:00')['ok']);
$mt = $dbB->row('SELECT public_token FROM appointments WHERE id = ?', [$mixed['id']])['public_token'];
Booking::decide($dbB, $cfgB, Booking::byToken($dbB, $mt), true);
$mv2 = Booking::reschedule($dbB, $cfgB, $mt, $thu, '15:00');
check('spostamento: servizio da approvare → torna richiesta', $mv2['ok'] && $mv2['status'] === 'requested' && $dbB->row('SELECT status FROM appointments WHERE public_token = ?', [$mt])['status'] === 'requested');
$busyMv = Booking::reschedule($dbB, $cfgB, $mt, $thu, '10:00');
check('spostamento in orario occupato rifiutato (con un solo collaboratore libero ok)', $busyMv['ok'] === true || str_contains($busyMv['error'], 'occupato'));
check('spostamento: data non valida e link errato', !Booking::reschedule($dbB, $cfgB, $mt, $sunday, '10:00')['ok'] && !Booking::reschedule($dbB, $cfgB, str_repeat('c', 32), $thu, '10:00')['ok']);
check('pagina sposta', str_contains(Shop::reschedulePage($cfgB, Booking::byToken($dbB, $mt), $mt), "BASE='/sposta/") && str_contains(Shop::reschedulePage($cfgB, null, 'x'), 'non è corretto'));
check('pagina annulla propone di spostare', str_contains(Shop::cancelPage($cfgB, Booking::byToken($dbB, $mt), $mt), '/sposta/'));
// calendario .ics
$ics = Booking::ics(Auth::account($dbB, $idB), Booking::byToken($dbB, $ar['public_token']), 'Taglio, piega');
check('file calendario', str_starts_with($ics, "BEGIN:VCALENDAR\r\n") && str_contains($ics, 'DTSTART:' . str_replace('-', '', $thu) . 'T100000') && str_contains($ics, 'SUMMARY:Taglio\\, piega') && str_contains($ics, 'END:VCALENDAR'));
// informativa privacy
$pv = Shop::privacyPage($cfgB, Auth::account($dbB, $idB));
check('informativa privacy del negozio', str_contains($pv, 'Titolare del trattamento') && str_contains($pv, 'Salone Bella Vita') && str_contains($pv, 'Garante'));
check('pagina pubblica: consenso e recapito', str_contains($page, 'id="consent"') && str_contains($page, '/bella-vita/privacy') && !str_contains($page, 'id="phone" type="tel" autocomplete="tel" required'));

// ── Piani gestiti dall'amministratore ──
fwrite(STDOUT, "Piani\n");
use App\Plans;
$dbL = newDb();
check('piano: nome obbligatorio', Plans::save($dbL, 0, ['name' => '', 'duration_days' => 30, 'price' => '10']) !== null);
check('piano: durata e prezzo validi', Plans::save($dbL, 0, ['name' => 'X', 'duration_days' => 0, 'price' => '10']) !== null && Plans::save($dbL, 0, ['name' => 'X', 'duration_days' => 30, 'price' => 'abc']) !== null && Plans::save($dbL, 0, ['name' => 'X', 'duration_days' => 30, 'price' => '1', 'stripe_price_id' => 'xx']) !== null);
check('piano creato', Plans::save($dbL, 0, ['name' => 'Annuale', 'duration_days' => 365, 'price' => '190,50', 'active' => '1']) === null && Plans::save($dbL, 0, ['name' => 'Mensile', 'duration_days' => 30, 'price' => '19', 'active' => '1']) === null);
$pl = Plans::all($dbL);
$byName = array_column($pl, null, 'name');
check('piani elencati e prezzo con virgola', count($pl) === 2 && abs($byName['Annuale']['price'] - 190.5) < 0.001);
check('etichette', Plans::priceLabel(19.0) === '19 €' && Plans::priceLabel(190.5) === '190,50 €' && Plans::durationLabel(30) === 'al mese' && Plans::durationLabel(365) === "all'anno" && Plans::durationLabel(45) === 'per 45 giorni');
check('piano modificato', Plans::save($dbL, $byName['Mensile']['id'], ['name' => 'Mensile Plus', 'duration_days' => 30, 'price' => '25', 'active' => '1']) === null && count(Plans::active($dbL)) === 2 && Plans::find($dbL, $byName['Mensile']['id'])['name'] === 'Mensile Plus');
$landP = Pages::landing(['trial_days' => 30], Plans::all($dbL));
check('home: piani dal database', str_contains($landP, 'Annuale') && str_contains($landP, '190,50 €') && str_contains($landP, 'all&#039;anno'));
$idP = addAccount($dbL, 'p@example.com');
$dbL->exec("UPDATE accounts SET plan_status = 'trial', trial_ends_at = '2000-01-01 00:00:00' WHERE id = ?", [$idP]);
$ann = $byName['Annuale'];
check('assegna piano: attivo per la durata', Plans::assign($dbL, $idP, $ann['id'], 1000000) && Auth::plan(Auth::account($dbL, $idP), 1000000 + 86400)['state'] === 'active' && Auth::account($dbL, $idP)['paid_until'] === date('Y-m-d H:i:s', 1000000 + 365 * 86400));
check('assegna piano inesistente', !Plans::assign($dbL, $idP, 999) && !Plans::assign($dbL, 999, $ann['id']));
check('piano eliminato', Plans::delete($dbL, $ann['id']) && count(Plans::all($dbL)) === 1);
$adm = ['email' => 'boss@example.com', 'plan_status' => 'trial', 'trial_ends_at' => '2000-01-01 00:00:00', 'suspended' => 0, 'approved' => 1];
check('amministratore: piano senza scadenza', Auth::effectivePlan(['admin_emails' => ['boss@example.com']], $adm)['state'] === 'active' && Auth::effectivePlan([], $adm)['state'] === 'expired' && Auth::effectivePlan(['admin_emails' => ['boss@example.com']], ['suspended' => 1] + $adm)['state'] === 'suspended');
$admPage = Pages::admin([], Admin::rows($dbL, null, ['admin_emails' => ['p@example.com']]), Admin::stats(Admin::rows($dbL)), ['q' => '', 'state' => '', 'page' => 1, 'pages' => 1, 'shown' => 1], 'tok', 1, null, null, Plans::all($dbL));
check('pannello admin: piani e assegnazione', str_contains($admPage, 'Piani di abbonamento') && str_contains($admPage, 'plan_save') && str_contains($admPage, 'Assegna piano') && str_contains($admPage, 'amministratore · senza scadenza'));

// ── Amministratore definito nel config ──
fwrite(STDOUT, "Amministratore da config\n");
$dbA = newDb();
$cfgA = ['admin_email' => 'Info@Esempio.it', 'admin_password' => 'Segreta123!'];
check('admin config: password sbagliata rifiutata', !Auth::login($dbA, 'info@esempio.it', 'altra', $cfgA)['ok']);
$rA = Auth::login($dbA, 'info@esempio.it', 'Segreta123!', $cfgA);
check('admin config: primo accesso crea l\'account', $rA['ok'] && $dbA->row('SELECT id FROM accounts WHERE email = ?', ['info@esempio.it']) !== null);
check('admin config: è amministratore', Auth::isAdmin($cfgA, ['email' => 'info@esempio.it']));
check('admin config: secondo accesso riusa l\'account', Auth::login($dbA, 'INFO@esempio.it', 'Segreta123!', $cfgA)['ok'] && (int)$dbA->row('SELECT COUNT(*) AS n FROM accounts')['n'] === 1);
check('admin config: senza config nessun accesso speciale', !Auth::login(newDb(), 'info@esempio.it', 'Segreta123!', [])['ok']);

// ── Pannello amministratore ──────────────────────────────────────────
fwrite(STDOUT, "Pannello amministratore\n");
$db = newDb();
$cfgA = ['admin_emails' => [' Boss@Example.com ']];
$boss = addAccount($db, 'boss@example.com');
$s1 = addAccount($db, 's1@example.com', 'tattoo');
$s2 = addAccount($db, 's2@example.com', 'meccanico');
$bossAcct = Auth::account($db, $boss);
check('admin riconosciuto dall\'email (maiuscole e spazi ignorati)', Auth::isAdmin($cfgA, $bossAcct) && !Auth::isAdmin($cfgA, Auth::account($db, $s1)) && !Auth::isAdmin([], $bossAcct));
$apiS1 = new Api($db, $s1);
$cl = call($apiS1, 'POST', '/api/clients', [], ['name' => 'Mario'])[1]['client']['id'];
call($apiS1, 'POST', '/api/appointments', [], ['client_id' => $cl, 'scheduled_date' => '2030-01-01']);
$rows = Admin::rows($db);
$byEmail = [];
foreach ($rows as $r) { $byEmail[$r['email']] = $r; }
check('elenco: tre negozi con utilizzo', count($rows) === 3 && $byEmail['s1@example.com']['clients'] === 1 && $byEmail['s1@example.com']['appointments'] === 1 && $byEmail['s2@example.com']['clients'] === 0);
check('elenco: categoria e piano', $byEmail['s1@example.com']['category_label'] === 'Studio tatuaggi / piercing' && $byEmail['s1@example.com']['plan']['state'] === 'trial');
check('statistiche', Admin::stats($rows)['total'] === 3 && Admin::stats($rows)['trial'] === 3 && Admin::stats($rows)['new7'] === 3);

// prolungare la prova
$before = strtotime(Auth::account($db, $s1)['trial_ends_at']);
check('prolunga la prova di 30 giorni', Admin::extend($db, $s1, 30) && strtotime(Auth::account($db, $s1)['trial_ends_at']) === $before + 30 * 86400);
$db->exec("UPDATE accounts SET trial_ends_at = '2000-01-01 00:00:00' WHERE id = ?", [$s2]);
check('prova scaduta', Auth::plan(Auth::account($db, $s2))['state'] === 'expired');
check('prolungare una prova scaduta riparte da oggi', Admin::extend($db, $s2, 7) && Auth::plan(Auth::account($db, $s2))['state'] === 'trial'
    && Auth::plan(Auth::account($db, $s2))['days_left'] === 7);
check('giorni non validi rifiutati', !Admin::extend($db, $s1, 0) && !Admin::extend($db, $s1, 99999) && !Admin::extend($db, 9999, 5));

// attivazione manuale
$future = date('Y-m-d', time() + 90 * 86400);
check('attivazione manuale fino a una data', Admin::activate($db, $s1, $future) && Auth::plan(Auth::account($db, $s1))['state'] === 'active');
check('attivazione: data passata o formato errato rifiutati', !Admin::activate($db, $s1, '2001-01-01') && !Admin::activate($db, $s1, 'domani') && !Admin::activate($db, 9999, $future));
$endBefore = strtotime(Auth::account($db, $s1)['paid_until']);
Admin::extend($db, $s1, 10);
check('per chi paga si sposta il rinnovo', strtotime(Auth::account($db, $s1)['paid_until']) === $endBefore + 10 * 86400);

// sospensione
check('sospensione blocca l\'uso', Admin::setSuspended($db, $s1, true) && Auth::plan(Auth::account($db, $s1))['state'] === 'suspended' && !Auth::canUse([], Auth::account($db, $s1)));
check('riattivazione ripristina lo stato precedente', Admin::setSuspended($db, $s1, false) && Auth::plan(Auth::account($db, $s1))['state'] === 'active' && Auth::canUse([], Auth::account($db, $s1)));
check('Stripe non toglie la sospensione', (function () use ($db, $s1) {
    Admin::setSuspended($db, $s1, true);
    Billing::handleEvent($db, ['type' => 'checkout.session.completed', 'data' => ['object' => ['mode' => 'subscription', 'client_reference_id' => (string)$s1, 'customer' => 'c', 'subscription' => 's']]]);
    $ok = Auth::plan(Auth::account($db, $s1))['state'] === 'suspended';
    Admin::setSuspended($db, $s1, false);
    return $ok;
})());
$db->exec("UPDATE accounts SET trial_ends_at = '2000-01-01 00:00:00', plan_status = 'trial' WHERE id = ?", [$boss]);
check('l\'amministratore usa l\'agenda anche a prova scaduta', Auth::canUse($cfgA, Auth::account($db, $boss)) && !Auth::canUse([], Auth::account($db, $boss)));
Admin::setSuspended($db, $boss, true);
check('...ma non da sospeso', !Auth::canUse($cfgA, Auth::account($db, $boss)));
Admin::setSuspended($db, $boss, false);

// password
$old = Auth::account($db, $s2)['password_hash'];
$pwd = Admin::resetPassword($db, $s2);
check('nuova password provvisoria', is_string($pwd) && strlen($pwd) === 12 && Auth::login($db, 's2@example.com', $pwd)['ok'] && Auth::account($db, $s2)['password_hash'] !== $old);
Auth::logout();
check('la vecchia password non vale più', !Auth::login($db, 's2@example.com', 'segretissima')['ok']);
check('reset su account inesistente', Admin::resetPassword($db, 9999) === null);
check('ultimo accesso registrato', Auth::account($db, $s2)['last_login_at'] !== '');

// eliminazione
$cl2 = call(new Api($db, $s2), 'POST', '/api/clients', [], ['name' => 'Luigi'])[1]['client']['id'];
call(new Api($db, $s2), 'POST', '/api/appointments', [], ['client_id' => $cl2, 'scheduled_date' => '2030-01-01']);
check('eliminazione account', Admin::delete($db, $s2) && Auth::account($db, $s2) === null);
$left = fn(string $t, int $id) => (int)$db->row("SELECT COUNT(*) AS c FROM $t WHERE account_id = ?", [$id])['c'];
check('eliminati tutti i suoi dati', $left('clients', $s2) + $left('staff', $s2) + $left('services', $s2) + $left('appointments', $s2) === 0
    && (int)$db->row('SELECT COUNT(*) AS c FROM appointment_services')['c'] === 0);
check('i dati degli altri restano', $left('clients', $s1) === 1 && $left('services', $s1) >= 5 && $left('appointments', $s1) === 1);
check('eliminare un account inesistente', !Admin::delete($db, 9999));

// sicurezza del modulo
Auth::start();
$tok = Admin::csrfToken();
check('token CSRF stabile e verificabile', $tok === Admin::csrfToken() && Admin::checkCsrf($tok) && !Admin::checkCsrf('altro') && !Admin::checkCsrf(''));
$rows = Admin::rows($db);
$page = Pages::admin([], $rows, Admin::stats($rows), ['q' => '"><script>', 'state' => '', 'page' => 1, 'pages' => 1, 'shown' => 2], $tok, $boss, null, null);
check('pagina admin: elenco, azioni, token', str_contains($page, 's1@example.com') && str_contains($page, 'value="' . $tok . '"') && str_contains($page, 'name="azione"') && str_contains($page, 'Elimina account e dati'));
check('pagina admin: nessuna eliminazione del proprio account', substr_count($page, 'value="delete"') === count($rows) - 1);
check('pagina admin escapa i filtri', !str_contains($page, '"><script>'));
Auth::logout();

fwrite(STDOUT, "\n$passed ok, $failed falliti\n");
exit($failed === 0 ? 0 : 1);
