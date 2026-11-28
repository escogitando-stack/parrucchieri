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
                'theme' => (string)($r['theme'] ?? ''),
                'image_url' => (string)($r['image_url'] ?? ''),
                'headline' => (string)($r['headline'] ?? ''),
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
            'dentista' => [
                'label' => 'Studio dentistico',
                'staff_title' => 'Dentista',
                'staff_hint' => 'es. Odontoiatra, Igienista dentale',
                'terms' => ['staff' => 'professionista', 'staffs' => 'Professionisti', 'service' => 'prestazione', 'services' => 'Prestazioni', 'product' => 'materiale', 'products' => 'Materiali'],
                'services' => [
                    ['Prima visita', 'Visita e piano di cura', 30, 50, '#3b82f6', 'Visite'],
                    ['Visita di controllo', 'Controllo periodico', 20, 40, '#3b82f6', 'Visite'],
                    ['Igiene orale', 'Pulizia professionale e lucidatura', 45, 80, '#10b981', 'Igiene'],
                    ['Sbiancamento', 'Sbiancamento professionale in studio', 60, 250, '#14b8a6', 'Estetica'],
                    ['Otturazione', 'Cura della carie con ricostruzione', 45, 100, '#f59e0b', 'Conservativa'],
                    ['Devitalizzazione', 'Cura canalare', 90, 250, '#ef4444', 'Endodonzia'],
                    ['Estrazione', 'Estrazione semplice', 30, 100, '#6b7280', 'Chirurgia'],
                    ['Visita ortodontica', 'Valutazione apparecchio o allineatori', 30, 60, '#8b5cf6', 'Ortodonzia'],
                    ['Urgenza', 'Visita per dolore o urgenza', 30, 60, '#f43f5e', 'Visite'],
                ],
            ],
            'veterinario' => [
                'label' => 'Ambulatorio veterinario',
                'staff_title' => 'Veterinario',
                'staff_hint' => 'es. Medico veterinario',
                'terms' => ['staff' => 'veterinario', 'staffs' => 'Veterinari', 'service' => 'prestazione', 'services' => 'Prestazioni', 'product' => 'prodotto', 'products' => 'Prodotti'],
                'services' => [
                    ['Visita clinica', 'Visita generale cane o gatto', 30, 45, '#3b82f6', 'Visite'],
                    ['Vaccinazione', 'Vaccino annuale con visita', 20, 50, '#10b981', 'Prevenzione'],
                    ['Microchip e iscrizione anagrafe', 'Applicazione microchip', 15, 40, '#14b8a6', 'Prevenzione'],
                    ['Visita di controllo', 'Controllo dopo terapia o intervento', 20, 30, '#3b82f6', 'Visite'],
                    ['Ecografia', 'Ecografia addominale', 30, 70, '#8b5cf6', 'Diagnostica'],
                    ['Esami del sangue', 'Prelievo ed emocromo', 15, 60, '#f59e0b', 'Diagnostica'],
                    ['Pulizia dei denti', 'Detartrasi in sedazione', 60, 150, '#ec4899', 'Chirurgia'],
                    ['Sterilizzazione', 'Intervento di sterilizzazione', 90, 200, '#ef4444', 'Chirurgia'],
                    ['Visita animali esotici', 'Conigli, rettili, uccelli', 30, 50, '#6b7280', 'Visite'],
                ],
            ],
            'gommista' => [
                'label' => 'Gommista',
                'staff_title' => 'Gommista',
                'staff_hint' => 'es. Gommista, Addetto officina',
                'terms' => ['staff' => 'operatore', 'staffs' => 'Operatori', 'service' => 'intervento', 'services' => 'Interventi', 'product' => 'pneumatico', 'products' => 'Pneumatici'],
                'services' => [
                    ['Cambio gomme stagionale', 'Smontaggio, montaggio ed equilibratura 4 ruote', 45, 50, '#3b82f6', 'Gomme'],
                    ['Montaggio pneumatici nuovi', 'Montaggio ed equilibratura 4 pneumatici', 60, 60, '#3b82f6', 'Gomme'],
                    ['Equilibratura', 'Equilibratura delle 4 ruote', 30, 30, '#10b981', 'Gomme'],
                    ['Convergenza', 'Controllo e regolazione assetto', 45, 50, '#8b5cf6', 'Assetto'],
                    ['Riparazione foratura', 'Riparazione pneumatico forato', 20, 20, '#f59e0b', 'Riparazioni'],
                    ['Deposito pneumatici', 'Custodia stagionale del treno di gomme', 15, 40, '#6b7280', 'Servizi'],
                    ['Controllo pressione e usura', 'Verifica gratuita', 10, 0, '#14b8a6', 'Servizi'],
                    ['Cambio gomme moto', 'Smontaggio e montaggio pneumatico moto', 45, 35, '#ec4899', 'Gomme'],
                ],
            ],
            'toelettatura' => [
                'label' => 'Toelettatura cani',
                'staff_title' => 'Toelettatore',
                'staff_hint' => 'es. Toelettatrice',
                'terms' => ['staff' => 'toelettatore', 'staffs' => 'Toelettatori', 'service' => 'servizio', 'services' => 'Servizi', 'product' => 'prodotto', 'products' => 'Prodotti'],
                'services' => [
                    ['Bagno taglia piccola', 'Bagno, asciugatura e spazzolatura', 60, 30, '#3b82f6', 'Bagno'],
                    ['Bagno taglia media', 'Bagno, asciugatura e spazzolatura', 75, 40, '#3b82f6', 'Bagno'],
                    ['Bagno taglia grande', 'Bagno, asciugatura e spazzolatura', 90, 55, '#3b82f6', 'Bagno'],
                    ['Toelettatura completa', 'Bagno, taglio e rifinitura', 120, 60, '#ec4899', 'Taglio'],
                    ['Stripping', 'Rimozione manuale del pelo morto', 120, 70, '#8b5cf6', 'Taglio'],
                    ['Taglio unghie', 'Taglio e limatura', 15, 10, '#6b7280', 'Extra'],
                    ['Pulizia orecchie', 'Pulizia delicata', 15, 8, '#14b8a6', 'Extra'],
                    ['Trattamento antiparassitario', 'Bagno con prodotto specifico', 30, 15, '#10b981', 'Extra'],
                    ['Bagno gatto', 'Bagno e asciugatura', 60, 45, '#f59e0b', 'Bagno'],
                ],
            ],
            'addestratore' => [
                'label' => 'Addestratore cani',
                'staff_title' => 'Educatore cinofilo',
                'staff_hint' => 'es. Addestratore, Educatore cinofilo',
                'terms' => ['staff' => 'educatore', 'staffs' => 'Educatori', 'service' => 'lezione', 'services' => 'Lezioni', 'product' => 'prodotto', 'products' => 'Prodotti'],
                'services' => [
                    ['Primo incontro conoscitivo', 'Valutazione del cane e degli obiettivi', 60, 0, '#6b7280', 'Consulenza'],
                    ['Lezione individuale', 'Educazione di base', 60, 40, '#3b82f6', 'Lezioni'],
                    ['Educazione cucciolo', 'Prime regole e socializzazione', 60, 35, '#10b981', 'Lezioni'],
                    ['Lezione di gruppo', 'Lezione in piccolo gruppo', 60, 20, '#14b8a6', 'Lezioni'],
                    ['Lezione a domicilio', 'Lezione a casa del cliente', 75, 55, '#8b5cf6', 'Lezioni'],
                    ['Problemi comportamentali', 'Consulenza su aggressività, ansia, tirare al guinzaglio', 90, 70, '#f43f5e', 'Consulenza'],
                    ['Pacchetto 5 lezioni', 'Percorso di educazione base', 60, 180, '#f59e0b', 'Pacchetti'],
                ],
            ],
            'idraulico' => [
                'label' => 'Idraulico',
                'staff_title' => 'Idraulico',
                'staff_hint' => 'es. Idraulico, Tecnico caldaie',
                'terms' => ['staff' => 'tecnico', 'staffs' => 'Tecnici', 'service' => 'intervento', 'services' => 'Interventi', 'product' => 'materiale', 'products' => 'Materiali'],
                'services' => [
                    ['Sopralluogo e preventivo', 'Valutazione del lavoro', 30, 0, '#6b7280', 'Preventivi'],
                    ['Riparazione perdita', 'Ricerca e riparazione perdita', 60, 80, '#3b82f6', 'Riparazioni'],
                    ['Disostruzione scarico', 'Lavandino, wc o doccia', 60, 70, '#10b981', 'Riparazioni'],
                    ['Sostituzione rubinetto', 'Fornitura esclusa', 45, 50, '#14b8a6', 'Installazioni'],
                    ['Manutenzione caldaia', 'Pulizia e controllo annuale', 60, 90, '#f59e0b', 'Caldaie'],
                    ['Installazione scaldabagno', 'Montaggio e collegamento', 120, 150, '#8b5cf6', 'Installazioni'],
                    ['Sostituzione sanitari', 'Wc, bidet o lavabo', 120, 150, '#ec4899', 'Installazioni'],
                    ['Intervento urgente', 'Uscita in giornata', 60, 100, '#ef4444', 'Urgenze'],
                ],
            ],
            'elettricista' => [
                'label' => 'Elettricista',
                'staff_title' => 'Elettricista',
                'staff_hint' => 'es. Elettricista, Tecnico',
                'terms' => ['staff' => 'tecnico', 'staffs' => 'Tecnici', 'service' => 'intervento', 'services' => 'Interventi', 'product' => 'materiale', 'products' => 'Materiali'],
                'services' => [
                    ['Sopralluogo e preventivo', 'Valutazione del lavoro', 30, 0, '#6b7280', 'Preventivi'],
                    ['Ricerca guasto', 'Individuazione e riparazione del guasto', 60, 70, '#ef4444', 'Riparazioni'],
                    ['Installazione punto luce o presa', 'Nuovo punto luce o presa', 60, 60, '#3b82f6', 'Installazioni'],
                    ['Installazione lampadari', 'Montaggio e collegamento', 45, 40, '#f59e0b', 'Installazioni'],
                    ['Sostituzione quadro elettrico', 'Nuovo quadro con salvavita', 180, 350, '#8b5cf6', 'Impianti'],
                    ['Installazione climatizzatore', 'Montaggio split', 180, 300, '#14b8a6', 'Impianti'],
                    ['Certificazione impianto', 'Verifica e dichiarazione di conformità', 90, 150, '#10b981', 'Certificazioni'],
                    ['Videocitofono o antenna', 'Installazione o riparazione', 90, 120, '#ec4899', 'Installazioni'],
                ],
            ],
            'fisioterapista' => [
                'label' => 'Fisioterapista',
                'staff_title' => 'Fisioterapista',
                'staff_hint' => 'es. Fisioterapista, Osteopata',
                'terms' => ['staff' => 'terapista', 'staffs' => 'Terapisti', 'service' => 'trattamento', 'services' => 'Trattamenti', 'product' => 'prodotto', 'products' => 'Prodotti'],
                'services' => [
                    ['Prima valutazione', 'Anamnesi, test e piano di trattamento', 60, 60, '#3b82f6', 'Valutazione'],
                    ['Seduta di fisioterapia', 'Terapia manuale ed esercizi', 45, 50, '#10b981', 'Trattamenti'],
                    ['Massaggio decontratturante', 'Massaggio terapeutico', 45, 45, '#14b8a6', 'Trattamenti'],
                    ['Riabilitazione post-operatoria', 'Recupero dopo intervento', 60, 55, '#8b5cf6', 'Riabilitazione'],
                    ['Tecarterapia', 'Seduta di tecar', 30, 40, '#f59e0b', 'Terapie fisiche'],
                    ['Onde d\'urto', 'Seduta di onde d\'urto', 20, 50, '#ef4444', 'Terapie fisiche'],
                    ['Linfodrenaggio', 'Drenaggio linfatico manuale', 60, 55, '#ec4899', 'Trattamenti'],
                    ['Pacchetto 5 sedute', 'Ciclo di trattamento', 45, 220, '#6b7280', 'Pacchetti'],
                ],
            ],
            'psicologo' => [
                'label' => 'Psicologo',
                'staff_title' => 'Psicologo',
                'staff_hint' => 'es. Psicologo, Psicoterapeuta',
                'terms' => ['staff' => 'professionista', 'staffs' => 'Professionisti', 'service' => 'seduta', 'services' => 'Sedute', 'product' => 'materiale', 'products' => 'Materiali'],
                'services' => [
                    ['Primo colloquio', 'Conoscenza e definizione degli obiettivi', 50, 60, '#3b82f6', 'Colloqui'],
                    ['Seduta individuale', 'Colloquio di sostegno o psicoterapia', 50, 60, '#10b981', 'Sedute'],
                    ['Seduta online', 'Videochiamata', 50, 55, '#14b8a6', 'Sedute'],
                    ['Terapia di coppia', 'Seduta di coppia', 60, 80, '#ec4899', 'Sedute'],
                    ['Sostegno genitoriale', 'Colloquio con i genitori', 50, 60, '#f59e0b', 'Sedute'],
                    ['Valutazione psicologica', 'Somministrazione test e restituzione', 90, 120, '#8b5cf6', 'Valutazioni'],
                ],
            ],
            'avvocato' => [
                'label' => 'Studio legale',
                'staff_title' => 'Avvocato',
                'staff_hint' => 'es. Avvocato, Praticante',
                'terms' => ['staff' => 'professionista', 'staffs' => 'Professionisti', 'service' => 'consulenza', 'services' => 'Consulenze', 'product' => 'documento', 'products' => 'Documenti'],
                'services' => [
                    ['Prima consulenza', 'Analisi del caso e possibili soluzioni', 45, 80, '#3b82f6', 'Consulenze'],
                    ['Consulenza online', 'Videochiamata', 45, 70, '#14b8a6', 'Consulenze'],
                    ['Consulenza diritto di famiglia', 'Separazioni, divorzi, affidamento', 60, 100, '#ec4899', 'Civile'],
                    ['Consulenza lavoro', 'Licenziamenti, contratti, vertenze', 60, 100, '#f59e0b', 'Lavoro'],
                    ['Recupero crediti', 'Valutazione della pratica', 45, 80, '#10b981', 'Civile'],
                    ['Successioni ed eredità', 'Consulenza su testamenti ed eredità', 60, 100, '#8b5cf6', 'Civile'],
                    ['Revisione contratto', 'Lettura e parere su un contratto', 60, 120, '#6b7280', 'Contratti'],
                ],
            ],
            'commercialista' => [
                'label' => 'Commercialista',
                'staff_title' => 'Commercialista',
                'staff_hint' => 'es. Commercialista, Consulente fiscale',
                'terms' => ['staff' => 'consulente', 'staffs' => 'Consulenti', 'service' => 'servizio', 'services' => 'Servizi', 'product' => 'documento', 'products' => 'Documenti'],
                'services' => [
                    ['Prima consulenza', 'Analisi della situazione fiscale', 45, 60, '#3b82f6', 'Consulenze'],
                    ['Dichiarazione dei redditi (730)', 'Compilazione e invio', 45, 60, '#10b981', 'Dichiarazioni'],
                    ['Modello Redditi persone fisiche', 'Compilazione e invio', 60, 120, '#10b981', 'Dichiarazioni'],
                    ['Apertura partita IVA', 'Scelta del regime e apertura', 60, 150, '#f59e0b', 'Imprese'],
                    ['Consulenza regime forfettario', 'Requisiti, tasse e adempimenti', 45, 60, '#14b8a6', 'Consulenze'],
                    ['ISEE', 'Compilazione DSU', 30, 30, '#8b5cf6', 'Pratiche'],
                    ['Successione', 'Dichiarazione di successione', 60, 300, '#6b7280', 'Pratiche'],
                    ['Incontro periodico', 'Consegna documenti e aggiornamento', 30, 0, '#ec4899', 'Clienti'],
                ],
            ],
            'consulente-finanziario' => [
                'label' => 'Consulente finanziario',
                'staff_title' => 'Consulente finanziario',
                'staff_hint' => 'es. Consulente finanziario, Private banker',
                'terms' => ['staff' => 'consulente', 'staffs' => 'Consulenti', 'service' => 'consulenza', 'services' => 'Consulenze', 'product' => 'documento', 'products' => 'Documenti'],
                'services' => [
                    ['Primo incontro', 'Conoscenza e analisi degli obiettivi', 60, 0, '#3b82f6', 'Incontri'],
                    ['Analisi del portafoglio', 'Verifica degli investimenti attuali', 60, 0, '#10b981', 'Investimenti'],
                    ['Pianificazione previdenziale', 'Pensione e fondi pensione', 60, 0, '#f59e0b', 'Pianificazione'],
                    ['Protezione e assicurazioni', 'Polizze vita, salute, casa', 45, 0, '#8b5cf6', 'Protezione'],
                    ['Pianificazione successoria', 'Passaggio generazionale del patrimonio', 60, 0, '#6b7280', 'Pianificazione'],
                    ['Consulenza mutuo', 'Confronto e scelta del mutuo', 45, 0, '#14b8a6', 'Finanziamenti'],
                    ['Revisione periodica', 'Aggiornamento del piano', 45, 0, '#ec4899', 'Incontri'],
                ],
            ],
            'immobiliare' => [
                'label' => 'Agenzia immobiliare',
                'staff_title' => 'Agente immobiliare',
                'staff_hint' => 'es. Agente immobiliare',
                'terms' => ['staff' => 'agente', 'staffs' => 'Agenti', 'service' => 'appuntamento', 'services' => 'Tipi di appuntamento', 'product' => 'immobile', 'products' => 'Immobili'],
                'services' => [
                    ['Visita immobile', 'Visita accompagnata a un immobile', 45, 0, '#3b82f6', 'Visite'],
                    ['Valutazione gratuita', 'Stima del valore del tuo immobile', 60, 0, '#10b981', 'Vendita'],
                    ['Incontro in agenzia', 'Ricerca casa e richieste', 30, 0, '#14b8a6', 'Incontri'],
                    ['Consulenza affitto', 'Per proprietari che vogliono affittare', 45, 0, '#f59e0b', 'Affitti'],
                    ['Consulenza mutuo', 'Con il consulente creditizio', 45, 0, '#8b5cf6', 'Finanziamenti'],
                    ['Firma proposta / compromesso', 'Appuntamento per la firma', 60, 0, '#ec4899', 'Trattative'],
                    ['Servizio fotografico immobile', 'Foto e planimetria per l\'annuncio', 60, 0, '#6b7280', 'Vendita'],
                ],
            ],
            'campi-sportivi' => [
                'label' => 'Campi da gioco / centro sportivo',
                'staff_title' => 'Campo',
                'staff_hint' => 'es. Campo 1, Campo padel, Calcetto',
                'terms' => ['staff' => 'campo', 'staffs' => 'Campi', 'service' => 'prenotazione', 'services' => 'Attività', 'product' => 'articolo', 'products' => 'Noleggi e articoli'],
                'services' => [
                    ['Padel 1 ora', 'Campo da padel, 4 giocatori', 60, 40, '#3b82f6', 'Padel'],
                    ['Padel 1 ora e mezza', 'Campo da padel, 4 giocatori', 90, 56, '#3b82f6', 'Padel'],
                    ['Tennis 1 ora', 'Campo da tennis', 60, 20, '#10b981', 'Tennis'],
                    ['Calcetto 1 ora', 'Campo da calcio a 5', 60, 70, '#f59e0b', 'Calcio'],
                    ['Calcio a 8 1 ora', 'Campo da calcio a 8', 60, 100, '#f59e0b', 'Calcio'],
                    ['Beach volley 1 ora', 'Campo da beach volley', 60, 30, '#14b8a6', 'Beach'],
                    ['Lezione con maestro', 'Lezione individuale', 60, 40, '#8b5cf6', 'Lezioni'],
                    ['Noleggio racchette', 'Racchette e palline', 60, 5, '#6b7280', 'Noleggi'],
                ],
            ],
            'autoscuola' => [
                'label' => 'Autoscuola',
                'staff_title' => 'Istruttore',
                'staff_hint' => 'es. Istruttore di guida',
                'terms' => ['staff' => 'istruttore', 'staffs' => 'Istruttori', 'service' => 'lezione', 'services' => 'Lezioni', 'product' => 'materiale', 'products' => 'Materiali'],
                'services' => [
                    ['Guida auto (patente B)', 'Lezione di guida', 30, 30, '#3b82f6', 'Guide'],
                    ['Guida auto doppia', 'Lezione di guida di un\'ora', 60, 55, '#3b82f6', 'Guide'],
                    ['Guida in autostrada / notturna', 'Guida obbligatoria', 60, 60, '#8b5cf6', 'Guide'],
                    ['Guida moto (A1/A2/A)', 'Lezione di guida moto', 30, 35, '#f59e0b', 'Guide'],
                    ['Lezione di teoria', 'Lezione in aula', 60, 0, '#10b981', 'Teoria'],
                    ['Iscrizione e pratica', 'Iscrizione al corso e documenti', 30, 0, '#6b7280', 'Segreteria'],
                    ['Rinnovo patente', 'Visita medica e pratica', 20, 80, '#14b8a6', 'Segreteria'],
                    ['Esame di guida', 'Accompagnamento all\'esame', 60, 80, '#ef4444', 'Esami'],
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

    /** Categorie aggiunte con lo schema 9 (inserite anche nei database già esistenti). */
    public const ADDED_V9 = ['dentista', 'veterinario', 'gommista', 'toelettatura', 'addestratore', 'idraulico', 'elettricista', 'fisioterapista', 'psicologo', 'avvocato', 'commercialista', 'consulente-finanziario', 'immobiliare', 'campi-sportivi', 'autoscuola'];

    /** Aggiunge le categorie predefinite indicate se mancano (prima di "Altra attività"). @param array<int,string> $keys */
    public static function addMissing(Db $db, array $keys): void
    {
        $builtin = self::builtin();
        $altro = $db->row("SELECT sort_order FROM categories WHERE cat_key = 'altro'");
        $order = (int)($db->row('SELECT COALESCE(MAX(sort_order), 0) AS m FROM categories' . ($altro !== null ? " WHERE cat_key <> 'altro'" : ''))['m'] ?? 0);
        foreach ($keys as $key) {
            if (!isset($builtin[$key]) || $db->row('SELECT id FROM categories WHERE cat_key = ?', [$key]) !== null) {
                continue;
            }
            $c = $builtin[$key];
            $db->exec(
                'INSERT INTO categories (cat_key, label, staff_title, staff_hint, terms, services, active, sort_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
                [$key, $c['label'], $c['staff_title'], $c['staff_hint'], json_encode($c['terms'], JSON_UNESCAPED_UNICODE), json_encode($c['services'], JSON_UNESCAPED_UNICODE), ++$order]
            );
        }
        if ($altro !== null) {
            $db->exec("UPDATE categories SET sort_order = ? WHERE cat_key = 'altro'", [$order + 1]);
        }
        if (self::$db === $db) {
            self::$cache = null;
        }
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
        $theme = (string)($in['theme'] ?? '');
        if ($theme !== '' && !isset(Themes::styles()[$theme])) {
            return 'Stile grafico non valido.';
        }
        $image = trim((string)($in['image_url'] ?? ''));
        if ($image !== '' && (!preg_match('#^https://[^\s"\'<>]+$#i', $image) || strlen($image) > 500)) {
            return 'L\'immagine deve essere un indirizzo che inizia con https:// (massimo 500 caratteri).';
        }
        $headline = mb_substr(trim((string)($in['headline'] ?? '')), 0, 160);
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
                'INSERT INTO categories (cat_key, label, staff_title, staff_hint, terms, services, active, sort_order, theme, image_url, headline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$key, $label, $staffTitle, $staffHint, $jsonT, $jsonS, $active, $max + 1, $theme, $image, $headline]
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
                'UPDATE categories SET label = ?, staff_title = ?, staff_hint = ?, terms = ?, services = ?, active = ?, theme = ?, image_url = ?, headline = ?' . ($order > 0 ? ', sort_order = ' . min($order, 9999) : '') . ' WHERE id = ?',
                [$label, $staffTitle, $staffHint, $jsonT, $jsonS, $active, $theme, $image, $headline, $id]
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
