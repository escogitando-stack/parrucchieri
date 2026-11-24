<?php
declare(strict_types=1);

namespace App;

/**
 * Dati del sito di presentazione modificabili dal pannello /admin (box "Sito, contatti e pagine legali"):
 * titolare, WhatsApp, social, testi di Privacy Policy e Cookie Policy.
 * Sono salvati nel database (_meta: site_settings) con questi valori di partenza.
 */
final class Site
{
    public const DEFAULTS = [
        'owner_name' => 'Sh1ftup Adv',
        'owner_cf' => 'CLLFNC01E22G702P',
        'owner_vat' => '',
        'owner_address' => '',
        'whatsapp' => '393381587990',
        'whatsapp_text' => 'Ciao, vorrei informazioni su Appuntamenti4me',
        'facebook' => 'https://www.facebook.com/Sh1ftup',
        'instagram' => 'https://www.instagram.com/sh1ftup/',
        'youtube' => 'https://www.youtube.com/@sh1ftup503',
        'linkedin' => '',
        'privacy_text' => '',
        'cookie_text' => '',
    ];

    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /** @return array<string,string> */
    public static function settings(?Db $db = null): array
    {
        if (self::$cache !== null && $db === null) {
            return self::$cache;
        }
        $saved = [];
        if ($db !== null) {
            try {
                $raw = $db->metaGet('site_settings');
                $saved = is_string($raw) ? (array)json_decode($raw, true) : [];
            } catch (\Throwable) {
                $saved = [];
            }
        }
        $out = self::DEFAULTS;
        foreach (self::DEFAULTS as $k => $_) {
            if (array_key_exists($k, $saved) && is_string($saved[$k])) {
                $out[$k] = $saved[$k];
            }
        }
        return self::$cache = $out;
    }

    /** Carica i dati dal database (una volta per richiesta). */
    public static function boot(Db $db): void
    {
        self::$cache = null;
        self::settings($db);
    }

    /**
     * Salva i dati dal modulo admin. Restituisce un messaggio d'errore o null.
     * @param array<string,mixed> $in
     */
    public static function save(Db $db, array $in): ?string
    {
        $out = [];
        foreach (self::DEFAULTS as $k => $_) {
            $v = trim(str_replace("\r\n", "\n", (string)($in[$k] ?? '')));
            $out[$k] = mb_substr($v, 0, in_array($k, ['privacy_text', 'cookie_text'], true) ? 20000 : 300);
        }
        $out['whatsapp'] = preg_replace('/\D/', '', $out['whatsapp']) ?? '';
        if ($out['whatsapp'] !== '' && (strlen($out['whatsapp']) < 8 || strlen($out['whatsapp']) > 15)) {
            return 'Il numero WhatsApp deve avere il prefisso internazionale, solo cifre (es. 393331234567).';
        }
        foreach (['facebook', 'instagram', 'youtube', 'linkedin'] as $k) {
            if ($out[$k] !== '' && !preg_match('#^https://[^\s"<>]+$#', $out[$k])) {
                return 'Il link ' . ucfirst($k) . ' deve iniziare con https://';
            }
        }
        $db->metaSet('site_settings', (string)json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        self::$cache = $out;
        return null;
    }

    public static function whatsappUrl(?string $text = null): string
    {
        $s = self::settings();
        if ($s['whatsapp'] === '') {
            return '';
        }
        $msg = $text ?? $s['whatsapp_text'];
        return 'https://wa.me/' . $s['whatsapp'] . ($msg !== '' ? '?text=' . rawurlencode($msg) : '');
    }

    /** @return array<string,string> rete => url, solo quelli compilati */
    public static function socials(): array
    {
        $s = self::settings();
        $out = [];
        foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'] as $k => $_) {
            if ($s[$k] !== '') {
                $out[$k] = $s[$k];
            }
        }
        return $out;
    }

    public static function socialIcon(string $net): string
    {
        $p = [
            'facebook' => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H8v4h2v8h4v-8h3l1-4h-4V8z"/>',
            'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.3"/>',
            'youtube' => '<path d="M22 8.2a3 3 0 0 0-2.1-2.1C18 5.6 12 5.6 12 5.6s-6 0-7.9.5A3 3 0 0 0 2 8.2 31 31 0 0 0 1.6 12 31 31 0 0 0 2 15.8a3 3 0 0 0 2.1 2.1c1.9.5 7.9.5 7.9.5s6 0 7.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .4-3.8 31 31 0 0 0-.4-3.8zM10 15V9l5.2 3L10 15z"/>',
            'linkedin' => '<path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9.5h4V21H3zM9.5 9.5h3.8v1.6h.1c.5-1 1.8-2 3.8-2 4 0 4.8 2.6 4.8 6V21h-4v-5.1c0-1.2 0-2.8-1.7-2.8s-2 1.3-2 2.7V21h-4z"/>',
            'whatsapp' => '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.8-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.4-.3z"/>',
        ][$net] ?? '';
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">' . $p . '</svg>';
    }

