<?php
declare(strict_types=1);

namespace App;

/**
 * Template grafici della pagina pubblica dei negozi.
 *
 * Ogni stile ha colori, carattere dei titoli e un'illustrazione; ogni categoria ha i suoi testi
 * (titolo con una parola in corsivo tra *asterischi*, introduzione e tre punti numerati) e un'icona.
 * L'amministratore può cambiare lo stile e l'immagine di una categoria da /admin; il negozio può
 * caricare la sua foto di copertina, che ha la precedenza su tutto.
 */
final class Themes
{
    /** @return array<string,array<string,string>> */
    public static function styles(): array
    {
        return [
            'cipria' => ['label' => 'Cipria (bellezza)', 'bg' => '#f7ebe7', 'surface' => '#fffaf8', 'ink' => '#2b1d22', 'muted' => '#7a6168', 'acc' => '#7a2e52', 'acc2' => '#e9c4c9', 'soft' => '#f3dcdc', 'font' => 'serif'],
            'notte' => ['label' => 'Notte e oro (barbiere, tattoo)', 'bg' => '#1a1716', 'surface' => '#24201e', 'ink' => '#f5efe6', 'muted' => '#b8ab9b', 'acc' => '#c9a26b', 'acc2' => '#5a4630', 'soft' => '#2f2925', 'font' => 'serif'],
            'menta' => ['label' => 'Menta (salute)', 'bg' => '#e8f3f0', 'surface' => '#f8fcfb', 'ink' => '#11302b', 'muted' => '#4d6a64', 'acc' => '#0f766e', 'acc2' => '#a7dccf', 'soft' => '#d3ebe5', 'font' => 'serif'],
            'salvia' => ['label' => 'Salvia (benessere, psicologia)', 'bg' => '#eef0e6', 'surface' => '#fbfbf6', 'ink' => '#272d1f', 'muted' => '#626b52', 'acc' => '#56663f', 'acc2' => '#cbd5b1', 'soft' => '#e0e5d0', 'font' => 'serif'],
            'pesca' => ['label' => 'Pesca (animali)', 'bg' => '#fbede2', 'surface' => '#fffaf5', 'ink' => '#33231a', 'muted' => '#7d6352', 'acc' => '#b4532a', 'acc2' => '#f5c9a6', 'soft' => '#f7dcc8', 'font' => 'serif'],
            'officina' => ['label' => 'Officina (auto e moto)', 'bg' => '#eceff4', 'surface' => '#ffffff', 'ink' => '#14181f', 'muted' => '#5b6472', 'acc' => '#1d4ed8', 'acc2' => '#facc15', 'soft' => '#dde3ee', 'font' => 'sans'],
            'casa' => ['label' => 'Casa (impianti e riparazioni)', 'bg' => '#eef3f8', 'surface' => '#ffffff', 'ink' => '#13212f', 'muted' => '#566779', 'acc' => '#0369a1', 'acc2' => '#fb923c', 'soft' => '#d8e5f0', 'font' => 'sans'],
            'studio' => ['label' => 'Studio (professionisti)', 'bg' => '#f3efe7', 'surface' => '#fffdf8', 'ink' => '#1b2233', 'muted' => '#5f6576', 'acc' => '#1f2f52', 'acc2' => '#c8a96a', 'soft' => '#e7e0d2', 'font' => 'serif'],
            'sport' => ['label' => 'Sport (campi e centri sportivi)', 'bg' => '#e9f5e6', 'surface' => '#ffffff', 'ink' => '#13261a', 'muted' => '#4f6655', 'acc' => '#15803d', 'acc2' => '#d9f99d', 'soft' => '#d3ead0', 'font' => 'sans'],
            'neutro' => ['label' => 'Neutro (qualsiasi attività)', 'bg' => '#f4f2ee', 'surface' => '#ffffff', 'ink' => '#1f1d1a', 'muted' => '#6b665e', 'acc' => '#4338ca', 'acc2' => '#c7d2fe', 'soft' => '#e6e3f3', 'font' => 'serif'],
        ];
    }

