<?php
declare(strict_types=1);

namespace App;

/**
 * Tipi di attività (categorie merceologiche): ognuno porta servizi di partenza (modificabili dal negozio)
 * e i nomi usati nell'interfaccia (collaboratore, servizio, prodotto).
 *
 * Le categorie vivono nella tabella `categories` e si gestiscono dal pannello /admin.
 * L'elenco qui sotto (builtin) serve a riempire la tabella la prima volta e come riserva
 * quando il database non è ancora pronto.
 */
final class Categories
{
    private static ?Db $db = null;
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $cache = null;

    /** Collega le categorie al database (chiamato all'avvio). Con null si torna all'elenco predefinito. */
    public static function boot(?Db $db): void
    {
        self::$db = $db;
        self::$cache = null;
    }

    /**
     * Tutte le categorie (anche quelle disattivate), nell'ordine scelto dall'amministratore.
     * Ogni voce: label, staff_title, staff_hint, terms, services, active, id, sort_order.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $rows = [];
        if (self::$db !== null) {
            try {
                $rows = self::$db->all('SELECT * FROM categories ORDER BY sort_order, id');
            } catch (\Throwable) {
                $rows = [];
            }
        }
        if ($rows === []) {
            $out = [];
            $i = 0;
            foreach (self::builtin() as $key => $c) {
                $out[$key] = $c + ['id' => 0, 'active' => true, 'sort_order' => ++$i];
            }
            return self::$db === null ? $out : (self::$cache = $out);
        }
        $out = [];
        foreach ($rows as $r) {
            $terms = json_decode((string)$r['terms'], true);
            $services = json_decode((string)$r['services'], true);
            $out[(string)$r['cat_key']] = [
                'id' => (int)$r['id'],
                'label' => (string)$r['label'],
                'staff_title' => (string)$r['staff_title'],
                'staff_hint' => (string)$r['staff_hint'],
                'terms' => (is_array($terms) ? $terms : []) + self::DEFAULT_TERMS,
                'services' => is_array($services) ? array_values($services) : [],
                'active' => (int)$r['active'] === 1,
                'sort_order' => (int)$r['sort_order'],
            ];
        }
        return self::$cache = $out;
    }

    public const DEFAULT_TERMS = ['staff' => 'collaboratore', 'staffs' => 'Personale', 'service' => 'servizio', 'services' => 'Servizi', 'product' => 'prodotto', 'products' => 'Prodotti'];

    /**
     * Elenco di partenza. Servizi: [nome, descrizione, durata in minuti, prezzo in euro, colore, gruppo].
     *
     * @return array<string,array<string,mixed>>
     */
    public static function builtin(): array
    {
        return [
            'parrucchiere' => [
                'label' => 'Parrucchiere',
                'staff_title' => 'Parrucchiere',
                'staff_hint' => 'es. Parrucchiera senior',
                'terms' => ['staff' => 'collaboratore', 'staffs' => 'Personale', 'service' => 'servizio', 'services' => 'Servizi', 'product' => 'prodotto', 'products' => 'Prodotti'],
                'services' => [
                    ['Taglio donna', 'Shampoo, taglio e piega', 60, 45, '#ec4899', 'Taglio'],
                    ['Taglio uomo', 'Taglio con rifinitura', 30, 20, '#3b82f6', 'Taglio'],
                    ['Piega', 'Shampoo e piega', 45, 25, '#10b981', 'Styling'],
                    ['Colore', 'Colorazione completa', 90, 60, '#f59e0b', 'Colore'],
                    ['Colpi di sole / Balayage', 'Schiariture con tecnica a mano libera', 150, 120, '#8b5cf6', 'Colore'],
                    ['Trattamento ristrutturante', 'Maschera e trattamento per capelli sfibrati', 30, 25, '#14b8a6', 'Trattamento'],
                    ['Barba', 'Rifinitura barba con panno caldo', 20, 12, '#6b7280', 'Taglio'],
                    ['Acconciatura sposa', 'Prova e acconciatura il giorno del matrimonio', 120, 150, '#f43f5e', 'Styling'],
                ],
            ],
            'barbiere' => [
                'label' => 'Barbiere',
                'staff_title' => 'Barbiere',
                'staff_hint' => 'es. Barbiere senior',
                'terms' => ['staff' => 'barbiere', 'staffs' => 'Barbieri', 'service' => 'servizio', 'services' => 'Servizi', 'product' => 'prodotto', 'products' => 'Prodotti'],
                'services' => [
                    ['Taglio uomo', 'Taglio con rifinitura', 30, 18, '#3b82f6', 'Taglio'],
                    ['Barba', 'Rifinitura barba con panno caldo', 20, 12, '#6b7280', 'Barba'],
                    ['Taglio e barba', 'Taglio completo e barba', 45, 28, '#8b5cf6', 'Taglio'],
                    ['Rasatura con panno caldo', 'Rasatura tradizionale a lama', 30, 18, '#14b8a6', 'Barba'],
                    ['Taglio bambino', 'Taglio fino a 12 anni', 25, 12, '#10b981', 'Taglio'],
                    ['Colore / Schiaritura', 'Colorazione o decolorazione', 60, 40, '#f59e0b', 'Colore'],
                    ['Trattamento capelli', 'Trattamento specifico per cuoio capelluto', 20, 15, '#ec4899', 'Trattamento'],
                ],
            ],
            'unghie' => [
                'label' => 'Centro unghie (nail)',
                'staff_title' => 'Nail artist',
                'staff_hint' => 'es. Nail artist',
                'terms' => ['staff' => 'operatore', 'staffs' => 'Operatori', 'service' => 'servizio', 'services' => 'Servizi', 'product' => 'prodotto', 'products' => 'Prodotti'],
                'services' => [
                    ['Manicure classica', 'Cura e smalto tradizionale', 45, 20, '#ec4899', 'Mani'],
                    ['Semipermanente mani', 'Applicazione smalto semipermanente', 60, 30, '#8b5cf6', 'Mani'],
                    ['Ricostruzione unghie in gel', 'Ricostruzione completa', 90, 55, '#f43f5e', 'Mani'],
                    ['Refill gel', 'Ricarico della ricostruzione', 60, 35, '#f59e0b', 'Mani'],
                    ['Nail art', 'Decorazioni personalizzate', 30, 15, '#3b82f6', 'Mani'],
                    ['Rimozione semipermanente', 'Rimozione con cura delle unghie', 30, 10, '#6b7280', 'Mani'],
                    ['Pedicure estetico', 'Cura dei piedi e smalto', 60, 30, '#10b981', 'Piedi'],
                    ['Semipermanente piedi', 'Applicazione smalto semipermanente', 60, 28, '#14b8a6', 'Piedi'],
                ],
            ],
            'estetica' => [
                'label' => 'Centro estetico / beauty',
                'staff_title' => 'Estetista',
                'staff_hint' => 'es. Estetista',
                'terms' => ['staff' => 'operatore', 'staffs' => 'Operatori', 'service' => 'trattamento', 'services' => 'Trattamenti', 'product' => 'prodotto', 'products' => 'Prodotti'],
                'services' => [
                    ['Pulizia del viso', 'Pulizia profonda e idratazione', 60, 50, '#ec4899', 'Viso'],
                    ['Trattamento viso anti-age', 'Trattamento specifico personalizzato', 60, 65, '#f43f5e', 'Viso'],
                    ['Trucco', 'Make-up per occasioni speciali', 45, 40, '#8b5cf6', 'Viso'],
                    ['Ceretta gambe intere', 'Depilazione con cera', 40, 28, '#f59e0b', 'Depilazione'],
                    ['Ceretta braccia', 'Depilazione con cera', 20, 14, '#f59e0b', 'Depilazione'],
                    ['Ceretta inguine', 'Depilazione con cera', 20, 15, '#f59e0b', 'Depilazione'],
                    ['Sopracciglia', 'Definizione e depilazione', 15, 10, '#6b7280', 'Depilazione'],
                    ['Massaggio rilassante', 'Massaggio di 50 minuti', 50, 50, '#10b981', 'Massaggi'],
                    ['Massaggio decontratturante', 'Massaggio di 50 minuti', 50, 55, '#14b8a6', 'Massaggi'],
                    ['Manicure', 'Cura delle mani e smalto', 45, 20, '#3b82f6', 'Mani e piedi'],
                    ['Pedicure', 'Cura dei piedi e smalto', 60, 30, '#3b82f6', 'Mani e piedi'],
                ],
            ],
            'meccanico' => [
                'label' => 'Officina meccanica',
                'staff_title' => 'Meccanico',
                'staff_hint' => 'es. Meccanico capo officina',
                'terms' => ['staff' => 'meccanico', 'staffs' => 'Meccanici', 'service' => 'intervento', 'services' => 'Interventi', 'product' => 'ricambio', 'products' => 'Ricambi'],
                'services' => [
                    ['Tagliando completo', 'Controlli, olio e filtri', 120, 180, '#3b82f6', 'Manutenzione'],
                    ['Cambio olio e filtri', 'Olio motore e filtro olio', 45, 90, '#3b82f6', 'Manutenzione'],
                    ['Cambio pneumatici stagionale', 'Smontaggio, montaggio ed equilibratura', 60, 50, '#10b981', 'Gomme'],
                    ['Equilibratura ruote', 'Equilibratura delle quattro ruote', 30, 30, '#10b981', 'Gomme'],
                    ['Sostituzione pastiglie freni', 'Asse anteriore o posteriore', 90, 120, '#ef4444', 'Freni'],
                    ['Diagnosi elettronica', 'Lettura centralina e controllo errori', 45, 40, '#8b5cf6', 'Diagnosi'],
                    ['Ricarica climatizzatore', 'Controllo e ricarica impianto A/C', 60, 70, '#14b8a6', 'Climatizzazione'],
                    ['Sostituzione batteria', 'Fornitura e montaggio', 30, 30, '#f59e0b', 'Elettrico'],
                    ['Pre-revisione', 'Controlli prima della revisione', 30, 20, '#6b7280', 'Controlli'],
                ],
            ],
            'tattoo' => [
                'label' => 'Studio tatuaggi / piercing',
                'staff_title' => 'Tatuatore',
                'staff_hint' => 'es. Tatuatore, Piercer',
                'terms' => ['staff' => 'tatuatore', 'staffs' => 'Tatuatori', 'service' => 'servizio', 'services' => 'Servizi', 'product' => 'materiale', 'products' => 'Materiali'],
                'services' => [
                    ['Consulenza', 'Colloquio e preventivo gratuito', 30, 0, '#6b7280', 'Consulenza'],
                    ['Tatuaggio piccolo', 'Fino a circa 5 cm', 60, 80, '#8b5cf6', 'Tatuaggi'],
                    ['Tatuaggio medio', 'Seduta di circa 3 ore', 180, 180, '#ec4899', 'Tatuaggi'],
                    ['Seduta intera', 'Seduta di circa 5 ore', 300, 300, '#f43f5e', 'Tatuaggi'],
                    ['Cover up', 'Copertura di un tatuaggio esistente', 240, 250, '#f59e0b', 'Tatuaggi'],
                    ['Ritocco', 'Ritocco di un lavoro precedente', 60, 50, '#3b82f6', 'Tatuaggi'],
                    ['Piercing', 'Foratura con gioiello incluso', 30, 40, '#14b8a6', 'Piercing'],
                ],
            ],
            'altro' => [
                'label' => 'Altra attività',
                'staff_title' => '',
                'staff_hint' => 'es. Responsabile',
                'terms' => ['staff' => 'collaboratore', 'staffs' => 'Personale', 'service' => 'servizio', 'services' => 'Servizi', 'product' => 'prodotto', 'products' => 'Prodotti'],
                'services' => [
                    ['Appuntamento', 'Appuntamento standard', 60, 0, '#3b82f6', 'Generale'],
                    ['Consulenza', 'Primo colloquio', 30, 0, '#8b5cf6', 'Generale'],
                    ['Visita di controllo', 'Controllo o seguito', 30, 0, '#10b981', 'Generale'],
                ],
            ],
        ];
    }

