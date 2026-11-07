<?php
declare(strict_types=1);

namespace App;

/** Pagine pubbliche (vetrina, registrazione, accesso, abbonamento) generate dal server. */
final class Pages
{
    public static function h(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    /** @param array<string,mixed> $cfg */
    private static function name(array $cfg): string
    {
        return (string)($cfg['site_name'] ?? 'Appuntamenti4me');
    }

    /** @param array<string,mixed> $cfg */
    private static function layout(array $cfg, string $title, string $body, bool $narrow = false, bool $wide = false, ?string $nav = null, string $head = ''): string
    {
        $name = self::h(self::name($cfg));
        $t = self::h($title);
        $wrap = $narrow ? 'wrap narrow' : ($wide ? 'wrap wide' : 'wrap');
        $nav ??= '<a href="/login">Accedi</a><a href="/registrati">Prova gratis</a>';
        $links = '';
        foreach (['terms_url' => 'Termini', 'privacy_url' => 'Privacy'] as $k => $label) {
            if (!empty($cfg[$k])) {
                $links .= ' · <a href="' . self::h((string)$cfg[$k]) . '">' . $label . '</a>';
            }
        }
        return <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>$t</title>
$head<style>
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{margin:0;font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif;color:#1c1917;background:#faf8f7}
  a{color:#be185d}
  header.top{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;max-width:1040px;margin:0 auto;padding:18px 20px}
  header.top .brand{font-weight:700;font-size:18px;color:#1c1917;text-decoration:none}
  header.top nav a{margin-left:16px;text-decoration:none;font-weight:600;font-size:15px}
  .wrap{max-width:1040px;margin:0 auto;padding:0 20px}
  .narrow{max-width:440px}
  .wide{max-width:1240px}
  .tbl{overflow-x:auto;background:#fff;border:1px solid #e7e5e4;border-radius:14px}
  table{border-collapse:collapse;width:100%;font-size:14px}
  th,td{padding:10px 12px;border-bottom:1px solid #f0eeed;text-align:left;vertical-align:top}
  th{background:#faf8f7;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#78716c;white-space:nowrap}
  tr:last-child td{border-bottom:0}
  .badge{display:inline-block;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:600}
  .b-trial{background:#fef3c7;color:#92400e}.b-active{background:#dcfce7;color:#166534}.b-expired{background:#fee2e2;color:#991b1b}.b-suspended{background:#e7e5e4;color:#44403c}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin:8px 0 20px}
  .stat{background:#fff;border:1px solid #e7e5e4;border-radius:12px;padding:14px}.stat b{display:block;font-size:26px}.stat span{font-size:13px;color:#78716c}
  .filters{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}.filters input,.filters select{width:auto;height:40px;min-width:180px}.filters .btn{padding:9px 18px}
  details.act summary{cursor:pointer;color:#be185d;font-weight:600;font-size:13px}
  details.act .menu{margin-top:8px;display:flex;flex-direction:column;gap:6px;min-width:210px}
  details.act form{display:flex;gap:6px;align-items:center;margin:0}
  details.act input[type=date],details.act input[type=number]{height:32px;width:auto;padding:0 8px;font-size:13px}
  .mini{border:1px solid #e7c5d4;background:#fff;color:#be185d;border-radius:8px;padding:5px 10px;font-size:13px;font-weight:600;cursor:pointer}
  .mini.danger{border-color:#fca5a5;color:#b91c1c}
  .pager{display:flex;gap:10px;justify-content:center;margin:16px 0;font-size:14px}
  code.pw{font-size:18px;background:#fff;border:1px solid #e7e5e4;border-radius:8px;padding:4px 10px}
  .btn{display:inline-block;background:#be185d;color:#fff!important;border:0;border-radius:10px;padding:13px 22px;font-size:16px;font-weight:600;text-decoration:none;cursor:pointer}
  .btn:hover{background:#9d174d}
  .btn.alt{background:#fff;color:#be185d!important;border:1px solid #e7c5d4}
  .hero{padding:56px 0 36px;text-align:center}
  .hero h1{font-size:clamp(30px,5vw,48px);line-height:1.15;margin:0 0 16px;letter-spacing:-.02em}
  .hero p{font-size:19px;color:#57534e;max-width:640px;margin:0 auto 26px}
  .chips{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin:26px 0 0}
  .chip{background:#fff;border:1px solid #e7e5e4;border-radius:999px;padding:6px 14px;font-size:14px;color:#44403c}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;margin:30px 0}
  .card{background:#fff;border:1px solid #e7e5e4;border-radius:14px;padding:22px}
  .card h3{margin:0 0 6px;font-size:17px}
  .card p{margin:0;color:#57534e;font-size:15px}
  h2{font-size:28px;margin:46px 0 6px;text-align:center;letter-spacing:-.01em}
  .sub{text-align:center;color:#57534e;margin:0 0 8px}
  .price{font-size:34px;font-weight:700}
  .price small{font-size:15px;font-weight:500;color:#78716c}
  .plans{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;max-width:620px;margin:24px auto}
  .plans ul{padding-left:18px;margin:12px 0;color:#44403c;font-size:15px}
  .cta{text-align:center;margin:36px 0 10px}
  section.blk{scroll-margin-top:16px}
  .steps{counter-reset:s;display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;margin:24px 0}
  .steps .card{position:relative;padding-top:46px}
  .steps .card::before{counter-increment:s;content:counter(s);position:absolute;top:14px;left:20px;width:26px;height:26px;border-radius:50%;background:#be185d;color:#fff;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center}
  .tick{list-style:none;padding:0;margin:12px 0 0}.tick li{padding:3px 0 3px 24px;position:relative;font-size:15px;color:#44403c}.tick li::before{content:"✓";position:absolute;left:0;color:#be185d;font-weight:700}
  .plan.best{border:2px solid #be185d}
  .tag{display:inline-block;background:#fce7f3;color:#9d174d;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:700;margin-bottom:6px}
  .faq{max-width:760px;margin:22px auto}
  .faq details{background:#fff;border:1px solid #e7e5e4;border-radius:12px;padding:14px 18px;margin-bottom:10px}
  .faq summary{cursor:pointer;font-weight:600;list-style:none;display:flex;justify-content:space-between;gap:12px}
  .faq summary::after{content:"+";color:#be185d;font-size:20px;line-height:1}
  .faq details[open] summary::after{content:"–"}
  .faq details p{margin:10px 0 0;color:#57534e;font-size:15px}
  .note{text-align:center;font-size:14px;color:#78716c;margin:8px 0 0}
  footer{max-width:1040px;margin:50px auto 30px;padding:0 20px;color:#78716c;font-size:14px;text-align:center}
  form.box{background:#fff;border:1px solid #e7e5e4;border-radius:14px;padding:26px;box-shadow:0 6px 24px rgba(0,0,0,.05);margin:24px 0}
  form.box h1{margin:0 0 4px;font-size:22px}
  form.box p.sub{text-align:left;margin:0 0 18px;font-size:14px}
  label{display:block;font-size:13px;font-weight:600;margin:14px 0 6px}
  input,select{width:100%;height:44px;padding:0 12px;border:1px solid #d6d3d1;border-radius:10px;font-size:16px;background:#fff}
  input:focus,select:focus{outline:2px solid #ec4899;outline-offset:1px}
  input[type=checkbox]{width:auto;height:auto;margin-right:8px}
  label.check{font-weight:400;display:flex;align-items:flex-start;font-size:14px}
  form.box button{margin-top:18px;width:100%}
  .err{margin:0 0 14px;padding:10px 12px;border-radius:8px;background:#fef2f2;color:#b91c1c;font-size:14px}
  .ok{margin:0 0 14px;padding:10px 12px;border-radius:8px;background:#f0fdf4;color:#166534;font-size:14px}
  .hp{position:absolute;left:-9999px;height:0;overflow:hidden}
  .alt-link{text-align:center;font-size:14px;color:#57534e}
</style>
</head>
<body>
<header class="top">
  <a class="brand" href="/">📅 $name</a>
  <nav>$nav</nav>
</header>
<div class="$wrap">
$body
</div>
<footer>© {$name}$links</footer>
</body>
</html>
HTML;
    }

    /**
     * Domande frequenti: dicono solo ciò che l'app fa davvero.
     *
     * @param array<string,mixed> $cfg
     * @return array<int,array{0:string,1:string}>
     */
    public static function faq(array $cfg): array
    {
        $days = (int)($cfg['trial_days'] ?? 30);
        $pricing = $cfg['pricing'] ?? [];
        $monthly = (string)($pricing['monthly_label'] ?? '');
        $yearly = (string)($pricing['yearly_label'] ?? '');
        $prices = [];
        if ($monthly !== '') {
            $prices[] = "il piano mensile costa $monthly";
        }
        if ($yearly !== '') {
            $prices[] = "il piano annuale costa $yearly";
        }
        $cost = "Provi tutto gratis per $days giorni. Poi " . ($prices ? implode(' e ', $prices) . '.' : 'scegli tra il piano mensile e quello annuale.')
            . ' Il prezzo è lo stesso per tutte le funzioni, senza limiti di clienti, appuntamenti o collaboratori.';
        $contact = !empty($cfg['contact_email'])
            ? 'Scrivici a ' . (string)$cfg['contact_email'] . ': ti rispondiamo appena possibile.'
            : 'Contattaci dal sito: ti rispondiamo appena possibile.';

        return [
            ['Quanto costa?', $cost],
            ['Serve la carta di credito per provare?', "No. Ti registri con nome dell'attività, email e password e puoi usare l'agenda per $days giorni senza inserire dati di pagamento."],
            ['Cosa succede quando finisce la prova?', "L'agenda si blocca e ti viene proposto di scegliere un piano. I tuoi dati restano al loro posto: quando attivi l'abbonamento riprendi da dove eri."],
            ['Posso disdire quando voglio?', "Sì, dalla pagina Abbonamento, senza telefonare a nessuno. L'accesso resta attivo fino alla fine del periodo già pagato."],
            ['Come si paga?', 'Online, con carta, tramite Stripe, uno dei sistemi di pagamento più usati al mondo. I dati della carta non passano da questo sito.'],
            ['Funziona da telefono e tablet?', "Sì. Funziona dal browser di telefono, tablet e computer, senza installare nulla. L'indirizzo è sempre lo stesso."],
            ["Va bene per la mia attività?", "È pensata per chiunque lavori su appuntamento: parrucchieri, barbieri, centri unghie ed estetici, officine, studi di tatuaggi e altre attività. Scegli il tipo di attività e trovi già servizi e nomi adatti, poi cambi tutto come preferisci."],
            ['Posso gestire più collaboratori?', "Sì. L'agenda ha una colonna per ogni collaboratore, puoi bloccare gli orari (pausa pranzo, ferie) e ti avvisa quando due appuntamenti si sovrappongono."],
            ['I miei dati sono separati da quelli degli altri negozi?', 'Sì. Ogni negozio ha il proprio spazio e vede soltanto i propri clienti, appuntamenti, servizi e prodotti.'],
            ['I miei clienti possono prenotare da soli online?', "Non ancora: l'agenda la gestisci tu e il tuo staff. Le prenotazioni online da parte dei clienti non sono ancora disponibili."],
            ['Invia promemoria ai clienti?', 'Non ancora. Per ora non ci sono SMS o messaggi automatici di promemoria.'],
            ['Come ricevo assistenza?', $contact],
        ];
    }

    /** @param array<string,mixed> $cfg */
    public static function landing(array $cfg): string
    {
        $days = (int)($cfg['trial_days'] ?? 30);
        $name = self::name($cfg);
        $chips = '';
        foreach (Categories::options() as $label) {
            $chips .= '<span class="chip">' . self::h($label) . '</span>';
        }
        $pricing = $cfg['pricing'] ?? [];
        $monthly = (string)($pricing['monthly_label'] ?? '');
        $yearly = (string)($pricing['yearly_label'] ?? '');

        $included = '<ul class="tick"><li>Agenda a colonne per collaboratore</li><li>Clienti con storico e note</li><li>Servizi, prodotti e magazzino</li><li>Incassi dei servizi completati</li><li>Nessun limite di clienti, appuntamenti o collaboratori</li></ul>';
        $plans = '<div class="card plan"><span class="tag">Per iniziare</span><h3>Prova gratuita</h3><div class="price">0 € <small>per ' . $days . ' giorni</small></div>'
            . '<ul class="tick"><li>Tutte le funzioni incluse</li><li>Nessuna carta di credito</li><li>I dati restano se poi scegli un piano</li></ul>'
            . '<p style="margin-top:16px"><a class="btn" href="/registrati">Inizia gratis</a></p></div>';
        if ($monthly !== '') {
            $plans .= '<div class="card plan' . ($yearly === '' ? ' best' : '') . '"><h3>Mensile</h3><div class="price">' . self::h($monthly) . '</div>' . $included . '<p class="note" style="text-align:left">Disdici quando vuoi.</p></div>';
        }
        if ($yearly !== '') {
            $plans .= '<div class="card plan best"><span class="tag">Il più conveniente</span><h3>Annuale</h3><div class="price">' . self::h($yearly) . '</div>' . $included . '<p class="note" style="text-align:left">Un solo pagamento all\'anno.</p></div>';
        }
        $costs = '<section class="blk" id="costi"><h2>Costi chiari</h2><p class="sub">Un solo prezzo, tutte le funzioni. Prima provi, poi decidi.</p><div class="plans" style="max-width:960px">'
            . $plans . '</div><p class="note">Paghi online con carta tramite Stripe. Puoi disdire dalla pagina Abbonamento.</p></section>';

        $faqHtml = '';
        $faqLd = [];
        foreach (self::faq($cfg) as [$q, $a]) {
            $faqHtml .= '<details><summary>' . self::h($q) . '</summary><p>' . self::h($a) . '</p></details>';
            $faqLd[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
        }
        $ld = json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqLd], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        $desc = "Agenda online per parrucchieri, centri estetici, unghie, officine e studi di tatuaggi: appuntamenti, clienti, collaboratori e incassi. Prova gratis per $days giorni.";
        $head = '<meta name="description" content="' . self::h($desc) . '">' . "\n"
            . '<meta property="og:title" content="' . self::h($name) . ' · Agenda appuntamenti per il tuo negozio">' . "\n"
            . '<meta property="og:description" content="' . self::h($desc) . '">' . "\n"
            . '<script type="application/ld+json">' . $ld . '</script>' . "\n";

        $body = <<<HTML
<section class="hero">
  <h1>L'agenda degli appuntamenti per il tuo negozio</h1>
  <p>Prenotazioni, clienti, collaboratori e incassi in un unico posto. Funziona da telefono, tablet e computer. Provalo gratis per $days giorni.</p>
  <a class="btn" href="/registrati">Inizia la prova gratuita</a>
  <p style="font-size:14px;margin:12px auto 0;color:#78716c">Nessuna carta di credito richiesta.</p>
  <div class="chips">$chips</div>
</section>

<section class="blk" id="vantaggi">
  <h2>Perché usarla</h2>
  <p class="sub">Meno carta e messaggi sparsi, più tempo per il tuo lavoro.</p>
  <div class="grid">
    <div class="card"><h3>Tutto in un posto</h3><p>Agenda, clienti, collaboratori, servizi, prodotti e incassi: niente più quaderni, fogli e chat da incrociare.</p></div>
    <div class="card"><h3>Niente sovrapposizioni</h3><p>Se due appuntamenti si accavallano per lo stesso collaboratore, l'app ti avvisa prima di confermare.</p></div>
    <div class="card"><h3>Pronta per il tuo mestiere</h3><p>Scegli il tipo di attività e trovi servizi e nomi già adatti (per esempio interventi e ricambi per un'officina). Poi li modifichi come vuoi.</p></div>
    <div class="card"><h3>Sempre con te</h3><p>Dal browser di telefono, tablet o computer, senza installare niente. Controlli la giornata anche fuori dal negozio.</p></div>
    <div class="card"><h3>Storico dei clienti</h3><p>Ogni cliente ha la sua scheda con gli appuntamenti passati e le note, per ricordare gusti, lavori e dettagli.</p></div>
    <div class="card"><h3>Sotto controllo</h3><p>Vedi quanti appuntamenti hai oggi e in arrivo, l'incasso dei servizi completati e i prodotti in esaurimento.</p></div>
    <div class="card"><h3>Semplice e in italiano</h3><p>Importi in euro, date e orari all'italiana, comandi chiari: si impara in pochi minuti.</p></div>
    <div class="card"><h3>Prezzo senza sorprese</h3><p>Un solo prezzo con tutte le funzioni, senza limiti di clienti o appuntamenti. Prima provi gratis, poi scegli.</p></div>
  </div>
</section>

<section class="blk" id="come-funziona">
  <h2>Come funziona</h2>
  <div class="steps">
    <div class="card"><h3>Ti registri</h3><p>Nome dell'attività, tipo di attività, email e password. Ci vuole un minuto e non serve la carta.</p></div>
    <div class="card"><h3>Trovi già i servizi</h3><p>L'agenda parte con servizi e collaboratore pronti. Cambi prezzi, durate e nomi, aggiungi i tuoi clienti.</p></div>
    <div class="card"><h3>Gestisci gli appuntamenti</h3><p>Prenoti, sposti, completi e annulli dall'agenda. Dopo la prova scegli se continuare.</p></div>
  </div>
</section>

$costs

<section class="blk" id="faq">
  <h2>Domande frequenti</h2>
  <div class="faq">$faqHtml</div>
</section>

<div class="cta"><a class="btn" href="/registrati">Crea il tuo account gratis</a><p class="note">$days giorni di prova, nessuna carta di credito.</p></div>
HTML;
        $nav = '<a href="#vantaggi">Vantaggi</a><a href="#costi">Costi</a><a href="#faq">Domande</a><a href="/login">Accedi</a><a class="btn" style="color:#fff;padding:8px 16px;margin-left:16px" href="/registrati">Prova gratis</a>';
        return self::layout($cfg, $name . ' · Agenda appuntamenti per il tuo negozio', $body, false, false, $nav, $head);
    }

    /**
     * @param array<string,mixed> $cfg
     * @param array<string,string> $old
     */
    public static function register(array $cfg, ?string $error = null, array $old = []): string
    {
        $days = (int)($cfg['trial_days'] ?? 30);
        $options = '';
        foreach (Categories::options() as $key => $label) {
            $sel = ($old['category'] ?? 'parrucchiere') === $key ? ' selected' : '';
            $options .= '<option value="' . self::h($key) . "\"$sel>" . self::h($label) . '</option>';
        }
        $err = $error ? '<p class="err">' . self::h($error) . '</p>' : '';
        $terms = '';
        if (!empty($cfg['terms_url']) || !empty($cfg['privacy_url'])) {
            $links = [];
            if (!empty($cfg['terms_url'])) {
                $links[] = '<a href="' . self::h((string)$cfg['terms_url']) . '" target="_blank" rel="noopener">Termini</a>';
            }
            if (!empty($cfg['privacy_url'])) {
                $links[] = '<a href="' . self::h((string)$cfg['privacy_url']) . '" target="_blank" rel="noopener">Privacy</a>';
            }
            $terms = '<label class="check"><input type="checkbox" name="terms" value="1" required> <span>Ho letto e accetto ' . implode(' e ', $links) . '.</span></label>';
        }
        $b = self::h($old['business'] ?? '');
        $e = self::h($old['email'] ?? '');
        $min = Auth::MIN_PASSWORD;
        $body = <<<HTML
<form class="box" method="post" action="/registrati">
  <h1>Prova gratis per $days giorni</h1>
  <p class="sub">Crea il tuo account: ti servono un minuto. Nessuna carta richiesta.</p>
  $err
  <label for="business">Nome dell'attività</label>
  <input id="business" name="business" value="$b" maxlength="120" required autofocus>
  <label for="category">Che attività hai?</label>
  <select id="category" name="category">$options</select>
  <label for="email">Email</label>
  <input id="email" name="email" type="email" value="$e" autocomplete="email" required>
  <label for="password">Password (almeno $min caratteri)</label>
  <input id="password" name="password" type="password" minlength="$min" autocomplete="new-password" required>
  <div class="hp" aria-hidden="true"><label>Sito web <input name="website" tabindex="-1" autocomplete="off"></label></div>
  $terms
  <button class="btn" type="submit">Crea account e inizia</button>
</form>
<p class="alt-link">Hai già un account? <a href="/login">Accedi</a></p>
HTML;
        return self::layout($cfg, 'Registrati · ' . self::name($cfg), $body, true);
    }

    /** @param array<string,mixed> $cfg */
    public static function login(array $cfg, ?string $error = null, string $email = ''): string
    {
        $err = $error ? '<p class="err">' . self::h($error) . '</p>' : '';
        $e = self::h($email);
        $reg = ($cfg['allow_registration'] ?? true) ? '<p class="alt-link">Non hai un account? <a href="/registrati">Prova gratis</a></p>' : '';
        $body = <<<HTML
<form class="box" method="post" action="/login">
  <h1>Accedi</h1>
  <p class="sub">Entra nella tua agenda.</p>
  $err
  <label for="email">Email</label>
  <input id="email" name="email" type="email" value="$e" autocomplete="email" required autofocus>
  <label for="password">Password</label>
  <input id="password" name="password" type="password" autocomplete="current-password" required>
  <button class="btn" type="submit">Accedi</button>
</form>
$reg
HTML;
        return self::layout($cfg, 'Accedi · ' . self::name($cfg), $body, true);
    }

    public static function closed(array $cfg): string
    {
        return self::layout($cfg, 'Registrazioni chiuse', '<form class="box"><h1>Registrazioni chiuse</h1><p class="sub">Al momento non è possibile creare nuovi account.</p></form><p class="alt-link"><a href="/login">Accedi</a></p>', true);
    }

    /**
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $acct
     * @param array{state:string,days_left:int,ends_at:string} $plan
     */
    public static function billing(array $cfg, array $acct, array $plan, ?string $notice = null, ?string $error = null): string
    {
        $state = $plan['state'];
        $stripe = Billing::enabled($cfg);
        $pricing = $cfg['pricing'] ?? [];
        $fmt = static fn(string $d): string => $d === '' ? '' : date('d/m/Y', (int)strtotime($d));

        if ($state === 'suspended') {
            $status = '<p class="err">Questo account è stato sospeso. Contattaci per maggiori informazioni: i tuoi dati sono al sicuro.</p>';
        } elseif ($state === 'active') {
            $status = '<p class="ok">Abbonamento attivo' . ($plan['ends_at'] !== '' ? ' · prossimo rinnovo ' . self::h($fmt($plan['ends_at'])) : '') . '.</p>';
        } elseif ($state === 'trial') {
            $status = '<p class="ok">Prova gratuita: ' . $plan['days_left'] . ($plan['days_left'] === 1 ? ' giorno rimasto' : ' giorni rimasti') . ' (fino al ' . self::h($fmt($plan['ends_at'])) . ').</p>';
        } else {
            $status = '<p class="err">La prova gratuita è terminata. Attiva un piano per continuare a usare l\'agenda: i tuoi dati sono al sicuro.</p>';
        }

        $buttons = '';
        if ($state === 'suspended') {
            $buttons = '';
        } elseif ($stripe && $state !== 'active') {
            foreach (['monthly' => ['mensile', 'Mensile'], 'yearly' => ['annuale', 'Annuale']] as $key => [$plan_, $label]) {
                if (empty($cfg['stripe']['price_' . $key])) {
                    continue;
                }
                $price = (string)($pricing[$key . '_label'] ?? '');
                $buttons .= '<form method="post" action="/abbonamento/acquista" style="margin:10px 0"><input type="hidden" name="piano" value="' . $plan_ . '"><button class="btn" style="width:100%">'
                    . 'Scegli il piano ' . strtolower($label) . ($price !== '' ? ' · ' . self::h($price) : '') . '</button></form>';
            }
        } elseif ($stripe && !empty($acct['stripe_customer_id'])) {
            $buttons = '<form method="post" action="/abbonamento/gestisci"><button class="btn alt" style="width:100%">Gestisci abbonamento e fatture</button></form>';
        } elseif (!$stripe) {
            $buttons = '<p class="sub">I pagamenti online non sono ancora attivi. Contattaci per attivare l\'abbonamento.</p>';
        }
        $n = $notice ? '<p class="ok">' . self::h($notice) . '</p>' : '';
        $e = $error ? '<p class="err">' . self::h($error) . '</p>' : '';
        $back = in_array($state, ['expired', 'suspended'], true) ? '' : '<p class="alt-link"><a href="/">← Torna all\'agenda</a></p>';
        $body = '<form class="box" onsubmit="return false"><h1>Il tuo abbonamento</h1><p class="sub">' . self::h((string)$acct['business_name']) . '</p>'
            . $status . $n . $e . '</form>' . '<div class="box" style="background:#fff;border:1px solid #e7e5e4;border-radius:14px;padding:22px;margin:0 0 20px">' . $buttons . '</div>'
            . $back . '<p class="alt-link"><a href="/logout">Esci</a></p>';
        return self::layout($cfg, 'Abbonamento · ' . self::name($cfg), $body, true);
    }

    /**
     * Pannello amministratore.
     *
     * @param array<string,mixed> $cfg
     * @param array<int,array<string,mixed>> $rows negozi già filtrati e della pagina corrente
     * @param array{total:int,trial:int,active:int,expired:int,suspended:int,new7:int} $stats
     * @param array{q:string,state:string,page:int,pages:int,shown:int} $f
     */
    public static function admin(array $cfg, array $rows, array $stats, array $f, string $csrf, int $selfId, ?string $notice = null, ?string $error = null): string
    {
        $h = [self::class, 'h'];
        $fmt = static fn(string $d, string $format = 'd/m/Y'): string => $d === '' ? '—' : date($format, (int)strtotime($d));
        $labels = ['trial' => 'In prova', 'active' => 'Attivo', 'expired' => 'Scaduto', 'suspended' => 'Sospeso'];

        $cards = '';
        foreach ([['total', 'Negozi registrati'], ['trial', 'In prova'], ['active', 'Abbonati'], ['expired', 'Prova scaduta'], ['suspended', 'Sospesi'], ['new7', 'Nuovi (7 giorni)']] as [$k, $label]) {
            $cards .= '<div class="stat"><b>' . $stats[$k] . '</b><span>' . $label . '</span></div>';
        }

        $opts = '<option value="">Tutti gli stati</option>';
        foreach ($labels as $k => $label) {
            $opts .= '<option value="' . $k . '"' . ($f['state'] === $k ? ' selected' : '') . '>' . $label . '</option>';
        }

        $body = '';
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $state = $r['plan']['state'];
            $when = $state === 'trial' ? 'fino al ' . $fmt($r['plan']['ends_at']) . ' (' . $r['plan']['days_left'] . ' g)'
                : ($state === 'active' ? ($r['plan']['ends_at'] !== '' ? 'rinnovo ' . $fmt($r['plan']['ends_at']) : 'senza scadenza')
                : ($state === 'expired' ? 'dal ' . $fmt($r['plan']['ends_at']) : ''));
            $hidden = '<input type="hidden" name="csrf" value="' . self::h($csrf) . '"><input type="hidden" name="id" value="' . $id . '">';
            $form = static fn(string $action, string $inner, string $extra = ''): string
                => '<form method="post" action="/admin" ' . $extra . '>' . $hidden . '<input type="hidden" name="azione" value="' . $action . '">' . $inner . '</form>';
            $menu = $form('extend', '<input type="hidden" name="days" value="7"><button class="mini">+7 giorni</button>')
                . $form('extend', '<input type="hidden" name="days" value="30"><button class="mini">+30 giorni</button>')
                . $form('activate', '<input type="date" name="until" required><button class="mini">Attiva fino al</button>')
                . $form('suspend', '<button class="mini">' . ($r['suspended'] ? 'Riattiva account' : 'Sospendi account') . '</button>')
                . $form('reset', '<button class="mini">Nuova password</button>', 'onsubmit="return confirm(\'Generare una nuova password per questo negozio?\')"');
            if ($id !== $selfId) {
                $menu .= $form('delete', '<button class="mini danger">Elimina account e dati</button>',
                    'onsubmit="return confirm(\'Eliminare DEFINITIVAMENTE ' . self::h(addslashes((string)$r['business_name'])) . ' e tutti i suoi dati?\')"');
            }
            $body .= '<tr><td><b>' . self::h((string)$r['business_name']) . '</b><br><small>' . self::h((string)$r['email']) . '</small></td>'
                . '<td>' . self::h((string)$r['category_label']) . '</td>'
                . '<td><span class="badge b-' . $state . '">' . $labels[$state] . '</span><br><small>' . self::h($when) . '</small></td>'
                . '<td>' . $fmt((string)$r['created_at']) . '</td>'
                . '<td>' . $fmt((string)$r['last_login_at'], 'd/m/Y H:i') . '</td>'
                . '<td>' . $r['clients'] . ' clienti<br>' . $r['appointments'] . ' app.</td>'
                . '<td><details class="act"><summary>Azioni</summary><div class="menu">' . $menu . '</div></details></td></tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="7" style="text-align:center;color:#78716c;padding:28px">Nessun negozio trovato.</td></tr>';
        }

        $pager = '';
        if ($f['pages'] > 1) {
            $base = '/admin?q=' . urlencode($f['q']) . '&stato=' . urlencode($f['state']) . '&pagina=';
            $pager = '<div class="pager">'
                . ($f['page'] > 1 ? '<a href="' . self::h($base . ($f['page'] - 1)) . '">← Precedente</a>' : '')
                . '<span>Pagina ' . $f['page'] . ' di ' . $f['pages'] . '</span>'
                . ($f['page'] < $f['pages'] ? '<a href="' . self::h($base . ($f['page'] + 1)) . '">Successiva →</a>' : '')
                . '</div>';
        }
        $n = $notice ? '<p class="ok">' . $notice . '</p>' : ''; // già escapato da chi lo costruisce
        $e = $error ? '<p class="err">' . self::h($error) . '</p>' : '';
        $q = self::h($f['q']);

        $html = <<<HTML
<h2 style="text-align:left;margin:10px 0 14px">Amministrazione</h2>
$n$e
<div class="stats">$cards</div>
<form class="filters" method="get" action="/admin">
  <input name="q" value="$q" placeholder="Cerca per nome o email">
  <select name="stato">$opts</select>
  <button class="btn" type="submit">Cerca</button>
</form>
<div class="tbl"><table>
  <thead><tr><th>Negozio</th><th>Attività</th><th>Piano</th><th>Iscritto il</th><th>Ultimo accesso</th><th>Utilizzo</th><th></th></tr></thead>
  <tbody>$body</tbody>
</table></div>
$pager
HTML;
        return self::layout($cfg, 'Amministrazione · ' . self::name($cfg), $html, false, true,
            '<a href="/">← Torna all\'agenda</a><a href="/logout">Esci</a>');
    }
}