    /** Elenco per i menu a tendina. @return array<string,string> */
    public static function options(): array
    {
        return array_map(static fn(array $s): string => $s['label'], self::styles());
    }

    /**
     * Testi e stile predefiniti per categoria: [stile, icona, titolo, introduzione, [[punto, testo] x3]].
     *
     * @return array<string,array{0:string,1:string,2:string,3:string,4:array<int,array{0:string,1:string}>}>
     */
    private static function content(): array
    {
        $book = ['Prenota quando vuoi', 'Scegli il servizio, il giorno e l\'orario libero: bastano pochi secondi, anche di notte.'];
        $confirm = ['Conferma immediata', 'Ricevi subito il riepilogo e un promemoria prima dell\'appuntamento.'];
        return [
            'parrucchiere' => ['cipria', 'scissors', 'I tuoi capelli, *finalmente* come li hai sempre voluti', 'Taglio, colore e piega pensati per te, con prodotti professionali e tutta la cura che meriti.', [['Consulenza su misura', 'Ascoltiamo cosa desideri e ti consigliamo il look più adatto al tuo viso e al tuo stile.'], $book, ['Prodotti di qualità', 'Usiamo solo prodotti professionali che rispettano i tuoi capelli e la tua pelle.']]],
            'barbiere' => ['notte', 'razor', 'Il tuo stile, *rifinito* nei dettagli', 'Tagli classici e moderni, barba con panno caldo e rasatura tradizionale. Entra, rilassati, al resto pensiamo noi.', [['Taglio su misura', 'Forbici, macchinetta e sfumature precise per il taglio che ti rappresenta.'], ['Rituale della barba', 'Panno caldo, oli e lama: la rasatura come una volta.'], $book]],
            'unghie' => ['cipria', 'polish', 'Mani perfette, *ogni* giorno', 'Manicure, semipermanente, ricostruzione e nail art: colori di tendenza e una cura impeccabile.', [['Igiene garantita', 'Strumenti sterilizzati e materiali monouso per ogni cliente.'], ['Nail art su richiesta', 'Porta la tua idea: la trasformiamo in un design unico.'], $book]],
            'estetica' => ['cipria', 'flower', 'Svela la *bellezza* che c\'è in te', 'Trattamenti per viso e corpo personalizzati, prodotti di qualità e un ambiente pensato per farti stare bene.', [['Consulenza personalizzata', 'Analizziamo pelle ed esigenze per consigliarti il percorso più adatto.'], ['Trattamenti mirati', 'Viso, corpo, epilazione e massaggi con tecniche professionali.'], ['Relax e benessere', 'Un momento solo per te, lontano dalla fretta di tutti i giorni.']]],
            'meccanico' => ['officina', 'wrench', 'La tua auto in *buone* mani', 'Tagliandi, freni, diagnosi e climatizzatore: preventivi chiari e tempi certi.', [['Diagnosi precisa', 'Strumenti elettronici aggiornati per trovare subito il problema.'], ['Preventivo chiaro', 'Sai sempre prima cosa facciamo e quanto costa.'], $book]],
            'tattoo' => ['notte', 'pen', 'La tua idea, *sulla* pelle per sempre', 'Tatuaggi personalizzati e piercing in uno studio sicuro, con artisti che ascoltano la tua storia.', [['Consulenza gratuita', 'Parliamo del progetto, della posizione e dei tempi prima di iniziare.'], ['Massima igiene', 'Aghi monouso e ambiente sterilizzato a ogni seduta.'], $book]],
            'dentista' => ['menta', 'tooth', 'Il tuo sorriso, *in salute* e sereno', 'Prevenzione, igiene, cure e estetica del sorriso in uno studio moderno, con attenzione anche ai più timorosi.', [['Prima visita', 'Un controllo completo e un piano di cura spiegato con chiarezza.'], ['Tecnologie moderne', 'Strumenti aggiornati per cure precise e meno fastidiose.'], $confirm]],
            'veterinario' => ['pesca', 'paw', 'Chi ami ha *quattro* zampe? Ci pensiamo noi', 'Visite, vaccinazioni, diagnostica e chirurgia per cani, gatti e piccoli animali, con calma e dolcezza.', [['Prevenzione', 'Vaccini, microchip e controlli per una vita lunga e serena.'], ['Diagnostica in studio', 'Ecografie ed esami del sangue con risultati rapidi.'], $book]],
            'gommista' => ['officina', 'tire', 'Gomme giuste, *viaggi* sicuri', 'Cambio stagionale, equilibratura, convergenza e deposito pneumatici senza file.', [['Niente attese', 'Prenota l\'orario: quando arrivi siamo pronti per te.'], ['Deposito gomme', 'Custodiamo il tuo treno di pneumatici fino al prossimo cambio.'], ['Controllo gratuito', 'Pressione e usura verificate a ogni visita.']]],
            'toelettatura' => ['pesca', 'paw', 'Il tuo cane, *coccolato* dalla testa alla coda', 'Bagno, taglio, stripping e piccole cure con prodotti delicati e tanta pazienza.', [['Prodotti delicati', 'Shampoo e balsami specifici per ogni tipo di pelo e di pelle.'], ['Senza stress', 'Tempi tranquilli e mani esperte, anche per i cani più timidi.'], $book]],
            'addestratore' => ['pesca', 'paw', 'Un cane felice, *un\'intesa* che dura', 'Educazione di base, cuccioli e problemi di comportamento con metodi gentili e rispettosi.', [['Primo incontro', 'Conosciamo te e il tuo cane per capire obiettivi ed esigenze.'], ['Metodo gentile', 'Rinforzo positivo e pazienza: niente costrizioni.'], ['Lezioni flessibili', 'In campo, a domicilio o in piccolo gruppo.']]],
            'idraulico' => ['casa', 'drop', 'Perdite e guasti? *Risolti* in fretta', 'Riparazioni, scarichi, caldaie e installazioni: interventi puliti e preventivi chiari.', [['Intervento rapido', 'Prenota la fascia oraria che preferisci, anche per le urgenze.'], ['Preventivo chiaro', 'Sai prima cosa facciamo e quanto costa.'], ['Lavoro pulito', 'Lasciamo la casa in ordine come l\'abbiamo trovata.']]],
            'elettricista' => ['casa', 'bolt', 'Impianti sicuri, *luce* dove serve', 'Guasti, nuovi punti luce, quadri elettrici, climatizzatori e certificazioni a norma.', [['Sicurezza a norma', 'Lavori certificati secondo le normative vigenti.'], ['Sopralluogo gratuito', 'Valutiamo il lavoro e ti diamo un preventivo chiaro.'], $book]],
            'fisioterapista' => ['menta', 'body', 'Torna a muoverti *senza* dolore', 'Valutazione, terapia manuale, esercizi e terapie fisiche per recuperare forza e mobilità.', [['Valutazione completa', 'Capiamo l\'origine del dolore prima di iniziare il trattamento.'], ['Percorso su misura', 'Terapie ed esercizi pensati per te e per i tuoi obiettivi.'], $book]],
            'psicologo' => ['salvia', 'leaf', 'Uno spazio *sicuro* per ritrovarti', 'Colloqui individuali, di coppia e online, in un ambiente accogliente e riservato.', [['Primo colloquio', 'Un incontro per conoscerci e capire insieme di cosa hai bisogno.'], ['Riservatezza', 'Quello che dici resta tra noi, sempre.'], ['Anche online', 'Puoi scegliere la seduta in studio o in videochiamata.']]],
            'avvocato' => ['studio', 'scale', 'I tuoi diritti, *tutelati* con competenza', 'Consulenze in diritto civile, di famiglia, del lavoro e successioni, con risposte chiare e concrete.', [['Prima consulenza', 'Analizziamo il caso e ti spieghiamo le possibili strade.'], ['Linguaggio chiaro', 'Niente gergo: sai sempre a che punto è la tua pratica.'], ['In studio o online', 'Scegli tu come incontrarci.']]],
            'commercialista' => ['studio', 'chart', 'Tasse e conti, *finalmente* in ordine', 'Dichiarazioni, partite IVA, forfettario, ISEE e successioni: ti seguiamo passo dopo passo.', [['Scadenze sotto controllo', 'Ti ricordiamo noi cosa serve e quando.'], ['Consulenza su misura', 'Il regime fiscale più conveniente per la tua situazione.'], $book]],
            'consulente-finanziario' => ['studio', 'chart', 'Il tuo futuro, *pianificato* con cura', 'Investimenti, previdenza, protezione e passaggio generazionale: un piano costruito sui tuoi obiettivi.', [['Analisi gratuita', 'Partiamo da dove sei oggi e da dove vuoi arrivare.'], ['Piano personale', 'Strategie chiare, rischi spiegati, nessuna sorpresa.'], ['Revisioni periodiche', 'Aggiorniamo il piano quando cambia la tua vita.']]],
            'immobiliare' => ['studio', 'house', 'La casa giusta, *al momento* giusto', 'Compravendite e affitti con valutazioni accurate, visite organizzate e assistenza fino al rogito.', [['Valutazione gratuita', 'Scopri quanto vale davvero il tuo immobile.'], ['Visite su appuntamento', 'Prenota la visita negli orari che preferisci.'], ['Assistenza completa', 'Dalla proposta al rogito, sempre al tuo fianco.']]],
            'campi-sportivi' => ['sport', 'ball', 'Il tuo campo, *libero* quando vuoi tu', 'Padel, tennis, calcetto e altro: guarda le disponibilità e prenota in pochi tocchi.', [['Disponibilità in tempo reale', 'Vedi subito orari e campi liberi.'], ['Prenota per il gruppo', 'Blocca il campo e avvisa i tuoi amici.'], ['Noleggio attrezzatura', 'Racchette e palline disponibili in struttura.']]],
            'autoscuola' => ['officina', 'wheel', 'La tua patente, *passo* dopo passo', 'Guide per auto e moto con istruttori pazienti, teoria in aula e pratiche senza pensieri.', [['Guide su misura', 'Prenota le lezioni negli orari che ti fanno comodo.'], ['Istruttori esperti', 'Ti accompagniamo con calma fino all\'esame.'], ['Pratiche incluse', 'Iscrizione, rinnovi e documenti li seguiamo noi.']]],
            'altro' => ['neutro', 'calendar', 'Il tuo appuntamento, *in pochi* secondi', 'Scegli il servizio, il giorno e l\'orario libero: ti confermiamo subito la prenotazione.', [$book, $confirm, ['Sempre in contatto', 'Per qualsiasi dubbio scrivici o chiamaci.']]],
        ];
    }