    public static function get(string $key): array
    {
        $all = self::all();
        return $all[$key] ?? $all['altro'] ?? (self::builtin()['altro'] + ['id' => 0, 'active' => true, 'sort_order' => 0]);
    }

    /** La categoria esiste ed è attiva (cioè si può scegliere). */
    public static function valid(string $key): bool
    {
        $c = self::all()[$key] ?? null;
        return $c !== null && !empty($c['active']);
    }

    /**
     * Elenco per i menu a tendina: chiave => etichetta, solo categorie attive.
     * Con $keep si aggiunge anche una categoria disattivata (quella già scelta dal negozio).
     *
     * @return array<string,string>
     */
    public static function options(string $keep = ''): array
    {
        $out = [];
        foreach (self::all() as $key => $c) {
            if (!empty($c['active']) || $key === $keep) {
                $out[$key] = $c['label'];
            }
        }
        return $out;
    }

    /** Riempie la tabella con l'elenco predefinito (solo se è vuota). */
    public static function seed(Db $db): void
    {
        if ($db->row('SELECT id FROM categories LIMIT 1') !== null) {
            return;
        }
        $i = 0;
        foreach (self::builtin() as $key => $c) {
            $db->exec(
                'INSERT INTO categories (cat_key, label, staff_title, staff_hint, terms, services, active, sort_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
                [$key, $c['label'], $c['staff_title'], $c['staff_hint'], json_encode($c['terms'], JSON_UNESCAPED_UNICODE), json_encode($c['services'], JSON_UNESCAPED_UNICODE), ++$i]
            );
        }
        if (self::$db === $db) {
            self::$cache = null;
        }
    }

