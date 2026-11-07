<?php
declare(strict_types=1);

namespace App;

/**
 * Tipi di attività: ognuno porta servizi di partenza (modificabili dal negozio)
 * e i nomi usati nell'interfaccia (collaboratore, servizio, prodotto).
 * Per aggiungere un'attività basta aggiungere una voce qui sotto.
 */
final class Categories
{
    /**
     * Servizi: [nome, descrizione, durata in minuti, prezzo in euro, colore, gruppo].
     * I prezzi sono indicativi e si cambiano dall'app.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
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
        return $all[$key] ?? $all['altro'];
    }

    public static function valid(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /** Elenco per i menu a tendina: chiave => etichetta. @return array<string,string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::all() as $key => $c) {
            $out[$key] = $c['label'];
        }
        return $out;
    }
}