    /**
     * Tutto ciò che serve alla pagina pubblica per una categoria.
     *
     * @param array<string,mixed> $cat voce di Categories::get()
     * @return array{style:array<string,string>,style_key:string,icon:string,headline:string,intro:string,points:array<int,array{0:string,1:string}>,image:string}
     */
    public static function forCategory(string $key, array $cat): array
    {
        $content = self::content();
        $c = $content[$key] ?? null;
        $styleKey = (string)($cat['theme'] ?? '');
        if (!isset(self::styles()[$styleKey])) {
            $styleKey = $c[0] ?? self::guessStyle($key . ' ' . (string)($cat['label'] ?? ''));
        }
        $label = (string)($cat['label'] ?? '');
        $c ??= [
            $styleKey, self::guessIcon($key . ' ' . $label),
            'Prenota il tuo appuntamento *in pochi* secondi',
            'Scegli cosa ti serve, il giorno e l\'orario libero: ti rispondiamo appena possibile.',
            $content['altro'][4],
        ];
        $c[2] = trim((string)($cat['headline'] ?? '')) !== '' ? (string)$cat['headline'] : $c[2];
        return [
            'style' => self::styles()[$styleKey],
            'style_key' => $styleKey,
            'icon' => $c[1],
            'headline' => $c[2],
            'intro' => $c[3],
            'points' => $c[4],
            'image' => (string)($cat['image_url'] ?? ''),
        ];
    }