    /** Quanti negozi usano ogni categoria. @return array<string,int> */
    public static function usage(Db $db): array
    {
        $out = [];
        foreach ($db->all('SELECT category, COUNT(*) AS n FROM accounts GROUP BY category') as $r) {
            $out[(string)$r['category']] = (int)$r['n'];
        }
        return $out;
    }

    /**
     * Servizi in formato testo, uno per riga: Nome | Descrizione | Minuti | Prezzo | Gruppo | Colore
     *
     * @param array<int,array<int,mixed>> $services
     */
    public static function servicesToText(array $services): string
    {
        $lines = [];
        foreach ($services as $s) {
            [$name, $desc, $dur, $price, $color, $group] = array_pad(array_values($s), 6, '');
            $p = (float)$price;
            $lines[] = implode(' | ', [$name, $desc, (int)$dur, fmod($p, 1.0) === 0.0 ? (string)(int)$p : str_replace('.', ',', (string)$p), $group, $color]);
        }
        return implode("\n", $lines);
    }

    /**
     * Legge i servizi scritti uno per riga. Restituisce [servizi, errore|null].
     *
     * @return array{0:array<int,array<int,mixed>>,1:?string}
     */
    public static function parseServices(string $text): array
    {
        $palette = ['#3b82f6', '#ec4899', '#10b981', '#f59e0b', '#8b5cf6', '#14b8a6', '#f43f5e', '#6b7280'];
        $out = [];
        $n = 0;
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $n++;
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            [$name, $desc, $dur, $price, $group, $color] = array_pad($parts, 6, '');
            if ($name === '' || mb_strlen($name) > 120) {
                return [[], "Riga $n: manca il nome del servizio."];
            }
            $dur = $dur === '' ? 60 : $dur;
            if (!ctype_digit($dur) || (int)$dur < 5 || (int)$dur > 720) {
                return [[], "Riga $n: la durata deve essere in minuti (da 5 a 720)."];
            }
            $price = str_replace([',', '€', ' '], ['.', '', ''], $price === '' ? '0' : $price);
            if (!is_numeric($price) || (float)$price < 0 || (float)$price > 100000) {
                return [[], "Riga $n: il prezzo non è valido."];
            }
            if ($color === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = $palette[count($out) % count($palette)];
            }
            $p = round((float)$price, 2);
            $out[] = [mb_substr($name, 0, 120), mb_substr($desc, 0, 255), (int)$dur, fmod($p, 1.0) === 0.0 ? (int)$p : $p, strtolower($color), mb_substr($group, 0, 60)];
        }
        if (count($out) > 60) {
            return [[], 'Massimo 60 servizi per categoria.'];
        }
        return [$out, null];
    }

    /**
     * Crea (id = 0) o aggiorna una categoria. Restituisce un messaggio di errore oppure null.
     *
     * @param array<string,mixed> $in key, label, staff_title, staff_hint, t_staff, t_staffs, t_service, t_services, t_product, t_products, services, active
     */
    public static function save(Db $db, int $id, array $in): ?string
    {
        $label = trim((string)($in['label'] ?? ''));
        if ($label === '' || mb_strlen($label) > 80) {
            return 'Dai un nome alla categoria (massimo 80 caratteri).';
        }
        $terms = [];
        foreach (self::DEFAULT_TERMS as $k => $def) {
            $v = trim((string)($in['t_' . $k] ?? ''));
            if (mb_strlen($v) > 40) {
                return 'I nomi usati nell\'app possono avere al massimo 40 caratteri.';
            }
            $terms[$k] = $v === '' ? $def : $v;
        }
        [$services, $err] = self::parseServices((string)($in['services'] ?? ''));
        if ($err !== null) {
            return $err;
        }
        $staffTitle = mb_substr(trim((string)($in['staff_title'] ?? '')), 0, 80);
        $staffHint = mb_substr(trim((string)($in['staff_hint'] ?? '')), 0, 120);
        $active = !empty($in['active']) ? 1 : 0;
        $jsonT = json_encode($terms, JSON_UNESCAPED_UNICODE);
        $jsonS = json_encode($services, JSON_UNESCAPED_UNICODE);
        if ($id === 0) {
            $key = strtolower(trim((string)($in['key'] ?? '')));
            if ($key === '') {
                $key = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower(strtr($label, ['à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']))), '-');
                $key = substr($key, 0, 32);
            }
            if (!preg_match('/^[a-z0-9][a-z0-9-]{1,31}$/', $key)) {
                return 'Il codice deve avere da 2 a 32 tra lettere minuscole, numeri e trattini.';
            }
            if ($db->row('SELECT id FROM categories WHERE cat_key = ?', [$key]) !== null) {
                return 'Esiste già una categoria con il codice "' . $key . '".';
            }
            $max = (int)($db->row('SELECT COALESCE(MAX(sort_order), 0) AS m FROM categories')['m'] ?? 0);
            $db->exec(
                'INSERT INTO categories (cat_key, label, staff_title, staff_hint, terms, services, active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$key, $label, $staffTitle, $staffHint, $jsonT, $jsonS, $active, $max + 1]
            );
        } else {
            $row = $db->row('SELECT cat_key FROM categories WHERE id = ?', [$id]);
            if ($row === null) {
                return 'Categoria non trovata.';
            }
            if ($row['cat_key'] === 'altro' && !$active) {
                return '"Altra attività" resta sempre attiva: è la scelta di riserva.';
            }
            $order = (int)($in['sort_order'] ?? 0);
            $db->exec(
                'UPDATE categories SET label = ?, staff_title = ?, staff_hint = ?, terms = ?, services = ?, active = ?' . ($order > 0 ? ', sort_order = ' . min($order, 9999) : '') . ' WHERE id = ?',
                [$label, $staffTitle, $staffHint, $jsonT, $jsonS, $active, $id]
            );
        }
        if (self::$db === $db) {
            self::$cache = null;
        }
        return null;
    }

    /**
     * Elimina una categoria. Se qualche negozio la usa, i suoi negozi passano alla categoria $moveTo
     * (i loro servizi e dati non cambiano). Restituisce un messaggio di errore oppure null.
     */
    public static function delete(Db $db, int $id, string $moveTo = ''): ?string
    {
        $row = $db->row('SELECT cat_key, label FROM categories WHERE id = ?', [$id]);
        if ($row === null) {
            return 'Categoria non trovata.';
        }
        if ((int)($db->row('SELECT COUNT(*) AS n FROM categories')['n'] ?? 0) <= 1) {
            return 'Deve restare almeno una categoria.';
        }
        $key = (string)$row['cat_key'];
        $n = (int)($db->row('SELECT COUNT(*) AS n FROM accounts WHERE category = ?', [$key])['n'] ?? 0);
        if ($n > 0) {
            if ($moveTo === '' || $moveTo === $key || $db->row('SELECT id FROM categories WHERE cat_key = ?', [$moveTo]) === null) {
                return "La usano $n negozi: scegli a quale categoria spostarli.";
            }
            $db->exec('UPDATE accounts SET category = ? WHERE category = ?', [$moveTo, $key]);
        }
        $db->exec('DELETE FROM categories WHERE id = ?', [$id]);
        if (self::$db === $db) {
            self::$cache = null;
        }
        return null;
    }
}
