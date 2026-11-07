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
    private static function layout(array $cfg, string $title, string $body, bool $narrow = false): string
    {
        $name = self::h(self::name($cfg));
        $t = self::h($title);
        $wrap = $narrow ? 'wrap narrow' : 'wrap';
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
<style>
  *{box-sizing:border-box}
  body{margin:0;font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif;color:#1c1917;background:#faf8f7}
  a{color:#be185d}
  header.top{display:flex;align-items:center;justify-content:space-between;max-width:1040px;margin:0 auto;padding:18px 20px}
  header.top .brand{font-weight:700;font-size:18px;color:#1c1917;text-decoration:none}
  header.top nav a{margin-left:16px;text-decoration:none;font-weight:600;font-size:15px}
  .wrap{max-width:1040px;margin:0 auto;padding:0 20px}
  .narrow{max-width:440px}
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
  <nav><a href="/login">Accedi</a><a href="/registrati">Prova gratis</a></nav>
</header>
<div class="$wrap">
$body
</div>
<footer>© {$name}$links</footer>
</body>
</html>
HTML;
    }

    /** @param array<string,mixed> $cfg */
    public static function landing(array $cfg): string
    {
        $days = (int)($cfg['trial_days'] ?? 30);
        $chips = '';
        foreach (Categories::options() as $label) {
            $chips .= '<span class="chip">' . self::h($label) . '</span>';
        }
        $pricing = $cfg['pricing'] ?? [];
        $monthly = (string)($pricing['monthly_label'] ?? '');
        $yearly = (string)($pricing['yearly_label'] ?? '');
        $plans = '';
        if ($monthly !== '') {
            $plans .= '<div class="card"><h3>Mensile</h3><div class="price">' . self::h($monthly) . '</div><ul><li>Tutte le funzioni</li><li>Disdici quando vuoi</li></ul></div>';
        }
        if ($yearly !== '') {
            $plans .= '<div class="card"><h3>Annuale</h3><div class="price">' . self::h($yearly) . '</div><ul><li>Tutte le funzioni</li><li>Il prezzo migliore</li></ul></div>';
        }
        $pricingBlock = $plans === '' ? '' : "<h2>Prezzi semplici</h2><p class=\"sub\">Prova gratis per $days giorni, poi scegli il piano.</p><div class=\"plans\">$plans</div>";

        $body = <<<HTML
<section class="hero">
  <h1>L'agenda degli appuntamenti per il tuo negozio</h1>
  <p>Prenotazioni, clienti, collaboratori e incassi in un unico posto. Funziona da telefono, tablet e computer. Provalo gratis per $days giorni.</p>
  <a class="btn" href="/registrati">Inizia la prova gratuita</a>
  <p style="font-size:14px;margin:12px 0 0;color:#78716c">Nessuna carta di credito richiesta.</p>
  <div class="chips">$chips</div>
</section>
<div class="grid">
  <div class="card"><h3>Agenda chiara</h3><p>Vedi la giornata di ogni collaboratore, sposta gli appuntamenti e blocca gli orari. Avvisa quando due prenotazioni si sovrappongono.</p></div>
  <div class="card"><h3>Pronto per la tua attività</h3><p>Scegli il tipo di negozio e trovi già servizi e nomi adatti. Poi cambi tutto come vuoi.</p></div>
  <div class="card"><h3>Clienti e storico</h3><p>Scheda cliente con tutti gli appuntamenti passati e le note, per ricordare ogni dettaglio.</p></div>
  <div class="card"><h3>Incassi e magazzino</h3><p>Totale dei servizi completati e prodotti in magazzino con avviso di scorta bassa.</p></div>
</div>
$pricingBlock
<div class="cta"><a class="btn" href="/registrati">Crea il tuo account gratis</a></div>
HTML;
        return self::layout($cfg, self::name($cfg) . ' · Agenda appuntamenti per il tuo negozio', $body);
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

        if ($state === 'active') {
            $status = '<p class="ok">Abbonamento attivo' . ($plan['ends_at'] !== '' ? ' · prossimo rinnovo ' . self::h($fmt($plan['ends_at'])) : '') . '.</p>';
        } elseif ($state === 'trial') {
            $status = '<p class="ok">Prova gratuita: ' . $plan['days_left'] . ($plan['days_left'] === 1 ? ' giorno rimasto' : ' giorni rimasti') . ' (fino al ' . self::h($fmt($plan['ends_at'])) . ').</p>';
        } else {
            $status = '<p class="err">La prova gratuita è terminata. Attiva un piano per continuare a usare l\'agenda: i tuoi dati sono al sicuro.</p>';
        }

        $buttons = '';
        if ($stripe && $state !== 'active') {
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
        $back = $state === 'expired' ? '' : '<p class="alt-link"><a href="/">← Torna all\'agenda</a></p>';
        $body = '<form class="box" onsubmit="return false"><h1>Il tuo abbonamento</h1><p class="sub">' . self::h((string)$acct['business_name']) . '</p>'
            . $status . $n . $e . '</form>' . '<div class="box" style="background:#fff;border:1px solid #e7e5e4;border-radius:14px;padding:22px;margin:0 0 20px">' . $buttons . '</div>'
            . $back . '<p class="alt-link"><a href="/logout">Esci</a></p>';
        return self::layout($cfg, 'Abbonamento · ' . self::name($cfg), $body, true);
    }
}