    /** Logo del marchio: calendario con spunta, in SVG. */
    public static function logoSvg(int $size = 28): string
    {
        return '<svg viewBox="0 0 64 64" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false">'
            . '<rect x="4" y="8" width="56" height="52" rx="12" fill="#be185d"/>'
            . '<rect x="4" y="8" width="56" height="16" rx="12" fill="#9d174d"/><rect x="4" y="18" width="56" height="6" fill="#9d174d"/>'
            . '<rect x="17" y="2" width="6" height="14" rx="3" fill="#1c1917"/><rect x="41" y="2" width="6" height="14" rx="3" fill="#1c1917"/>'
            . '<path d="M20 41l8 8 16-17" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    /** Riga con i dati fiscali del titolare. */
    public static function ownerLine(): string
    {
        $s = self::settings();
        $parts = [$s['owner_name']];
        if ($s['owner_vat'] !== '') {
            $parts[] = 'P. IVA ' . $s['owner_vat'];
        }
        if ($s['owner_cf'] !== '') {
            $parts[] = 'C.F. ' . $s['owner_cf'];
        }
        if ($s['owner_address'] !== '') {
            $parts[] = $s['owner_address'];
        }
        return implode(' · ', array_filter($parts, static fn($p) => $p !== ''));
    }

    /** Testo di partenza della Privacy Policy (se l'admin non l'ha personalizzato). */
    public static function defaultPrivacy(string $email): string
    {
        $owner = self::ownerLine();
        return <<<TXT
## Titolare del trattamento
$owner. Email: $email.

## Quali dati raccogliamo
- Dati dell'account dei negozi: nome dell'attività, email, password (salvata cifrata), dati di contatto e impostazioni del negozio.
- Dati inseriti dai negozi nella propria agenda: clienti, appuntamenti, servizi, note. Per questi dati il negozio è titolare del trattamento e Appuntamenti4me agisce come responsabile del trattamento.
- Dati di chi prenota dalla pagina di un negozio: nome, telefono e/o email, servizio e orario scelti.
- Dati tecnici: indirizzo IP e registri del server, conservati per sicurezza e prevenzione degli abusi.

## Perché li usiamo (finalità e base giuridica)
- Fornire il servizio di agenda e prenotazione online (esecuzione del contratto).
- Inviare le email di servizio: conferme, promemoria, recupero password (esecuzione del contratto).
- Gestire abbonamenti e pagamenti (obbligo contrattuale e obblighi fiscali).
- Proteggere il sito da accessi non autorizzati e abusi (legittimo interesse).

## Pagamenti
I pagamenti avvengono tramite Stripe: i dati della carta vengono inseriti direttamente sui sistemi di Stripe e non passano da questo sito.

## Con chi li condividiamo
Fornitori che ci aiutano a erogare il servizio (hosting, invio email, Stripe per i pagamenti), nominati responsabili del trattamento quando previsto. Non vendiamo i dati a nessuno.

## Per quanto tempo
I dati dell'account restano finché l'account è attivo; dopo la cancellazione vengono eliminati, salvo quelli che la legge ci obbliga a conservare (per esempio i dati di fatturazione, per 10 anni).

## I tuoi diritti
Puoi chiedere in ogni momento accesso, rettifica, cancellazione, limitazione, portabilità dei dati e opporti al trattamento scrivendo a $email. Puoi anche presentare reclamo al Garante per la protezione dei dati personali (www.garanteprivacy.it).

## Cookie
Per i cookie usati dal sito leggi la Cookie Policy.
TXT;
    }

    public static function defaultCookie(string $email): string
    {
        $owner = self::ownerLine();
        return <<<TXT
## Cosa sono i cookie
I cookie sono piccoli file che il sito salva nel browser per funzionare o per ricordare le tue scelte.

## Cookie tecnici (sempre attivi)
- appuntamenti: cookie di sessione che ti tiene collegato al tuo account e protegge i moduli. Durata: fino a 30 giorni.
- a4m_consent: ricorda le tue scelte sui cookie. Durata: 6 mesi.
Questi cookie sono necessari al funzionamento del sito e non richiedono consenso.

## Cookie statistici e di marketing
Al momento il sito non usa cookie statistici né di marketing. Se in futuro verranno aggiunti, saranno attivati solo dopo il tuo consenso dal banner.

## Come cambiare le tue scelte
Puoi cambiare le preferenze in ogni momento dal link "Preferenze cookie" in fondo a ogni pagina, oppure cancellare i cookie dalle impostazioni del browser.

## Titolare
$owner. Email: $email.
TXT;
    }

    /** Trasforma il testo semplice dell'admin (## titoli, - elenchi, paragrafi) in HTML sicuro. */
    public static function textToHtml(string $text): string
    {
        $html = '';
        $list = false;
        foreach (preg_split('/\n/', $text) ?: [] as $line) {
            $line = rtrim($line);
            $isItem = str_starts_with($line, '- ');
            if ($list && !$isItem) {
                $html .= '</ul>';
                $list = false;
            }
            if ($line === '') {
                continue;
            }
            $e = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
            if (str_starts_with($line, '## ')) {
                $html .= '<h2>' . substr($e, 3) . '</h2>';
            } elseif ($isItem) {
                if (!$list) {
                    $html .= '<ul>';
                    $list = true;
                }
                $html .= '<li>' . substr($e, 2) . '</li>';
            } else {
                $html .= '<p>' . $e . '</p>';
            }
        }
        return $html . ($list ? '</ul>' : '');
    }
}
