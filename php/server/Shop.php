<?php
declare(strict_types=1);

namespace App;

/** Pagina pubblica di un negozio (/nome): profilo e prenotazione online. */
final class Shop
{
    private static function h(?string $s): string
    {
        return Pages::h($s);
    }

    /** La foto di copertina caricata dal negozio (o null). @param array<string,mixed> $acct @return array{mime:string,data:string}|null */
    public static function cover(array $acct): ?array
    {
        $mime = (string)($acct['cover_mime'] ?? '');
        $data = base64_decode((string)($acct['cover_data'] ?? ''), true);
        return $mime === '' || $data === false || $data === '' ? null : ['mime' => $mime, 'data' => $data];
    }

    /** Il logo caricato, come immagine da servire (o null). @param array<string,mixed> $acct @return array{mime:string,data:string}|null */
    public static function logo(array $acct): ?array
    {
        $mime = (string)($acct['logo_mime'] ?? '');
        $data = base64_decode((string)($acct['logo_data'] ?? ''), true);
        if ($mime === '' || $data === false || $data === '') {
            return null;
        }
        return ['mime' => $mime, 'data' => $data];
    }

    /**
     * Controlla e prepara un logo ricevuto come data URL. Accetta PNG, JPEG, WebP, GIF fino a 400 KB.
     *
     * @return array{mime:string,data:string}|null null se non valido
     */
    public static function parseLogo(string $dataUrl): ?array
    {
        return self::parseImage($dataUrl, 400);
    }