    /** Stile per le categorie create dall'amministratore senza sceglierne uno. */
    public static function guessStyle(string $text): string
    {
        $t = mb_strtolower($text);
        $map = [
            'cipria' => ['estet', 'beauty', 'unghie', 'nail', 'parrucch', 'trucco', 'make', 'ciglia', 'spa', 'massagg'],
            'notte' => ['barb', 'tattoo', 'tatu', 'pierc'],
            'menta' => ['dent', 'medic', 'fisio', 'osteo', 'clinic', 'nutri', 'ottic', 'podolog', 'farmac', 'logoped'],
            'salvia' => ['psico', 'yoga', 'pilates', 'counsel', 'coach', 'natur'],
            'pesca' => ['vet', 'cane', 'cani', 'gatt', 'animal', 'pet', 'toelett'],
            'officina' => ['auto', 'moto', 'gomm', 'mecca', 'carrozz', 'officin', 'bici', 'lavaggio'],
            'casa' => ['idraul', 'elettric', 'casa', 'pulizi', 'giardin', 'imbianc', 'serrament', 'fabbro', 'condiz', 'caldai'],
            'studio' => ['avvoc', 'legal', 'notai', 'commerc', 'consul', 'finanz', 'immobil', 'assicur', 'agenzia', 'studio'],
            'sport' => ['campo', 'campi', 'sport', 'padel', 'tennis', 'calc', 'palestra', 'piscin', 'golf'],
        ];
        foreach ($map as $style => $words) {
            foreach ($words as $w) {
                if (str_contains($t, $w)) {
                    return $style;
                }
            }
        }
        return 'neutro';
    }