    /** Come parseLogo, con un limite di peso a scelta (in KB). @return array{mime:string,data:string}|null */
    public static function parseImage(string $dataUrl, int $maxKb): ?array
    {
        if (!preg_match('#^data:(image/(?:png|jpeg|webp|gif));base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
            return null;
        }
        $raw = base64_decode(preg_replace('/\s+/', '', $m[2]) ?? '', true);
        if ($raw === false || $raw === '' || strlen($raw) > $maxKb * 1024) {
            return null;
        }
        $info = @getimagesizeFromString($raw);
        if ($info === false || ($info['mime'] ?? '') !== $m[1] || $info[0] > 4000 || $info[1] > 4000) {
            return null;
        }
        return ['mime' => $m[1], 'data' => base64_encode($raw)];
    }

    /** @param array<string,mixed> $cfg @param array<string,mixed> $acct */
    public static function notFound(array $cfg): string
    {
        $body = '<form class="box"><h1>Pagina non trovata</h1><p class="sub">L\'indirizzo che hai aperto non esiste o non è più attivo.</p></form>';
        return Pages::layout($cfg, 'Pagina non trovata', $body, true);
    }

    /**
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $acct
     * @param array<int,array<string,mixed>> $services
     * @param array<int,array<string,mixed>> $staff
     */
    public static function page(array $cfg, array $acct, array $services, array $staff, bool $open): string
    {
        $slug = (string)$acct['slug'];
        $name = (string)$acct['business_name'];
        $url = Notify::shopUrl($cfg, $acct);
        $hasLogo = ($acct['logo_mime'] ?? '') !== '';
        $logo = $hasLogo
            ? '<img class="tp-logo" src="/' . self::h($slug) . '/logo?v=' . substr(md5((string)$acct['logo_data']), 0, 8) . '" alt="Logo ' . self::h($name) . '">'
            : '<div class="tp-logo ph">' . self::h(mb_strtoupper(mb_substr($name, 0, 1))) . '</div>';

        $catKey = (string)$acct['category'];
        $cat = Categories::get($catKey);
        $tpl = Themes::forCategory($catKey, $cat);
        $st = $tpl['style'];
        $dark = $st['bg'] === '#1a1716';

        $rows = '';
        $phone = trim((string)($acct['shop_phone'] ?? ''));
        if ($phone !== '') {
            $rows .= '<li><span class="ico">' . Pages::icon('phone') . '</span><a href="tel:' . self::h((string)preg_replace('/[^0-9+]/', '', $phone)) . '">' . self::h($phone) . '</a></li>';
        }
        $wa = Notify::digits((string)($acct['shop_whatsapp'] ?? ''));
        if ($wa !== '') {
            $rows .= '<li><span class="ico">' . Pages::icon('chat') . '</span><a href="https://wa.me/' . self::h($wa) . '" rel="noopener">Scrivici su WhatsApp</a></li>';
        }
        $mail = trim((string)($acct['shop_email'] ?? ''));
        if ($mail !== '') {
            $rows .= '<li><span class="ico">' . Pages::icon('mail') . '</span><a href="mailto:' . self::h($mail) . '">' . self::h($mail) . '</a></li>';
        }
        $addr = trim((string)($acct['shop_address'] ?? ''));
        if ($addr !== '') {
            $rows .= '<li><span class="ico">' . Pages::icon('pin') . '</span><a href="https://www.google.com/maps/search/?api=1&amp;query=' . rawurlencode($addr) . '" rel="noopener" target="_blank">' . self::h($addr) . '</a></li>';
        }
        $hours = trim((string)($acct['shop_hours'] ?? ''));
        if ($hours !== '') {
            $rows .= '<li><span class="ico">' . Pages::icon('clock') . '</span><span>' . nl2br(self::h($hours)) . '</span></li>';
        }
        $desc = trim((string)($acct['shop_description'] ?? ''));
        $contacts = $rows !== '' ? '<div class="card"><h3 class="tp-h3">Dove e quando</h3><ul class="contact-list">' . $rows . '</ul></div>' : '';

        // Immagine dell'arco: foto del negozio, poi immagine della categoria, poi illustrazione.
        $hasCover = ($acct['cover_mime'] ?? '') !== '';
        if ($hasCover) {
            $art = '<img src="/' . self::h($slug) . '/copertina?v=' . substr(md5((string)$acct['cover_data']), 0, 8) . '" alt="' . self::h($name) . '">';
            $ogImage = $url . '/copertina';
        } elseif ($tpl['image'] !== '') {
            $art = '<img src="' . self::h($tpl['image']) . '" alt="" referrerpolicy="no-referrer">';
            $ogImage = $tpl['image'];
        } else {
            $art = Themes::illustration($st, $tpl['icon']);
            $ogImage = $hasLogo ? $url . '/logo' : '';
        }

        $vars = '--bg:' . $st['bg'] . ';--surface:' . $st['surface'] . ';--ink:' . $st['ink'] . ';--muted:' . $st['muted'] . ';--acc:' . $st['acc'] . ';--acc2:' . $st['acc2'] . ';--soft:' . $st['soft']
            . ';--on-acc:' . ($dark ? '#1a1716' : '#ffffff') . ';--line:' . ($dark ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.09)')
            . ';--font-h:' . ($st['font'] === 'serif' ? '"Iowan Old Style","Palatino Linotype",Palatino,"Book Antiqua",Georgia,serif' : 'system-ui,-apple-system,"Segoe UI",Roboto,sans-serif');

        $head = '<meta name="description" content="' . self::h(($desc !== '' ? mb_substr($desc, 0, 150) : 'Prenota online da ' . $name)) . '">' . "\n"
            . '<meta property="og:title" content="' . self::h($name) . '">' . "\n"
            . ($ogImage !== '' ? '<meta property="og:image" content="' . self::h($ogImage) . '">' . "\n" : '')
            . '<meta name="theme-color" content="' . $st['bg'] . '">' . "\n"
            . '<style>:root{' . $vars . '}' . self::css() . self::themeCss() . '</style>' . "\n";

        // Pulsanti principali
        $cta = '<a class="btn" href="#book">Prenota ora</a>';
        if ($wa !== '') {
            $cta .= '<a class="btn alt" href="https://wa.me/' . self::h($wa) . '" rel="noopener">WhatsApp</a>';
        } elseif ($phone !== '') {
            $cta .= '<a class="btn alt" href="tel:' . self::h((string)preg_replace('/[^0-9+]/', '', $phone)) . '">Chiama</a>';
        }
        $count = count($services);
        $facts = '<ul class="tp-facts">'
            . ($count > 0 ? '<li><b>' . $count . '</b> ' . self::h(mb_strtolower((string)$cat['terms']['services'])) . ' prenotabili</li>' : '')
            . '<li><b>24/7</b> prenotazione online</li>'
            . ($addr !== '' ? '<li>' . Pages::icon('pin') . ' ' . self::h(mb_strimwidth($addr, 0, 48, '…')) . '</li>' : '')
            . '</ul>';

        $hero = '<section class="tp-hero">'
            . '<div class="tp-txt">'
            . '<div class="tp-who">' . $logo . '<div><b>' . self::h($name) . '</b><span>' . self::h((string)$cat['label']) . '</span></div></div>'
            . '<h1 class="tp-title">' . Themes::headlineHtml($tpl['headline']) . '</h1>'
            . '<p class="tp-lead">' . nl2br(self::h($desc !== '' ? $desc : $tpl['intro'])) . '</p>'
            . '<div class="tp-cta">' . $cta . '</div>'
            . $facts
            . '</div>'
            . '<div class="tp-art"><div class="tp-arch">' . $art . '</div>'
            . '<div class="tp-badge"><span class="tp-badge-ico">' . Themes::icon($tpl['icon'], 22) . '</span><div><b>Prenota online</b><small>Scegli giorno e orario</small></div></div>'
            . '<div class="tp-ring" aria-hidden="true"></div></div>'
            . '</section>';

        $points = '';
        foreach ($tpl['points'] as $i => [$t, $txt]) {
            $points .= '<div class="tp-point"><span class="tp-num">' . sprintf('%02d', $i + 1) . '</span><h3>' . self::h($t) . '</h3><p>' . self::h($txt) . '</p></div>';
        }
        $points = '<section class="tp-points">' . $points . '</section>';

        // Vetrina dei servizi (i primi 8)
        $menu = '';
        foreach (array_slice($services, 0, 8) as $sv) {
            $price = (float)$sv['price'] > 0 ? number_format((float)$sv['price'], (fmod((float)$sv['price'], 1.0) === 0.0 ? 0 : 2), ',', '.') . ' €' : '';
            $menu .= '<li><div><b>' . self::h((string)$sv['name']) . '</b><small>' . (int)$sv['duration'] . ' min</small></div><span class="tp-dots"></span><em>' . $price . '</em></li>';
        }
        $menu = $menu !== '' ? '<section class="tp-menu"><p class="tp-kicker">' . self::h((string)$cat['terms']['services']) . '</p><h2 class="tp-h2">Scegli il tuo <em>momento</em></h2><ul>' . $menu . '</ul>'
            . ($count > 8 ? '<p class="tp-more">e altri ' . ($count - 8) . ' nella prenotazione qui sotto</p>' : '') . '</section>' : '';

        $head_html = $hero . $points . $menu;

        if (!$open || $services === []) {
            $msg = !$open ? 'Le prenotazioni online non sono attive al momento.' : 'Non ci sono ancora servizi prenotabili online.';
            $book = '<div class="card"><h3>Prenota</h3><p>' . $msg . ($phone !== '' ? ' Contattaci al ' . self::h($phone) . '.' : '') . '</p></div>';
            $script = '';
        } else {
            $book = self::bookingCard($services, $staff, $slug);
            $bar = '<div class="bar" id="bar"><span id="barsum">Prenota online</span><button type="button" class="btn" id="barbtn">Prenota</button></div>';
            $script = $bar . '<script>' . str_replace('__BASE__', '/' . $slug, self::js()) . '</script>';
        }

        $brand = '<a class="brand" href="/' . self::h($slug) . '">' . self::h($name) . '</a>';
        $footer = 'Prenotazioni gestite con <a href="/">' . self::h((string)($cfg['site_name'] ?? 'Appuntamenti4me')) . '</a>';
        $body = '<div class="shop tp">' . $head_html . '<div class="shop-grid" id="prenota"><div>' . $book . '</div><div>' . $contacts . '</div></div></div>' . $script;
        return Pages::layout($cfg, $name, $body, false, false, '', $head, $brand, $footer);
    }

    /** @param array<int,array<string,mixed>> $services @param array<int,array<string,mixed>> $staff */
    private static function bookingCard(array $services, array $staff, string $slug): string
    {
        $svc = '';
        foreach ($services as $s) {
            $price = (float)$s['price'] > 0 ? ' · ' . number_format((float)$s['price'], 2, ',', '.') . ' €' : '';
            $svc .= '<label class="svc"><input type="checkbox" name="svc" value="' . (int)$s['id'] . '" data-n="' . self::h((string)$s['name']) . '" data-d="' . (int)$s['duration'] . '" data-p="' . (float)$s['price'] . '"><span><b>' . self::h((string)$s['name']) . '</b><small>' . (int)$s['duration'] . ' min' . $price . ((int)($s['auto_confirm'] ?? 0) === 1 ? ' · conferma immediata' : '') . '</small></span></label>';
        }
        $staffSel = '';
        if (count($staff) > 1) {
            $opts = '<option value="0">Chiunque sia libero</option>';
            foreach ($staff as $st) {
                $opts .= '<option value="' . (int)$st['id'] . '">' . self::h((string)$st['name']) . '</option>';
            }
            $staffSel = '<label for="staff">Con chi</label><select id="staff">' . $opts . '</select>';
        }
        $html = <<<HTML
<div class="card book" id="book">
  <h3>Prenota online</h3>
  <p class="hint">Scegli cosa ti serve e quando. Alcuni servizi si confermano subito, per gli altri il negozio ti risponde appena possibile.</p>
  <div id="ok" class="ok" hidden></div>
  <div id="flow">
    <div class="step"><b>1 · Servizi</b><div class="svcs">$svc</div>$staffSel</div>
    <div class="step" id="s2" hidden>
      <div class="first" id="first" hidden><span>Primo orario libero: <b id="firstwhen"></b></span><button type="button" class="btn small" id="firstbtn">Scegli questo</button></div>
      <b>2 · Giorno</b>
      <div class="cal-head"><button type="button" id="prev" aria-label="Mese precedente">‹</button><span id="mlabel"></span><button type="button" id="next" aria-label="Mese successivo">›</button></div>
      <div class="cal" id="cal"></div>
      <p class="hint" id="nodays" hidden>Nessun orario libero in questo mese.</p>
      <p class="hint">Non trovi l'orario giusto? <a href="#" id="freeLink">Invia comunque una richiesta</a>: il negozio ti risponderà.</p>
      <div id="free" hidden class="free">
        <label for="fdate">Giorno preferito</label><input type="date" id="fdate">
        <label for="ftime">Orario preferito</label><input type="time" id="ftime" value="10:00">
      </div>
    </div>
    <div class="step" id="s3" hidden><b>3 · Orario</b><div class="slots" id="slots"></div></div>
    <form class="step" id="s4" hidden autocomplete="on">
      <b id="s4t">4 · I tuoi dati</b>
      <p class="chosen" id="chosen"></p>
      <p class="sum" id="sum"></p>
      <label for="name">Nome e cognome</label><input id="name" autocomplete="name" required maxlength="120">
      <p class="hint">Basta un recapito: telefono oppure email.</p>
      <label for="phone">Telefono</label><input id="phone" type="tel" autocomplete="tel" maxlength="30">
      <label for="email">Email</label><input id="email" type="email" autocomplete="email" maxlength="190">
      <label for="notes">Note (facoltative)</label><input id="notes" maxlength="500">
      <label class="consent"><input type="checkbox" id="consent"><span>Ho letto l'<a href="__PRIVACY__" target="_blank" rel="noopener">informativa sulla privacy</a> e acconsento al trattamento dei miei dati per gestire la prenotazione.</span></label>
      <input class="hp" id="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <p class="err" id="err" hidden></p>
      <button class="btn" id="send" type="submit">Invia richiesta</button>
    </form>
  </div>
</div>
HTML;
        return str_replace('__PRIVACY__', '/' . $slug . '/privacy', $html);
    }

    private static function css(): string
    {
        return <<<'CSS'
.shop{max-width:980px;margin:0 auto;padding:6px 0 10px}
.shop-head{display:flex;gap:16px;align-items:center;margin:8px 0 6px}
.shop-head h1{margin:0;font-size:clamp(24px,4vw,34px);letter-spacing:-.01em}
.shop-head .cat{margin:2px 0 0;color:#78716c;font-size:14px}
.logo{width:72px;height:72px;border-radius:16px;object-fit:contain;background:#fff;border:1px solid #e7e5e4;flex:none}
.logo.ph{display:flex;align-items:center;justify-content:center;background:var(--soft);color:var(--acc);font-size:32px;font-weight:700;border-color:var(--acc2)}
.desc{color:#44403c;margin:10px 0 0;max-width:720px}
.shop-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:18px;margin-top:18px;align-items:start}
@media(max-width:820px){.shop-grid{grid-template-columns:1fr}}
.book h3{margin:0 0 4px;font-size:20px}.hint{color:#78716c;font-size:14px;margin:4px 0 10px}
.step{margin:18px 0 0}.step>b{display:block;margin-bottom:8px;font-size:14px;color:#44403c}
.svcs{display:grid;gap:8px}
label.svc{display:flex;gap:10px;align-items:center;border:1px solid #e7e5e4;border-radius:10px;padding:10px 12px;margin:0;font-weight:400;cursor:pointer}
label.svc:has(input:checked){border-color:var(--acc);background:var(--soft)}
label.svc input{margin:0}label.svc span{display:flex;flex-direction:column}label.svc small{color:#78716c;font-size:12px}
.cal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;text-transform:capitalize;font-weight:600}
.cal-head button{border:1px solid #e7e5e4;background:#fff;border-radius:8px;width:36px;height:34px;font-size:20px;cursor:pointer}
.cal-head button:disabled{opacity:.35;cursor:default}
.cal{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center}
.cal .dow{font-size:11px;color:#a8a29e;padding:4px 0;text-transform:uppercase}
.cal button,.cal span.d{height:38px;border-radius:8px;border:0;background:transparent;font-size:14px;color:#d6d3d1}
.cal button.free{background:var(--soft);color:var(--acc);font-weight:700;cursor:pointer;border:1px solid var(--acc2)}
.cal button.sel{background:var(--acc);color:#fff;border-color:var(--acc)}
.slots{display:flex;flex-wrap:wrap;gap:8px}
.slots button{border:1px solid #e7e5e4;background:#fff;border-radius:8px;padding:8px 14px;font-size:15px;cursor:pointer}
.slots button.sel{background:var(--acc);color:#fff;border-color:var(--acc)}
.free{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:2px 12px 12px;margin-top:8px}
.chosen{background:#f5f5f4;border-radius:8px;padding:8px 12px;margin:0 0 4px;font-size:14px}
#s4 .btn{width:100%;margin-top:16px}
a{color:var(--acc)}
.first{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:10px 12px;margin-bottom:14px;font-size:14px}
.btn.small{padding:7px 12px;font-size:13px}
div.box{background:#fff;border:1px solid #e7e5e4;border-radius:14px;padding:26px;margin:24px 0}div.box h1{margin:0 0 4px;font-size:22px}div.box p.sub{margin:0 0 14px;font-size:14px;color:#78716c}div.legal p{line-height:1.5}
.sum{margin:0 0 6px;font-size:14px;color:#44403c}
.bar{display:none}
@media(max-width:820px){.bar{display:flex;position:fixed;left:0;right:0;bottom:0;z-index:20;gap:10px;align-items:center;justify-content:space-between;background:#fff;border-top:1px solid #e7e5e4;padding:10px 14px;box-shadow:0 -4px 14px rgba(0,0,0,.08)}.bar span{font-size:14px;font-weight:600}.bar .btn{white-space:nowrap}.shop{padding-bottom:70px}}
.consent{display:flex;gap:8px;align-items:flex-start;font-size:13px;color:#57534e;margin-top:12px;font-weight:400}.consent input{margin-top:3px;flex:none}
CSS;
    }

    /** Aspetto a template della pagina pubblica (colori dalle variabili dello stile). */
    private static function themeCss(): string
    {
        return <<<'CSS'
html body{background:var(--bg);color:var(--ink)}
html body header.top .brand,html body header.top nav a{color:var(--ink)}
html body footer,html body footer a{color:var(--muted)}
.tp a{color:var(--acc)}
.tp .btn{background:var(--acc);color:var(--on-acc)!important;border-radius:999px;padding:14px 26px;box-shadow:0 10px 24px -12px var(--acc)}
.tp .btn:hover{filter:brightness(1.08);background:var(--acc)}
.tp .btn.alt{background:transparent;color:var(--ink)!important;border:1.5px solid var(--ink);box-shadow:none}
.tp .card{background:var(--surface);border-color:var(--line);color:var(--ink);border-radius:20px}
.tp .card p,.tp .hint,.tp label.svc small,.tp .contact-list small{color:var(--muted)}
.tp .ico{background:var(--soft);color:var(--acc)}
.tp .contact-list li{border-color:var(--line)}
.tp input,.tp select,.tp textarea{background:var(--surface);color:var(--ink);border-color:var(--line)}
.tp label.svc,.tp .slots button,.tp .cal-head button{background:var(--surface);color:var(--ink);border-color:var(--line)}
.tp .slots button.sel{background:var(--acc);color:var(--on-acc);border-color:var(--acc)}
.tp .cal button.free{color:var(--ink)}
.tp .cal button.sel{color:var(--on-acc)}
.tp .chosen{background:var(--soft);color:var(--ink)}
.tp .step>b{color:var(--ink)}
.tp-hero{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:40px;align-items:center;padding:18px 0 36px}
.tp-who{display:flex;gap:12px;align-items:center;margin-bottom:22px}
.tp-who b{display:block;font-size:17px}.tp-who span{display:block;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted)}
.tp-logo{width:52px;height:52px;border-radius:14px;object-fit:contain;background:var(--surface);border:1px solid var(--line);flex:none}
.tp-logo.ph{display:flex;align-items:center;justify-content:center;background:var(--acc);color:var(--on-acc);font:600 24px var(--font-h);border:0}
.tp-title{font-family:var(--font-h);font-weight:500;font-size:clamp(36px,5.4vw,62px);line-height:1.04;letter-spacing:-.02em;margin:0 0 18px;color:var(--ink)}
.tp-title em,.tp-h2 em{font-style:italic;color:var(--acc)}
.tp-lead{font-size:18px;line-height:1.6;color:var(--muted);max-width:520px;margin:0 0 26px}
.tp-cta{display:flex;flex-wrap:wrap;gap:12px}
.tp-facts{list-style:none;display:flex;flex-wrap:wrap;gap:8px 22px;padding:0;margin:26px 0 0;font-size:14px;color:var(--muted)}
.tp-facts li{display:flex;align-items:center;gap:6px}.tp-facts b{color:var(--ink);font-size:16px}.tp-facts svg{width:16px;height:16px}
.tp-art{position:relative;max-width:440px;width:100%;justify-self:center}
.tp-arch{position:relative;z-index:1;aspect-ratio:4/5;border-radius:999px 999px 28px 28px;overflow:hidden;background:var(--soft);box-shadow:0 30px 60px -30px rgba(0,0,0,.35)}
.tp-arch img,.tp-arch svg{display:block;width:100%;height:100%;object-fit:cover}
.tp-ring{position:absolute;z-index:0;right:-22px;top:-22px;width:100%;height:100%;border:1.5px solid var(--acc);border-radius:999px 999px 28px 28px;opacity:.35}
.tp-badge{position:absolute;z-index:2;left:-26px;bottom:42px;display:flex;gap:12px;align-items:center;background:var(--surface);color:var(--ink);border-radius:18px;padding:12px 16px;box-shadow:0 18px 40px -18px rgba(0,0,0,.35)}
.tp-badge b{display:block;font-size:14px}.tp-badge small{font-size:12px;color:var(--muted)}
.tp-badge-ico{width:40px;height:40px;border-radius:12px;background:var(--soft);color:var(--acc);display:flex;align-items:center;justify-content:center}
.tp-points{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:10px 0 40px}
.tp-point{background:var(--surface);border:1px solid var(--line);border-radius:20px;padding:22px}
.tp-num{display:inline-block;font:italic 500 30px/1 var(--font-h);color:var(--acc);margin-bottom:12px}
.tp-point h3{margin:0 0 6px;font-size:17px}.tp-point p{margin:0;color:var(--muted);font-size:15px;line-height:1.5}
.tp-kicker{margin:0 0 6px;font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:var(--acc);font-weight:700}
.tp-h2{font-family:var(--font-h);font-weight:500;font-size:clamp(28px,3.6vw,40px);margin:0 0 18px;text-align:left;letter-spacing:-.01em}
.tp-menu{background:var(--surface);border:1px solid var(--line);border-radius:24px;padding:28px;margin:0 0 30px}
.tp-menu ul{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 34px}
.tp-menu li{display:flex;align-items:flex-end;gap:10px;padding:10px 0;border-bottom:1px dashed var(--line)}
.tp-menu li b{display:block;font-size:16px}.tp-menu li small{color:var(--muted);font-size:13px}
.tp-dots{flex:1}.tp-menu li em{font-style:normal;font-weight:700;color:var(--acc);white-space:nowrap}
.tp-more{margin:14px 0 0;color:var(--muted);font-size:14px}
.tp-h3{margin:0 0 6px;font-size:18px;font-family:var(--font-h);font-weight:600}
.tp .book h3{font-family:var(--font-h);font-weight:600;font-size:24px}
.shop-grid{scroll-margin-top:12px}#book{scroll-margin-top:12px}
html body .bar{background:var(--surface);color:var(--ink);border-color:var(--line)}html body .bar .btn{background:var(--acc);color:var(--on-acc)!important;border-radius:999px}
@media(max-width:820px){
  .tp-hero{grid-template-columns:1fr;gap:34px;padding-top:4px}
  .tp-art{max-width:250px}
  .tp-title{font-size:clamp(32px,9vw,44px)}
  .tp-badge{left:-8px;bottom:22px}
  .tp-ring{right:-12px;top:-12px}
  .tp-points{grid-template-columns:1fr}
  .tp-menu ul{grid-template-columns:1fr}
  .tp-menu{padding:20px}
}
CSS;
    }

    private static function js(): string
    {
        return <<<'JS'
(function(){
var BASE='__BASE__',MONTHS=['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
function $(i){return document.getElementById(i)}
var st={month:null,date:null,time:null,free:false,staff:0,days:[]};
var now=new Date();st.month=new Date(now.getFullYear(),now.getMonth(),1);var thisMonth=new Date(now.getFullYear(),now.getMonth(),1);
function pad(n){return (n<10?'0':'')+n}
function ymd(d){return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())}
function services(){return Array.prototype.map.call(document.querySelectorAll('input[name=svc]:checked'),function(i){return i.value}).join(',')}
function staffId(){return $('staff')?$('staff').value:'0'}
function svcIds(){var b=$('svcs');if(!b)return null;var r=[];Array.prototype.forEach.call(b.querySelectorAll('input:checked'),function(x){r.push(+x.value)});return r}
function sq(){var r=svcIds();return r&&r.length?'&servizi='+r.join(','):''}
function get(path,params){return fetch(BASE+path+'?'+params+sq(),{headers:{'Accept':'application/json'}}).then(function(r){return r.json()})}
function itDate(s){var p=s.split('-');var d=new Date(+p[0],+p[1]-1,+p[2]);return ['domenica','lunedì','martedì','mercoledì','giovedì','venerdì','sabato'][d.getDay()]+' '+(+p[2])+' '+MONTHS[+p[1]-1]}
function show(id,on){$(id).hidden=!on}
function reset3(){st.time=null;show('s3',false);show('s4',false)}
function loadMonth(){
  var s=services();bar();if(!s){show('s2',false);reset3();return}
  show('s2',true);
  $('mlabel').textContent=MONTHS[st.month.getMonth()]+' '+st.month.getFullYear();
  $('prev').disabled=st.month<=thisMonth;
  var m=st.month.getFullYear()+'-'+pad(st.month.getMonth()+1);
  get('/giorni','mese='+m+'&servizi='+s+'&staff='+staffId()).then(function(d){st.days=d.days||[];drawCal();show('nodays',st.days.length===0)}).catch(function(){st.days=[];drawCal()});
}
function drawCal(){
  var c=$('cal');c.textContent='';
  ['L','M','M','G','V','S','D'].forEach(function(x){var e=document.createElement('div');e.className='dow';e.textContent=x;c.appendChild(e)});
  var first=new Date(st.month.getFullYear(),st.month.getMonth(),1),lead=(first.getDay()+6)%7,n=new Date(st.month.getFullYear(),st.month.getMonth()+1,0).getDate();
  for(var i=0;i<lead;i++){c.appendChild(document.createElement('span'))}
  for(var d=1;d<=n;d++){
    var key=st.month.getFullYear()+'-'+pad(st.month.getMonth()+1)+'-'+pad(d);
    if(st.days.indexOf(key)>=0){var b=document.createElement('button');b.type='button';b.textContent=d;b.className='free'+(st.date===key&&!st.free?' sel':'');b.setAttribute('data-d',key);b.onclick=(function(k){return function(){pickDate(k)}})(key);c.appendChild(b)}
    else{var s=document.createElement('span');s.className='d';s.textContent=d;c.appendChild(s)}
  }
}
function pickDate(k,pre){
  st.free=false;show('free',false);st.date=k;st.time=null;drawCal();show('s4',false);
  get('/orari','data='+k+'&servizi='+services()+'&staff='+staffId()).then(function(d){
    var box=$('slots');box.textContent='';var list=d.slots||[];
    if(!list.length){box.textContent='Nessun orario libero in questo giorno.'}
    list.forEach(function(t){var b=document.createElement('button');b.type='button';b.textContent=t;b.onclick=function(){st.time=t;Array.prototype.forEach.call(box.children,function(x){x.className=''});b.className='sel';showForm()};box.appendChild(b);if(pre===t){b.onclick()}});
    show('s3',true);bar();
  });
}
function summary(){var n=[],dur=0,pr=0;Array.prototype.forEach.call(document.querySelectorAll('input[name=svc]:checked'),function(i){n.push(i.getAttribute('data-n'));dur+=+i.getAttribute('data-d');pr+=+i.getAttribute('data-p')});return{names:n,dur:dur,price:pr}}
function money(v){return v>0?(' · '+v.toFixed(2).replace('.',',')+' €'):''}
function sumText(){var x=summary();return x.names.length?x.names.join(', ')+' · '+x.dur+' min'+money(x.price):''}
function bar(){
  var b=$('bar');if(!b)return;var t=sumText();$('barsum').textContent=t||'Prenota online';
  $('barbtn').textContent=t?'Continua':'Prenota';b.style.display=(!$('s4').hidden||!$('ok').hidden)?'none':'';
}
function bartarget(){var ids=['s4','s3','s2'];for(var i=0;i<ids.length;i++){if(!$(ids[i]).hidden)return $(ids[i])}return $('book')}
$('barbtn').onclick=function(){bartarget().scrollIntoView({behavior:'smooth',block:'start'})};
function loadFirst(){
  var s=services();show('first',false);if(!s)return;
  get('/primo','servizi='+s+'&staff='+staffId()).then(function(d){
    if(!d.date||services()!==s)return;
    $('firstwhen').textContent=itDate(d.date)+' alle '+d.time;
    $('firstbtn').onclick=function(){var p=d.date.split('-');st.month=new Date(+p[0],+p[1]-1,1);st.free=false;show('free',false);loadMonth();pickDate(d.date,d.time)};
    show('first',true);
  }).catch(function(){});
}
function showForm(){
  $('sum').textContent=sumText();
  $('chosen').textContent=(st.free?'Richiesta per ':'')+itDate(st.date)+' alle '+st.time+(st.free?' (da confermare)':'');
  $('s4t').textContent=(st.free?'3':'4')+' · I tuoi dati';
  $('send').textContent=st.free?'Invia la richiesta':'Invia richiesta';
  show('s4',true);bar();
}
function freeChanged(){
  var d=$('fdate').value,t=$('ftime').value;
  if(d&&t){st.free=true;st.date=d;st.time=t;show('s3',false);showForm()}else{show('s4',false)}
}
$('freeLink').onclick=function(e){e.preventDefault();st.free=true;show('free',true);show('s3',false);show('s4',false);
  var min=ymd(new Date());$('fdate').min=min;if(!$('fdate').value){$('fdate').value=''}};
$('fdate').onchange=freeChanged;$('ftime').onchange=freeChanged;
$('prev').onclick=function(){st.month=new Date(st.month.getFullYear(),st.month.getMonth()-1,1);loadMonth()};
$('next').onclick=function(){st.month=new Date(st.month.getFullYear(),st.month.getMonth()+1,1);loadMonth()};
Array.prototype.forEach.call(document.querySelectorAll('input[name=svc]'),function(i){i.onchange=function(){st.date=null;reset3();loadMonth();loadFirst()}});
if($('staff')){$('staff').onchange=function(){st.date=null;reset3();loadMonth();loadFirst()}}
$('s4').onsubmit=function(e){
  e.preventDefault();var err=$('err');err.hidden=true;$('send').disabled=true;
  fetch(BASE+'/prenota',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
    name:$('name').value,phone:$('phone').value,email:$('email').value,notes:$('notes').value,website:$('website').value,consent:$('consent').checked,
    date:st.date,time:st.time,free:st.free,staff_id:+staffId(),service_ids:services().split(',').map(Number)})})
  .then(function(r){return r.json().then(function(j){return{ok:r.ok,j:j}})})
  .then(function(x){
    $('send').disabled=false;
    if(!x.ok){err.textContent=x.j.error||'Invio non riuscito, riprova.';err.hidden=false;if(!st.free){loadMonth();reset3()}return}
    show('flow',false);var ok=$('ok');ok.textContent=x.j.message||'Richiesta inviata!';ok.hidden=false;bar();ok.scrollIntoView({behavior:'smooth',block:'center'});
  }).catch(function(){$('send').disabled=false;err.textContent='Connessione non riuscita, riprova.';err.hidden=false});
};
bar();
})();
JS;
    }

    /** Pagina di annullamento dal link ricevuto per email. @param array<string,mixed> $cfg @param array<string,mixed>|null $apt */
    public static function cancelPage(array $cfg, ?array $apt, string $token, ?string $notice = null): string
    {
        if ($apt === null) {
            return Pages::layout($cfg, 'Link non valido', '<form class="box"><h1>Link non valido</h1><p class="sub">Questo link non è corretto o è scaduto.</p></form>', true);
        }
        $when = Notify::dateIt((string)$apt['scheduled_date']) . ' alle ' . $apt['start_time'];
        $active = in_array($apt['status'], ['requested', 'booked', 'confirmed'], true) && $apt['scheduled_date'] >= date('Y-m-d');
        $body = '<form class="box" method="post" action="/annulla/' . self::h($token) . '"><h1>Annulla prenotazione</h1>'
            . ($notice !== null ? '<p class="ok">' . self::h($notice) . '</p>' : '')
            . '<p class="sub">' . self::h($when) . '</p>'
            . ($active ? '<p>Vuoi annullare questa prenotazione?</p><button class="btn" type="submit">Sì, annulla</button><p class="sub" style="margin-top:12px">Preferisci cambiare giorno? <a href="/sposta/' . self::h($token) . '">Sposta la prenotazione</a></p>' : '<p>Questa prenotazione non è più attiva.</p>')
            . '</form>';
        return Pages::layout($cfg, 'Annulla prenotazione', $body, true);
    }

    /** Informativa privacy generata per il negozio (modello da verificare, non è una consulenza legale). @param array<string,mixed> $cfg @param array<string,mixed> $acct */
    public static function privacyPage(array $cfg, array $acct): string
    {
        $name = self::h((string)$acct['business_name']);
        $contact = trim((string)($acct['shop_email'] ?? '')) !== '' ? (string)$acct['shop_email'] : (string)$acct['email'];
        $site = self::h((string)($cfg['site_name'] ?? 'Appuntamenti4me'));
        $body = '<div class="box legal"><h1>Informativa sulla privacy</h1>'
            . '<p class="sub">Prenotazioni online di ' . $name . '</p>'
            . '<p><b>Titolare del trattamento</b>: ' . $name . ' (contatto: ' . self::h($contact) . (trim((string)($acct['shop_phone'] ?? '')) !== '' ? ', tel. ' . self::h((string)$acct['shop_phone']) : '') . ').</p>'
            . '<p><b>Dati raccolti</b>: nome, telefono e/o email, note facoltative, servizio, data e ora richiesti. Per prevenire abusi viene conservata anche una sigla anonima (hash) dell\'indirizzo di rete usato per inviare la richiesta.</p>'
            . '<p><b>Finalità e base giuridica</b>: gestire la tua prenotazione, contattarti per confermarla, modificarla o annullarla, inviarti promemoria (base: esecuzione della richiesta e tuo consenso).</p>'
            . '<p><b>Conservazione</b>: per il tempo necessario a gestire il rapporto e gli obblighi di legge; puoi chiedere la cancellazione in qualsiasi momento.</p>'
            . '<p><b>Destinatari</b>: i dati sono visibili al titolare e al suo personale. La piattaforma ' . $site . ' li ospita e li tratta per conto del titolare come fornitore del servizio. Non vengono ceduti a terzi per marketing.</p>'
            . '<p><b>I tuoi diritti</b>: accesso, rettifica, cancellazione, limitazione, opposizione e portabilità, scrivendo al contatto indicato sopra. Puoi anche proporre reclamo al Garante per la protezione dei dati personali.</p>'
            . '<p class="sub">Testo generato automaticamente come modello: il titolare è tenuto a verificarlo e adattarlo alla propria attività.</p>'
            . '<p><a href="/' . self::h((string)$acct['slug']) . '">← Torna alla prenotazione</a></p></div>';
        return Pages::layout($cfg, 'Privacy - ' . (string)$acct['business_name'], $body, true, false, '', '<style>' . self::css() . '</style>');
    }

    /**
     * Pagina per approvare o rifiutare una richiesta dal link nell'email del titolare.
     *
     * @param array<string,mixed> $cfg
     * @param array<string,mixed>|null $apt
     */
    public static function managePage(array $cfg, ?array $apt, string $token, string $sig, string $services = '', ?string $notice = null, bool $error = false): string
    {
        if ($apt === null) {
            return Pages::layout($cfg, 'Link non valido', '<form class="box"><h1>Link non valido</h1><p class="sub">Questo link non è corretto o è scaduto.</p></form>', true);
        }
        $when = Notify::dateIt((string)$apt['scheduled_date']) . ' alle ' . $apt['start_time'];
        $pending = $apt['status'] === 'requested';
        $states = ['confirmed' => 'Confermata', 'cancelled' => 'Annullata o rifiutata', 'requested' => 'Da approvare'];
        $body = '<form class="box" method="post" action="/gestisci/' . self::h($token) . '/' . self::h($sig) . '"><h1>Richiesta di prenotazione</h1>'
            . ($notice !== null ? '<p class="' . ($error ? 'err' : 'ok') . '">' . self::h($notice) . '</p>' : '')
            . '<p><b>' . self::h((string)($apt['client_name'] ?? '')) . '</b></p>'
            . '<p class="sub">' . self::h($when) . ($services !== '' ? '<br>' . self::h($services) : '') . '</p>'
            . (!empty($apt['notes']) ? '<p>' . self::h((string)$apt['notes']) . '</p>' : '')
            . ($pending
                ? '<button class="btn" type="submit" name="azione" value="approva">Conferma la prenotazione</button><p style="margin-top:10px"><button class="btn" type="submit" name="azione" value="rifiuta" style="background:#78716c">Rifiuta</button></p>'
                : '<p>Stato: ' . self::h($states[(string)$apt['status']] ?? (string)$apt['status']) . '</p>')
            . '<p class="sub"><a href="/appointments">Apri il pannello</a></p></form>';
        return Pages::layout($cfg, 'Richiesta di prenotazione', $body, true);
    }

    /** Pagina per spostare una prenotazione dal link ricevuto per email. @param array<string,mixed> $cfg @param array<string,mixed>|null $apt */
    public static function reschedulePage(array $cfg, ?array $apt, string $token, array $services = [], array $current = []): string
    {
        if ($apt === null) {
            return Pages::layout($cfg, 'Link non valido', '<form class="box"><h1>Link non valido</h1><p class="sub">Questo link non è corretto o è scaduto.</p></form>', true);
        }
        $when = Notify::dateIt((string)$apt['scheduled_date']) . ' alle ' . $apt['start_time'];
        if (!Booking::changeable($apt)) {
            return Pages::layout($cfg, 'Sposta prenotazione', '<form class="box"><h1>Sposta prenotazione</h1><p class="sub">' . self::h($when) . '</p><p>Questa prenotazione non è più modificabile.</p></form>', true);
        }
        $head = '<style>' . self::css() . '</style>';
        $svc = '';
        if (count($services) > 1 || ($services !== [] && !in_array((int)$services[0]['id'], array_map('intval', $current), true))) {
            $svc = '<div class="step"><b>Servizi</b><p class="hint">Puoi cambiare i servizi: gli orari liberi si adeguano alla durata.</p><div id="svcs">';
            foreach ($services as $sv) {
                $on = in_array((int)$sv['id'], array_map('intval', $current), true);
                $svc .= '<label style="display:block;margin:6px 0"><input type="checkbox" value="' . (int)$sv['id'] . '"' . ($on ? ' checked' : '') . '> ' . self::h((string)$sv['name'])
                    . ' <span class="sub">(' . (int)$sv['duration'] . ' min' . ((float)$sv['price'] > 0 ? ', ' . number_format((float)$sv['price'], 2, ',', '.') . ' €' : '') . ')</span></label>';
            }
            $svc .= '</div></div>';
        }
        $body = '<div class="box wide"><h1>Sposta la prenotazione</h1><p class="sub">Ora è: ' . self::h($when) . '</p>'
            . '<div id="ok" class="ok" hidden></div><div id="flow">' . $svc
            . '<div class="first" id="first" hidden><span>Primo orario libero: <b id="firstwhen"></b></span><button type="button" class="btn small" id="firstbtn">Scegli questo</button></div>'
            . '<div class="cal-head"><button type="button" id="prev" aria-label="Mese precedente">‹</button><span id="mlabel"></span><button type="button" id="next" aria-label="Mese successivo">›</button></div>'
            . '<div class="cal" id="cal"></div><p class="hint" id="nodays" hidden>Nessun orario libero in questo mese.</p>'
            . '<div class="step" id="s3" hidden><b>Orario</b><div class="slots" id="slots"></div></div>'
            . '<p class="chosen" id="chosen" hidden></p><p class="err" id="err" hidden></p>'
            . '<button class="btn" id="send" type="button" hidden>Conferma lo spostamento</button></div>'
            . '<p class="sub" style="margin-top:14px"><a href="/annulla/' . self::h($token) . '">Annulla invece la prenotazione</a></p></div>'
            . '<script>' . str_replace('__BASE__', '/sposta/' . $token, self::moveJs()) . '</script>';
        return Pages::layout($cfg, 'Sposta prenotazione', $body, true, false, '', $head);
    }

    private static function moveJs(): string
    {
        return <<<'JS'
(function(){
var BASE='__BASE__',MONTHS=['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
function $(i){return document.getElementById(i)}
function pad(n){return (n<10?'0':'')+n}
var now=new Date(),thisMonth=new Date(now.getFullYear(),now.getMonth(),1),st={month:thisMonth,date:null,time:null,days:[]};
function itDate(s){var p=s.split('-');var d=new Date(+p[0],+p[1]-1,+p[2]);return ['domenica','lunedì','martedì','mercoledì','giovedì','venerdì','sabato'][d.getDay()]+' '+(+p[2])+' '+MONTHS[+p[1]-1]}
function svcIds(){var b=$('svcs');if(!b)return null;var r=[];Array.prototype.forEach.call(b.querySelectorAll('input:checked'),function(x){r.push(+x.value)});return r}
function sq(){var r=svcIds();return r&&r.length?'&servizi='+r.join(','):''}
function get(path,params){return fetch(BASE+path+'?'+params+sq(),{headers:{'Accept':'application/json'}}).then(function(r){return r.json()})}
function loadMonth(){
  $('mlabel').textContent=MONTHS[st.month.getMonth()]+' '+st.month.getFullYear();$('prev').disabled=st.month<=thisMonth;
  get('/giorni','mese='+st.month.getFullYear()+'-'+pad(st.month.getMonth()+1)).then(function(d){st.days=d.days||[];draw();$('nodays').hidden=st.days.length>0}).catch(function(){st.days=[];draw()});
}
function draw(){
  var c=$('cal');c.textContent='';
  ['L','M','M','G','V','S','D'].forEach(function(x){var e=document.createElement('div');e.className='dow';e.textContent=x;c.appendChild(e)});
  var first=new Date(st.month.getFullYear(),st.month.getMonth(),1),lead=(first.getDay()+6)%7,n=new Date(st.month.getFullYear(),st.month.getMonth()+1,0).getDate();
  for(var i=0;i<lead;i++){c.appendChild(document.createElement('span'))}
  for(var d=1;d<=n;d++){
    var key=st.month.getFullYear()+'-'+pad(st.month.getMonth()+1)+'-'+pad(d);
    if(st.days.indexOf(key)>=0){var b=document.createElement('button');b.type='button';b.textContent=d;b.className='free'+(st.date===key?' sel':'');b.onclick=(function(k){return function(){pick(k)}})(key);c.appendChild(b)}
    else{var s=document.createElement('span');s.className='d';s.textContent=d;c.appendChild(s)}
  }
}
function pick(k,pre){
  st.date=k;st.time=null;draw();$('send').hidden=true;$('chosen').hidden=true;
  get('/orari','data='+k).then(function(d){
    var box=$('slots');box.textContent='';var list=d.slots||[];
    if(!list.length){box.textContent='Nessun orario libero in questo giorno.'}
    list.forEach(function(t){var b=document.createElement('button');b.type='button';b.textContent=t;b.onclick=function(){st.time=t;Array.prototype.forEach.call(box.children,function(x){x.className=''});b.className='sel';$('chosen').textContent='Nuovo orario: '+itDate(st.date)+' alle '+t;$('chosen').hidden=false;$('send').hidden=false};box.appendChild(b);if(pre===t){b.onclick()}});
    $('s3').hidden=false;
  });
}
$('prev').onclick=function(){st.month=new Date(st.month.getFullYear(),st.month.getMonth()-1,1);loadMonth()};
$('next').onclick=function(){st.month=new Date(st.month.getFullYear(),st.month.getMonth()+1,1);loadMonth()};
function loadFirst(){$('first').hidden=true;get('/primo','').then(function(d){if(!d.date)return;$('firstwhen').textContent=itDate(d.date)+' alle '+d.time;$('first').hidden=false;
  $('firstbtn').onclick=function(){var p=d.date.split('-');st.month=new Date(+p[0],+p[1]-1,1);loadMonth();pick(d.date,d.time)}}).catch(function(){})}
if($('svcs')){$('svcs').onchange=function(){var r=svcIds();$('err').hidden=true;if(!r.length){$('err').textContent='Scegli almeno un servizio.';$('err').hidden=false;return}st.date=null;st.time=null;$('s3').hidden=true;$('send').hidden=true;$('chosen').hidden=true;loadMonth();loadFirst()}}
loadFirst();
$('send').onclick=function(){
  var err=$('err');err.hidden=true;$('send').disabled=true;
  fetch(BASE,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({date:st.date,time:st.time,service_ids:svcIds()})})
  .then(function(r){return r.json().then(function(j){return{ok:r.ok,j:j}})})
  .then(function(x){$('send').disabled=false;
    if(!x.ok){err.textContent=x.j.error||'Spostamento non riuscito.';err.hidden=false;loadMonth();$('s3').hidden=true;$('send').hidden=true;return}
    $('flow').hidden=true;var ok=$('ok');ok.textContent=x.j.message;ok.hidden=false;
  }).catch(function(){$('send').disabled=false;err.textContent='Connessione non riuscita, riprova.';err.hidden=false});
};
loadMonth();
})();
JS;
    }
}