    private static function guessIcon(string $text): string
    {
        return match (self::guessStyle($text)) {
            'cipria' => 'flower', 'notte' => 'razor', 'menta' => 'body', 'salvia' => 'leaf', 'pesca' => 'paw',
            'officina' => 'wrench', 'casa' => 'house', 'studio' => 'chart', 'sport' => 'ball', default => 'calendar',
        };
    }

    /** Titolo con le parole tra *asterischi* in corsivo (testo già escapato). */
    public static function headlineHtml(string $headline): string
    {
        $h = htmlspecialchars($headline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return (string)preg_replace('/\*([^*]{1,60})\*/u', '<em>$1</em>', $h);
    }

    /** Disegni delle icone (tratto, 24x24). */
    private static function iconPaths(string $icon): string
    {
        return match ($icon) {
            'scissors' => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.1 15.9M14.5 14.5L20 20M8.1 8.1L12 12"/>',
            'razor' => '<path d="M4 20l7-7"/><path d="M9.5 11.5l6-6a3 3 0 014.2 4.2l-6 6z"/><path d="M13 8l3 3"/>',
            'polish' => '<path d="M9 2h6v5H9z"/><path d="M8 7h8l1 3v10a2 2 0 01-2 2H9a2 2 0 01-2-2V10z"/><path d="M7 13h10"/>',
            'flower' => '<circle cx="12" cy="12" r="2.5"/><path d="M12 9.5C12 5 9 3 9 3s-2 4 3 6.5zM12 9.5C12 5 15 3 15 3s2 4-3 6.5zM14.5 12c4.5 0 6.5-3 6.5-3s-4-2-6.5 3zM9.5 12C5 12 3 9 3 9s4-2 6.5 3zM12 14.5V22"/><path d="M12 18c-2 0-4-1-5-3M12 18c2 0 4-1 5-3"/>',
            'wrench' => '<path d="M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z"/>',
            'pen' => '<path d="M15 3l6 6-9 9H6v-6z"/><path d="M3 21l3-3"/><path d="M13 5l6 6"/>',
            'tooth' => '<path d="M7 3c-2.5 0-4 2-4 4.5 0 3 1.5 4.5 2 7.5.5 3 1 6 2.5 6s2-3 2.5-5c.4-1.5 3.6-1.5 4 0 .5 2 1 5 2.5 5s2-3 2.5-6c.5-3 2-4.5 2-7.5C21 5 19.5 3 17 3c-2 0-3 1-5 1S9 3 7 3z"/>',
            'paw' => '<circle cx="6" cy="10" r="2"/><circle cx="10" cy="5.5" r="2"/><circle cx="14" cy="5.5" r="2"/><circle cx="18" cy="10" r="2"/><path d="M12 11c-3 0-6 4.5-6 7a2.5 2.5 0 003.5 2.3c1.6-.6 3.4-.6 5 0A2.5 2.5 0 0018 18c0-2.5-3-7-6-7z"/>',
            'tire' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M12 3v5M12 16v5M3 12h5M16 12h5"/>',
            'drop' => '<path d="M12 3s7 7.5 7 12a7 7 0 01-14 0c0-4.5 7-12 7-12z"/><path d="M9 15a3 3 0 003 3"/>',
            'bolt' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
            'body' => '<circle cx="12" cy="4.5" r="2"/><path d="M5 9l7 1.5L19 9M12 10.5V15l-3 6M12 15l3 6"/>',
            'leaf' => '<path d="M5 19c0-9 6-15 15-15 0 9-6 15-15 15z"/><path d="M5 19l9-9"/>',
            'scale' => '<path d="M12 3v18M7 21h10M5 7h14"/><path d="M5 7l-3 6a3 3 0 006 0zM19 7l-3 6a3 3 0 006 0z"/>',
            'chart' => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 6-7"/><path d="M16 7h4v4"/>',
            'house' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
            'ball' => '<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6c3 3 3 9.8 0 12.8M18.4 5.6c-3 3-3 9.8 0 12.8"/>',
            'wheel' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2"/><path d="M3.5 10.5c5-1.5 12-1.5 17 0M12 14v7"/>',
            default => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="M8 15l2.5 2.5L16 13"/>',
        };
    }

    public static function icon(string $icon, int $size = 24, string $stroke = 'currentColor', float $width = 1.6): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="' . $stroke . '" stroke-width="' . $width . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . self::iconPaths($icon) . '</svg>';
    }

    /**
     * Illustrazione per l'arco della copertina quando non c'è una foto: sfondo sfumato,
     * forme morbide e la grande icona della categoria.
     *
     * @param array<string,string> $s stile
     */
    public static function illustration(array $s, string $icon): string
    {
        $acc = $s['acc'];
        $acc2 = $s['acc2'];
        $soft = $s['soft'];
        $surface = $s['surface'];
        $dark = $s['bg'] === '#1a1716';
        $line = $dark ? $acc : $acc;
        $paths = self::iconPaths($icon);
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500" preserveAspectRatio="xMidYMid slice" role="img" aria-hidden="true">
  <defs>
    <linearGradient id="g1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="$acc2"/><stop offset="1" stop-color="$soft"/></linearGradient>
    <radialGradient id="g2" cx=".7" cy=".25" r=".7"><stop offset="0" stop-color="$surface" stop-opacity=".9"/><stop offset="1" stop-color="$surface" stop-opacity="0"/></radialGradient>
  </defs>
  <rect width="400" height="500" fill="url(#g1)"/>
  <rect width="400" height="500" fill="url(#g2)"/>
  <circle cx="330" cy="420" r="150" fill="$acc" opacity=".10"/>
  <circle cx="60" cy="90" r="90" fill="$surface" opacity=".35"/>
  <path d="M-20 380 C 90 320, 170 460, 420 360 L 420 520 L -20 520 Z" fill="$acc" opacity=".14"/>
  <path d="M-20 420 C 120 370, 220 500, 420 410 L 420 520 L -20 520 Z" fill="$acc" opacity=".16"/>
  <g transform="translate(110 130) scale(7.5)" fill="none" stroke="$line" stroke-width=".55" stroke-linecap="round" stroke-linejoin="round" opacity=".9">$paths</g>
  <g fill="$acc" opacity=".55"><circle cx="70" cy="300" r="4"/><circle cx="330" cy="120" r="3"/><circle cx="300" cy="300" r="2.5"/><circle cx="95" cy="200" r="2"/></g>
</svg>
SVG;
    }
}
