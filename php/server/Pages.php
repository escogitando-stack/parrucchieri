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
    public static function layout(array $cfg, string $title, string $body, bool $narrow = false, bool $wide = false, ?string $nav = null, string $head = '', ?string $brand = null, ?string $footer = null): string
    {
        $name = self::h(self::name($cfg));
        $brandHtml = $brand ?? '<a class="brand" href="/">📅 ' . $name . '</a>';
        $t = self::h($title);
        $wrap = $narrow ? 'wrap narrow' : ($wide ? 'wrap wide' : 'wrap');
        $nav ??= '<a href="/login">Accedi</a><a href="/registrati">Prova gratis</a>';
        $links = ' · <a href="/contatti">Contatti</a>';
        foreach (['terms_url' => 'Termini', 'privacy_url' => 'Privacy'] as $k => $label) {
            if (!empty($cfg[$k])) {
                $links .= ' · <a href="' . self::h((string)$cfg[$k]) . '">' . $label . '</a>';
            }
        }
        $footerHtml = $footer ?? ('© ' . $name . $links);
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
  .b-trial{background:#fef3c7;color:#92400e}.b-active{background:#dcfce7;color:#166534}.b-expired{background:#fee2e2;color:#991b1b}.b-suspended{background:#e7e5e4;color:#44403c}.b-pending{background:#dbeafe;color:#1e40af}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin:8px 0 20px}
  .stat{background:#fff;border:1px solid #e7e5e4;border-radius:12px;padding:14px}.stat b{display:block;font-size:26px}.stat span{font-size:13px;color:#78716c}
  .filters{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}.filters input,.filters select{width:auto;height:40px;min-width:180px}.filters .btn{padding:9px 18px}
  details.act summary{cursor:pointer;color:#be185d;font-weight:600;font-size:13px}
  details.act .menu{margin-top:8px;display:flex;flex-direction:column;gap:6px;min-width:210px}
  details.act form{display:flex;gap:6px;align-items:center;margin:0}
  details.act input[type=date],details.act input[type=number]{height:32px;width:auto;padding:0 8px;font-size:13px}
  .mini{border:1px solid #e7c5d4;background:#fff;color:#be185d;border-radius:8px;padding:5px 10px;font-size:13px;font-weight:600;cursor:pointer}
  .mini.danger{border-color:#fca5a5;color:#b91c1c}
  .planrow{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 10px}.planrow input:not([type=checkbox]){width:auto;height:36px;font-size:14px;flex:1 1 130px}.planrow input[name=duration_days]{flex:0 0 90px}.planrow input[name=price]{flex:0 0 100px}
  .b-demo{background:#ede9fe;color:#5b21b6}
  .grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px 14px}
  .grid2 label,.catform label{display:block;font-size:13px;font-weight:600;color:#57534e;margin:0}
  .grid2 input,.grid2 select,.grid2 textarea,.catform input,.catform textarea{margin-top:4px;font-size:14px}
  .catform textarea{width:100%;font:13px/1.5 ui-monospace,Menlo,Consolas,monospace;min-height:170px}
  details.cat{border:1px solid #e7e5e4;border-radius:10px;padding:8px 12px;margin:0 0 8px;background:#fff}
  details.cat summary{cursor:pointer;font-weight:600}
  .muted{color:#78716c;font-size:13px;font-weight:400}
  .pager{display:flex;gap:10px;justify-content:center;margin:16px 0;font-size:14px}
  code.pw{font-size:18px;background:#fff;border:1px solid #e7e5e4;border-radius:8px;padding:4px 10px}
  .btn{display:inline-block;background:#be185d;color:#fff!important;border:0;border-radius:10px;padding:13px 22px;font-size:16px;font-weight:600;text-decoration:none;cursor:pointer}
  .btn:hover{background:#9d174d}
  .btn.alt{background:#fff;color:#be185d!important;border:1px solid #e7c5d4}
  .hero{padding:56px 0 36px;text-align:center}
  .hero h1{font-size:clamp(30px,5vw,48px);line-height:1.15;margin:0 0 16px;letter-spacing:-.02em}
  .hero p{font-size:19px;color:#57534e;max-width:640px;margin:0 auto 26px}
  .shot{max-width:980px;margin:34px auto 0;border-radius:18px;overflow:hidden;box-shadow:0 24px 60px rgba(190,24,93,.18),0 4px 14px rgba(0,0,0,.08);background:#fff}
  .shot svg{display:block;width:100%;height:auto}
  .ico{width:42px;height:42px;border-radius:12px;background:#fce7f3;color:#be185d;display:flex;align-items:center;justify-content:center;margin-bottom:12px}
  .contact-list{list-style:none;padding:0;margin:0}.contact-list li{padding:10px 0;border-bottom:1px solid #f0eeed;display:flex;gap:12px;align-items:center}.contact-list li:last-child{border:0}
  .contact-list .ico{margin:0;flex:none}.contact-list small{display:block;color:#78716c;font-size:12px}
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
  $brandHtml
  <nav>$nav</nav>
</header>
<div class="$wrap">
$body
</div>
<footer>$footerHtml</footer>
</body>
</html>
HTML;
    }


    /** Illustrazione della home: agenda sul computer e prenotazione sul telefono (disegno originale). */
    public static function heroImage(): string
    {
        return <<<'SVG'
<svg viewBox="0 0 960 540" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Anteprima dell'agenda sul computer e della prenotazione sul telefono" font-family="system-ui,-apple-system,'Segoe UI',sans-serif">
  <defs>
    <radialGradient id="g1" cx="25%" cy="30%" r="60%"><stop offset="0" stop-color="#f9a8d4" stop-opacity=".75"/><stop offset="1" stop-color="#f9a8d4" stop-opacity="0"/></radialGradient>
    <radialGradient id="g2" cx="80%" cy="70%" r="60%"><stop offset="0" stop-color="#c4b5fd" stop-opacity=".7"/><stop offset="1" stop-color="#c4b5fd" stop-opacity="0"/></radialGradient>
    <filter id="sh" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="14" stdDeviation="16" flood-color="#1c1917" flood-opacity=".18"/></filter>
    <filter id="sh2" x="-20%" y="-20%" width="140%" height="160%"><feDropShadow dx="0" dy="8" stdDeviation="9" flood-color="#1c1917" flood-opacity=".2"/></filter>
  </defs>
  <rect width="960" height="540" fill="url(#g1)"/><rect width="960" height="540" fill="url(#g2)"/>

  <!-- finestra del computer -->
  <g filter="url(#sh)">
    <rect x="30" y="34" width="650" height="440" rx="16" fill="#fff" stroke="#e7e5e4"/>
  </g>
  <path d="M30 50a16 16 0 0 1 16-16h618a16 16 0 0 1 16 16v22H30z" fill="#f5f5f4"/>
  <circle cx="54" cy="53" r="5" fill="#fca5a5"/><circle cx="72" cy="53" r="5" fill="#fcd34d"/><circle cx="90" cy="53" r="5" fill="#86efac"/>
  <rect x="140" y="45" width="220" height="17" rx="8" fill="#fff" stroke="#e7e5e4"/>
  <text x="152" y="57.5" font-size="10" fill="#78716c">appuntamenti4me.it/calendar</text>
  <!-- menu laterale -->
  <rect x="30" y="72" width="140" height="402" fill="#faf8f7"/>
  <rect x="44" y="88" width="22" height="22" rx="6" fill="#be185d"/>
  <rect x="74" y="93" width="76" height="9" rx="4" fill="#44403c"/>
  <g fill="#d6d3d1"><rect x="44" y="132" width="100" height="9" rx="4"/><rect x="44" y="160" width="84" height="9" rx="4"/><rect x="44" y="188" width="92" height="9" rx="4"/><rect x="44" y="216" width="70" height="9" rx="4"/><rect x="44" y="244" width="88" height="9" rx="4"/></g>
  <rect x="36" y="150" width="128" height="30" rx="8" fill="#fce7f3"/><rect x="44" y="160" width="84" height="9" rx="4" fill="#be185d"/>
  <rect x="48" y="396" width="106" height="52" rx="10" fill="#fff" stroke="#e7e5e4"/>
  <text x="62" y="418" font-size="10" fill="#78716c">Oggi</text><text x="62" y="438" font-size="18" font-weight="700" fill="#1c1917">7</text>
  <text x="104" y="418" font-size="10" fill="#78716c">In arrivo</text><text x="104" y="438" font-size="18" font-weight="700" fill="#1c1917">24</text>
  <!-- agenda -->
  <text x="192" y="104" font-size="16" font-weight="700" fill="#1c1917">Agenda · sabato 7 novembre</text>
  <rect x="520" y="88" width="140" height="24" rx="8" fill="#be185d"/><text x="590" y="104" font-size="11" font-weight="600" fill="#fff" text-anchor="middle">+ Nuova prenotazione</text>
  <g font-size="11" font-weight="600" fill="#44403c">
    <circle cx="262" cy="140" r="9" fill="#ec4899"/><text x="278" y="144">Giulia</text>
    <circle cx="398" cy="140" r="9" fill="#3b82f6"/><text x="414" y="144">Marco</text>
    <circle cx="534" cy="140" r="9" fill="#f59e0b"/><text x="550" y="144">Sara</text>
  </g>
  <g stroke="#f0eeed"><line x1="192" y1="164" x2="664" y2="164"/><line x1="192" y1="214" x2="664" y2="214"/><line x1="192" y1="264" x2="664" y2="264"/><line x1="192" y1="314" x2="664" y2="314"/><line x1="192" y1="364" x2="664" y2="364"/><line x1="192" y1="414" x2="664" y2="414"/><line x1="192" y1="464" x2="664" y2="464"/>
    <line x1="334" y1="152" x2="334" y2="470"/><line x1="470" y1="152" x2="470" y2="470"/></g>
  <g font-size="9" fill="#a8a29e"><text x="192" y="176">09:00</text><text x="192" y="226">10:00</text><text x="192" y="276">11:00</text><text x="192" y="326">12:00</text><text x="192" y="376">15:00</text><text x="192" y="426">16:00</text></g>
  <!-- appuntamenti -->
  <rect x="228" y="168" width="100" height="62" rx="8" fill="#fce7f3" stroke="#ec4899"/><text x="238" y="186" font-size="11" font-weight="700" fill="#9d174d">Taglio donna</text><text x="238" y="202" font-size="10" fill="#9d174d">Chiara Rossi</text><text x="238" y="217" font-size="9" fill="#be185d">09:00 – 10:00</text>
  <rect x="228" y="268" width="100" height="94" rx="8" fill="#ede9fe" stroke="#8b5cf6"/><text x="238" y="286" font-size="11" font-weight="700" fill="#5b21b6">Colore</text><text x="238" y="302" font-size="10" fill="#5b21b6">Francesca Verdi</text><text x="238" y="317" font-size="9" fill="#6d28d9">11:00 – 12:30</text>
  <rect x="364" y="168" width="100" height="40" rx="8" fill="#dbeafe" stroke="#3b82f6"/><text x="374" y="186" font-size="11" font-weight="700" fill="#1d4ed8">Barba</text><text x="374" y="200" font-size="10" fill="#1d4ed8">Luca Bianchi</text>
  <rect x="364" y="218" width="100" height="40" rx="8" fill="#fef3c7" stroke="#f59e0b" stroke-dasharray="4 3"/><text x="374" y="236" font-size="11" font-weight="700" fill="#92400e">Da approvare</text><text x="374" y="250" font-size="10" fill="#92400e">Paolo Neri</text>
  <rect x="364" y="318" width="100" height="62" rx="8" fill="#dcfce7" stroke="#10b981"/><text x="374" y="336" font-size="11" font-weight="700" fill="#166534">Piega</text><text x="374" y="352" font-size="10" fill="#166534">Anna Gallo</text><text x="374" y="367" font-size="9" fill="#15803d">15:00 – 15:45</text>
  <rect x="500" y="218" width="100" height="62" rx="8" fill="#fce7f3" stroke="#ec4899"/><text x="510" y="236" font-size="11" font-weight="700" fill="#9d174d">Acconciatura</text><text x="510" y="252" font-size="10" fill="#9d174d">Marta Conti</text>
  <rect x="500" y="368" width="100" height="40" rx="8" fill="#fef3c7" stroke="#f59e0b"/><text x="510" y="386" font-size="11" font-weight="700" fill="#92400e">Trattamento</text><text x="510" y="400" font-size="10" fill="#92400e">Elisa Neri</text>

  <!-- telefono -->
  <g filter="url(#sh)">
    <rect x="690" y="62" width="236" height="430" rx="36" fill="#1c1917"/>
  </g>
  <rect x="700" y="72" width="216" height="410" rx="28" fill="#fff"/>
  <rect x="780" y="80" width="56" height="12" rx="6" fill="#1c1917"/>
  <rect x="700" y="100" width="216" height="64" fill="#be185d"/><path d="M700 100h216v0" />
  <text x="716" y="127" font-size="14" font-weight="700" fill="#fff">Prenota da Salone Rossi</text>
  <text x="716" y="147" font-size="10" fill="#fbcfe8">Scegli servizio, giorno e orario</text>
  <text x="716" y="190" font-size="10" font-weight="700" fill="#78716c">1 · SERVIZIO</text>
  <rect x="716" y="198" width="184" height="34" rx="8" fill="#fdf2f8" stroke="#be185d"/><text x="728" y="219" font-size="11" font-weight="600" fill="#9d174d">Taglio donna · 60 min</text><circle cx="882" cy="215" r="7" fill="#be185d"/><path d="M878.5 215l2.5 2.5 4-5" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
  <rect x="716" y="238" width="184" height="34" rx="8" fill="#fff" stroke="#e7e5e4"/><text x="728" y="259" font-size="11" fill="#57534e">Piega · 45 min</text>
  <text x="716" y="296" font-size="10" font-weight="700" fill="#78716c">2 · GIORNO</text>
  <g font-size="9" text-anchor="middle" fill="#57534e">
    <rect x="716" y="304" width="30" height="34" rx="7" fill="#fff" stroke="#e7e5e4"/><text x="731" y="318" fill="#a8a29e">lun</text><text x="731" y="331" font-weight="700">9</text>
    <rect x="752" y="304" width="30" height="34" rx="7" fill="#be185d"/><text x="767" y="318" fill="#fbcfe8">mar</text><text x="767" y="331" font-weight="700" fill="#fff">10</text>
    <rect x="788" y="304" width="30" height="34" rx="7" fill="#fff" stroke="#e7e5e4"/><text x="803" y="318" fill="#a8a29e">mer</text><text x="803" y="331" font-weight="700">11</text>
    <rect x="824" y="304" width="30" height="34" rx="7" fill="#fff" stroke="#e7e5e4"/><text x="839" y="318" fill="#a8a29e">gio</text><text x="839" y="331" font-weight="700">12</text>
    <rect x="860" y="304" width="40" height="34" rx="7" fill="#f5f5f4"/><text x="880" y="318" fill="#d6d3d1">sab</text><text x="880" y="331" font-weight="700" fill="#d6d3d1">13</text>
  </g>
  <text x="716" y="362" font-size="10" font-weight="700" fill="#78716c">3 · ORARIO</text>
  <g font-size="10" font-weight="600" text-anchor="middle">
    <rect x="716" y="370" width="56" height="26" rx="7" fill="#fff" stroke="#e7e5e4"/><text x="744" y="387" fill="#57534e">09:00</text>
    <rect x="778" y="370" width="56" height="26" rx="7" fill="#be185d"/><text x="806" y="387" fill="#fff">10:30</text>
    <rect x="840" y="370" width="60" height="26" rx="7" fill="#fff" stroke="#e7e5e4"/><text x="870" y="387" fill="#57534e">15:00</text>
  </g>
  <rect x="716" y="424" width="184" height="36" rx="10" fill="#be185d"/><text x="808" y="447" font-size="12" font-weight="700" fill="#fff" text-anchor="middle">Invia richiesta</text>

  <!-- notifica -->
  <g filter="url(#sh2)">
    <rect x="436" y="408" width="236" height="62" rx="14" fill="#fff" stroke="#e7e5e4"/>
  </g>
  <circle cx="466" cy="439" r="16" fill="#dcfce7"/><path d="M458 439l6 6 11-12" stroke="#16a34a" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
  <text x="492" y="434" font-size="12" font-weight="700" fill="#1c1917">Prenotazione confermata</text>
  <text x="492" y="452" font-size="10" fill="#78716c">Martedì 10 alle 10:30 · email inviata</text>
</svg>
SVG;
    }

    /** Icone semplici (24x24, tratto) per le schede dei vantaggi. */
    public static function icon(string $name): string
    {
        $paths = [
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
            'sparkles' => '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
            'phone' => '<rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18h2"/>',
            'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><circle cx="17.5" cy="9" r="2.5"/><path d="M17.5 14c2.5 0 4 1.8 4 4.5"/>',
            'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
            'chat' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M8.5 11h7M8.5 14h4"/>',
            'euro' => '<circle cx="12" cy="12" r="9"/><path d="M15.5 9a4 4 0 1 0 0 6M7.5 11h6M7.5 13.5h6"/>',
            'pin' => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'bell' => '<path d="M6 17V11a6 6 0 1 1 12 0v6l1.5 2h-15z"/><path d="M10 21h4"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 7l8.5 6 8.5-6"/>',
        ];
        $p = $paths[$name] ?? $paths['grid'];
        return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    }

    /** @param array<string,mixed> $cfg */
    public static function contactEmail(array $cfg): string
    {
        return (string)(($cfg['contact_email'] ?? '') !== '' ? $cfg['contact_email'] : 'info@appuntamenti4me.it');
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
            ['I miei clienti possono prenotare da soli online?', "Sì. Ogni negozio ha la sua pagina (per esempio appuntamenti4me.it/nome-del-negozio) con logo, contatti e orari. I clienti scelgono servizio, giorno e ora tra quelli liberi; se non trovano un orario adatto possono inviare comunque una richiesta. Le richieste le approvi tu dal backoffice."],
            ['Come vengono avvisati i clienti?', "Con una email automatica quando inviano la richiesta e quando tu la confermi o la rifiuti. Per WhatsApp l'app prepara il messaggio già scritto e lo invii tu con un tocco: non c'è invio automatico. Non sono previsti SMS."],
            ['Devo essere approvato per usare l\'app?', "Sì: ogni nuova registrazione viene controllata prima dell'attivazione, di solito in poco tempo. Appena approvata ricevi una email e parte la prova gratuita."],
            ['Come ricevo assistenza?', $contact],
        ];
    }

    /** @param array<string,mixed> $cfg */
    public static function landing(array $cfg, array $dbPlans = []): string
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
        $plansHtml = '<div class="card plan"><span class="tag">Per iniziare</span><h3>Prova gratuita</h3><div class="price">0 € <small>per ' . $days . ' giorni</small></div>'
            . '<ul class="tick"><li>Tutte le funzioni incluse</li><li>Nessuna carta di credito</li><li>I dati restano se poi scegli un piano</li></ul>'
            . '<p style="margin-top:16px"><a class="btn" href="/registrati">Inizia gratis</a></p></div>';
        if ($dbPlans !== []) {
            // Piani gestiti dall'amministratore (nome, durata e prezzo).
            $bestId = 0;
            foreach ($dbPlans as $p) {
                if ($p['duration_days'] >= 365) {
                    $bestId = $p['id'];
                }
            }
            foreach ($dbPlans as $p) {
                $isBest = $p['id'] === $bestId;
                $plansHtml .= '<div class="card plan' . ($isBest ? ' best' : '') . '">' . ($isBest ? '<span class="tag">Il più conveniente</span>' : '') . '<h3>' . self::h($p['name']) . '</h3><div class="price">' . self::h(Plans::priceLabel($p['price'])) . ' <small>' . self::h(Plans::durationLabel($p['duration_days'])) . '</small></div>' . $included . '</div>';
            }
        } else {
            if ($monthly !== '') {
                $plansHtml .= '<div class="card plan' . ($yearly === '' ? ' best' : '') . '"><h3>Mensile</h3><div class="price">' . self::h($monthly) . '</div>' . $included . '<p class="note" style="text-align:left">Disdici quando vuoi.</p></div>';
            }
            if ($yearly !== '') {
                $plansHtml .= '<div class="card plan best"><span class="tag">Il più conveniente</span><h3>Annuale</h3><div class="price">' . self::h($yearly) . '</div>' . $included . '<p class="note" style="text-align:left">Un solo pagamento all\'anno.</p></div>';
            }
        }
        $costs = '<section class="blk" id="costi"><h2>Costi chiari</h2><p class="sub">Un solo prezzo, tutte le funzioni. Prima provi, poi decidi.</p><div class="plans" style="max-width:960px">'
            . $plansHtml . '</div><p class="note">Paghi online con carta tramite Stripe. Puoi disdire dalla pagina Abbonamento.</p></section>';

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

        $i_grid = self::icon('grid');
        $i_shield = self::icon('shield');
        $i_sparkles = self::icon('sparkles');
        $i_phone = self::icon('phone');
        $i_users = self::icon('users');
        $i_chart = self::icon('chart');
        $i_chat = self::icon('chat');
        $i_euro = self::icon('euro');
        $i_calendar = self::icon('calendar');
        $hero = self::heroImage();
        $body = <<<HTML
<section class="hero">
  <h1>L'agenda degli appuntamenti per il tuo negozio</h1>
  <p>Una pagina di prenotazione tutta tua, conferme e promemoria automatici, clienti e incassi in un unico posto. Funziona da telefono, tablet e computer. Provalo gratis per $days giorni.</p>
  <a class="btn" href="/registrati">Inizia la prova gratuita</a>
  <p style="font-size:14px;margin:12px auto 0;color:#78716c">Nessuna carta di credito richiesta.</p>
  <div class="shot">$hero</div>
  <div class="chips">$chips</div>
</section>

<section class="blk" id="vantaggi">
  <h2>Perché usarla</h2>
  <p class="sub">Meno carta e messaggi sparsi, più tempo per il tuo lavoro.</p>
  <div class="grid">
    <div class="card"><div class="ico">{$i_grid}</div><h3>Tutto in un posto</h3><p>Agenda, clienti, collaboratori, servizi, prodotti e incassi: niente più quaderni, fogli e chat da incrociare.</p></div>
    <div class="card"><div class="ico">{$i_shield}</div><h3>Niente sovrapposizioni</h3><p>Se due appuntamenti si accavallano per lo stesso collaboratore, l'app ti avvisa prima di confermare.</p></div>
    <div class="card"><div class="ico">{$i_sparkles}</div><h3>Pronta per il tuo mestiere</h3><p>Scegli il tipo di attività e trovi servizi e nomi già adatti (per esempio interventi e ricambi per un'officina). Poi li modifichi come vuoi.</p></div>
    <div class="card"><div class="ico">{$i_phone}</div><h3>Promemoria che non dimentichi</h3><p>Il giorno prima il cliente riceve un promemoria, la sera tu ricevi il riepilogo di domani: meno buchi e meno clienti che non si presentano.</p></div>
    <div class="card"><div class="ico">{$i_users}</div><h3>Rispondi con un tocco</h3><p>Ogni richiesta ti arriva per email con il pulsante Approva o Rifiuta, senza nemmeno accedere. Per i servizi che vuoi, la conferma è immediata.</p></div>
    <div class="card"><div class="ico">{$i_chart}</div><h3>Ferie e chiusure</h3><p>Chiudi i giorni di ferie in un attimo: in quel periodo la tua pagina non offre orari. Esporti gli appuntamenti in Excel quando vuoi.</p></div>
    <div class="card"><div class="ico">{$i_chat}</div><h3>Semplice e in italiano</h3><p>Importi in euro, date e orari all'italiana, comandi chiari, da telefono, tablet o computer: si impara in pochi minuti.</p></div>
    <div class="card"><div class="ico">{$i_calendar}</div><h3>La tua pagina di prenotazione</h3><p>Un indirizzo tutto tuo con logo, contatti e orari, più il codice QR da stampare: i clienti prenotano da soli negli orari liberi, anche di notte.</p></div>
    <div class="card"><div class="ico">{$i_euro}</div><h3>Prezzo senza sorprese</h3><p>Un solo prezzo con tutte le funzioni, senza limiti di clienti o appuntamenti. Prima provi gratis, poi scegli.</p></div>
  </div>
</section>

<section class="blk" id="clienti">
  <h2>Comodo anche per i tuoi clienti</h2>
  <p class="sub">Prenotare deve richiedere pochi secondi.</p>
  <div class="steps">
    <div class="card"><h3>Primo orario libero</h3><p>Scelti i servizi, la pagina propone subito il primo giorno e orario disponibile. Basta un tocco.</p></div>
    <div class="card"><h3>Telefono o email</h3><p>Niente registrazioni: bastano il nome e un recapito. Conferma, calendario e link per annullare arrivano per email.</p></div>
    <div class="card"><h3>Sposta o annulla da solo</h3><p>Se cambia qualcosa, il cliente sposta o annulla la prenotazione dal link ricevuto, senza telefonare.</p></div>
  </div>
</section>

<section class="blk" id="come-funziona">
  <h2>Come funziona</h2>
  <div class="steps">
    <div class="card"><h3>Ti registri</h3><p>Nome dell'attività, tipo di attività, email e password. Ci vuole un minuto e non serve la carta. Dopo l'approvazione parte la prova.</p></div>
    <div class="card"><h3>Trovi già i servizi</h3><p>L'agenda parte con servizi e collaboratore pronti. Cambi prezzi, durate e nomi, aggiungi i tuoi clienti.</p></div>
    <div class="card"><h3>Condividi il link</h3><p>Metti il link o il QR su WhatsApp, Instagram e in negozio. Le prenotazioni arrivano da sole; tu gestisci tutto dall'agenda. Dopo la prova scegli se continuare.</p></div>
  </div>
</section>

$costs

<section class="blk" id="faq">
  <h2>Domande frequenti</h2>
  <div class="faq">$faqHtml</div>
</section>

<div class="cta"><a class="btn" href="/registrati">Crea il tuo account gratis</a><p class="note">$days giorni di prova, nessuna carta di credito.</p></div>
HTML;
        $nav = '<a href="#vantaggi">Vantaggi</a><a href="#clienti">Per i clienti</a><a href="#costi">Costi</a><a href="#faq">Domande</a><a href="/contatti">Contatti</a><a href="/login">Accedi</a><a class="btn" style="color:#fff;padding:8px 16px;margin-left:16px" href="/registrati">Prova gratis</a>';
        return self::layout($cfg, $name . ' · Agenda appuntamenti per il tuo negozio', $body, false, false, $nav, $head);
    }

    /**
     * Pagina contatti. L'email è sempre mostrata; gli altri riferimenti solo se presenti in config ('contact').
     * @param array<string,mixed> $cfg
     */
    public static function contact(array $cfg): string
    {
        $email = self::contactEmail($cfg);
        $c = is_array($cfg['contact'] ?? null) ? $cfg['contact'] : [];
        $rows = '<li><span class="ico">' . self::icon('mail') . '</span><span><small>Email</small><a href="mailto:' . self::h($email) . '">' . self::h($email) . '</a></span></li>';
        $phone = trim((string)($c['phone'] ?? ''));
        if ($phone !== '') {
            $tel = preg_replace('/[^0-9+]/', '', $phone);
            $rows .= '<li><span class="ico">' . self::icon('phone') . '</span><span><small>Telefono</small><a href="tel:' . self::h((string)$tel) . '">' . self::h($phone) . '</a></span></li>';
        }
        $wa = preg_replace('/\D/', '', (string)($c['whatsapp'] ?? ''));
        if ($wa !== '') {
            $rows .= '<li><span class="ico">' . self::icon('chat') . '</span><span><small>WhatsApp</small><a href="https://wa.me/' . self::h((string)$wa) . '" rel="noopener">Scrivici su WhatsApp</a></span></li>';
        }
        $name = trim((string)($c['name'] ?? ''));
        $address = trim((string)($c['address'] ?? ''));
        $vat = trim((string)($c['vat'] ?? ''));
        $who = '';
        if ($name !== '' || $address !== '' || $vat !== '') {
            $who = '<div class="card" style="margin-top:16px"><h3>Riferimenti</h3><p>'
                . ($name !== '' ? '<b>' . self::h($name) . '</b><br>' : '')
                . ($address !== '' ? self::h($address) . '<br>' : '')
                . ($vat !== '' ? 'P. IVA ' . self::h($vat) : '')
                . '</p></div>';
        }
        $body = '<section class="hero" style="padding-bottom:10px"><h1>Contatti</h1><p>Hai domande o vuoi una mano a iniziare? Scrivici: rispondiamo appena possibile.</p></section>'
            . '<div class="card"><ul class="contact-list">' . $rows . '</ul></div>' . $who
            . '<div class="cta"><a class="btn" href="/registrati">Prova gratis</a> <a class="btn alt" href="/">Torna alla home</a></div>';
        $head = '<meta name="description" content="' . self::h('Contatta ' . self::name($cfg) . ': ' . $email) . '">' . "\n";
        return self::layout($cfg, 'Contatti · ' . self::name($cfg), $body, true, false, null, $head);
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
  <p class="sub">Crea il tuo account: ti serve un minuto. Nessuna carta richiesta. La registrazione viene approvata prima dell'attivazione.</p>
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
  <p class="alt-link" style="margin-top:14px"><a href="/password-dimenticata">Password dimenticata?</a></p>
</form>
$reg
HTML;
        return self::layout($cfg, 'Accedi · ' . self::name($cfg), $body, true);
    }

    /** Richiesta del link per reimpostare la password. @param array<string,mixed> $cfg */
    public static function forgot(array $cfg, ?string $notice = null): string
    {
        $msg = $notice !== null ? '<p class="ok">' . self::h($notice) . '</p>' : '';
        $body = <<<HTML
<form class="box" method="post" action="/password-dimenticata">
  <h1>Password dimenticata</h1>
  <p class="sub">Scrivi la tua email: se è registrata ti mandiamo un link per sceglierne una nuova.</p>
  $msg
  <label for="email">Email</label>
  <input id="email" name="email" type="email" autocomplete="email" required autofocus>
  <button class="btn" type="submit">Invia il link</button>
  <p class="alt-link" style="margin-top:14px"><a href="/login">Torna all'accesso</a></p>
</form>
HTML;
        return self::layout($cfg, 'Password dimenticata · ' . self::name($cfg), $body, true);
    }

    /** Scelta della nuova password dal link ricevuto. @param array<string,mixed> $cfg */
    public static function resetForm(array $cfg, string $token, bool $valid, ?string $error = null, bool $done = false): string
    {
        if ($done) {
            $body = '<form class="box"><h1>Password aggiornata</h1><p class="sub">Ora puoi accedere con la nuova password.</p><a class="btn" href="/login">Vai all\'accesso</a></form>';
        } elseif (!$valid) {
            $body = '<form class="box"><h1>Link non valido</h1><p class="sub">Il link non è corretto o è scaduto.</p><a class="btn" href="/password-dimenticata">Richiedine uno nuovo</a></form>';
        } else {
            $err = $error !== null ? '<p class="err">' . self::h($error) . '</p>' : '';
            $t = self::h($token);
            $body = <<<HTML
<form class="box" method="post" action="/reimposta/$t">
  <h1>Nuova password</h1>
  <p class="sub">Scegli una password di almeno 8 caratteri.</p>
  $err
  <label for="password">Nuova password</label>
  <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus>
  <button class="btn" type="submit">Salva la password</button>
</form>
HTML;
        }
        return self::layout($cfg, 'Nuova password · ' . self::name($cfg), $body, true);
    }

    /** Conferma dell'approvazione di un negozio dal link nell'email dell'amministratore. @param array<string,mixed> $cfg @param array<string,mixed>|null $acct */
    public static function approveLink(array $cfg, ?array $acct, string $sig, ?string $notice = null): string
    {
        if ($acct === null) {
            return self::layout($cfg, 'Link non valido', '<form class="box"><h1>Link non valido</h1><p class="sub">Il link non è corretto.</p></form>', true);
        }
        $pending = (int)$acct['approved'] === 0;
        $body = '<form class="box" method="post" action="/approva/' . (int)$acct['id'] . '/' . self::h($sig) . '"><h1>Nuovo negozio</h1>'
            . ($notice !== null ? '<p class="ok">' . self::h($notice) . '</p>' : '')
            . '<p><b>' . self::h((string)$acct['business_name']) . '</b></p><p class="sub">' . self::h((string)$acct['email']) . '<br>' . self::h(Categories::get((string)$acct['category'])['label']) . '</p>'
            . ($pending ? '<button class="btn" type="submit">Approva e avvia la prova</button>' : '<p>Questo negozio è già approvato.</p>')
            . '</form>';
        return self::layout($cfg, 'Approva negozio', $body, true);
    }

    /** @param array<string,mixed> $cfg @param array<string,mixed> $acct */
    public static function pending(array $cfg, array $acct): string
    {
        $email = self::h(self::contactEmail($cfg));
        $body = '<form class="box"><h1>Registrazione ricevuta</h1>'
            . '<p class="sub">Grazie ' . self::h((string)$acct['business_name']) . '! Controlliamo ogni nuova attività prima di attivarla.</p>'
            . '<p>Appena la tua registrazione sarà approvata ti scriveremo all\'indirizzo <b>' . self::h((string)$acct['email']) . '</b> e potrai entrare e iniziare la prova gratuita.</p>'
            . '<p style="color:#57534e;font-size:14px">Hai fretta? Scrivici a <a href="mailto:' . $email . '">' . $email . '</a>.</p></form>'
            . '<p class="alt-link"><a href="/logout">Esci</a></p>';
        return self::layout($cfg, 'In attesa di approvazione', $body, true);
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
    public static function billing(array $cfg, array $acct, array $plan, ?string $notice = null, ?string $error = null, array $dbPlans = []): string
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
        } elseif ($stripe && $state !== 'active' && $dbPlans !== []) {
            foreach ($dbPlans as $p) {
                if ($p['stripe_price_id'] === '') {
                    continue;
                }
                $buttons .= '<form method="post" action="/abbonamento/acquista" style="margin:10px 0"><input type="hidden" name="piano" value="' . (int)$p['id'] . '"><button class="btn" style="width:100%">'
                    . 'Scegli ' . self::h($p['name']) . ' · ' . self::h(Plans::priceLabel($p['price'])) . ' ' . self::h(Plans::durationLabel($p['duration_days'])) . '</button></form>';
            }
            if ($buttons === '') {
                $buttons = '<p class="sub">Contattaci per attivare l\'abbonamento: ' . self::h(self::contactEmail($cfg)) . '</p>';
            }
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
    public static function admin(array $cfg, array $rows, array $stats, array $f, string $csrf, int $selfId, ?string $notice = null, ?string $error = null, array $plans = [], array $extra = []): string
    {
        $h = [self::class, 'h'];
        $fmt = static fn(string $d, string $format = 'd/m/Y'): string => $d === '' ? '—' : date($format, (int)strtotime($d));
        $labels = ['pending' => 'Da approvare', 'trial' => 'In prova', 'active' => 'Attivo', 'expired' => 'Scaduto', 'suspended' => 'Sospeso'];

        $cards = '';
        foreach ([['total', 'Negozi registrati'], ['pending', 'Da approvare'], ['trial', 'In prova'], ['active', 'Abbonati'], ['expired', 'Prova scaduta'], ['suspended', 'Sospesi'], ['new7', 'Nuovi (7 giorni)']] as [$k, $label]) {
            $cards .= '<div class="stat"><b>' . $stats[$k] . '</b><span>' . $label . '</span></div>';
        }

        $opts = '<option value="">Tutti gli stati</option>';
        foreach ($labels as $k => $label) {
            $opts .= '<option value="' . $k . '"' . ($f['state'] === $k ? ' selected' : '') . '>' . $label . '</option>';
        }

        $planOpts = '';
        foreach ($plans as $p) {
            if ($p['active']) {
                $planOpts .= '<option value="' . (int)$p['id'] . '">' . self::h($p['name']) . ' (' . (int)$p['duration_days'] . ' g)</option>';
            }
        }
        $body = '';
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $state = $r['plan']['state'];
            $when = $state === 'trial' ? 'fino al ' . $fmt($r['plan']['ends_at']) . ' (' . $r['plan']['days_left'] . ' g)'
                : ($state === 'active' ? ($r['plan']['ends_at'] !== '' ? 'rinnovo ' . $fmt($r['plan']['ends_at']) : 'senza scadenza')
                : ($state === 'expired' ? 'dal ' . $fmt($r['plan']['ends_at']) : ''));
            if (!empty($r['is_admin'])) {
                $when = 'amministratore · senza scadenza';
            }
            $hidden = '<input type="hidden" name="csrf" value="' . self::h($csrf) . '"><input type="hidden" name="id" value="' . $id . '">';
            $form = static fn(string $action, string $inner, string $extra = ''): string
                => '<form method="post" action="/admin" ' . $extra . '>' . $hidden . '<input type="hidden" name="azione" value="' . $action . '">' . $inner . '</form>';
            $menu = ($state === 'pending' ? $form('approve', '<button class="mini">Approva</button>') : '')
                . $form('extend', '<input type="hidden" name="days" value="7"><button class="mini">+7 giorni</button>')
                . $form('extend', '<input type="hidden" name="days" value="30"><button class="mini">+30 giorni</button>')
                . ($planOpts !== '' ? $form('assign', '<select name="plan_id" style="height:32px;width:auto;font-size:13px">' . $planOpts . '</select><button class="mini">Assegna piano</button>') : '')
                . $form('activate', '<input type="date" name="until" required><button class="mini">Attiva fino al</button>')
                . $form('suspend', '<button class="mini">' . ($r['suspended'] ? 'Riattiva account' : 'Sospendi account') . '</button>')
                . ($id !== $selfId ? $form('impersonate', '<button class="mini">Entra come questo negozio</button>') : '')
                . $form('reset', '<button class="mini">Nuova password</button>', 'onsubmit="return confirm(\'Generare una nuova password per questo negozio?\')"');
            if ($id !== $selfId) {
                $menu .= $form('delete', '<button class="mini danger">Elimina account e dati</button>',
                    'onsubmit="return confirm(\'Eliminare DEFINITIVAMENTE ' . self::h(addslashes((string)$r['business_name'])) . ' e tutti i suoi dati?\')"');
            }
            $menu = '<a class="mini" style="text-decoration:none;text-align:center" href="/admin?duplica=' . $id . '#demo">Duplica per una demo</a>'
                . $form('email', '<input type="email" name="email" required placeholder="Nuova email di accesso" style="height:32px;width:auto;font-size:13px"><button class="mini">Cambia email</button>')
                . $menu;
            $quick = $state === 'pending' ? $form('approve', '<button class="mini" style="background:#be185d;color:#fff">Approva</button>') : '';
            $body .= '<tr><td><b>' . self::h((string)$r['business_name']) . '</b><br><small>' . self::h((string)$r['email']) . '</small><br><small>/' . self::h((string)$r['slug']) . '</small>' . (!empty($r['is_demo']) ? ' <span class="badge b-demo">Demo</span>' : '') . '</td>'
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

        $planRows = '';
        $planForm = static function (array $p) use ($csrf): string {
            $id = (int)$p['id'];
            return '<form method="post" action="/admin" class="planrow"><input type="hidden" name="csrf" value="' . self::h($csrf) . '"><input type="hidden" name="id" value="' . $id . '">'
                . '<input name="name" value="' . self::h((string)$p['name']) . '" placeholder="Nome (es. Annuale)" required maxlength="60" aria-label="Nome">'
                . '<input name="duration_days" type="number" min="1" max="3650" value="' . (int)$p['duration_days'] . '" required aria-label="Durata in giorni" title="Durata in giorni">'
                . '<input name="price" value="' . ($id === 0 ? '' : self::h(number_format((float)$p['price'], 2, ',', ''))) . '" inputmode="decimal" placeholder="Prezzo €" required aria-label="Prezzo in euro">'
                . '<input name="stripe_price_id" value="' . self::h((string)$p['stripe_price_id']) . '" placeholder="Codice Stripe (price_...) facoltativo" aria-label="Codice prezzo Stripe">'
                . '<label class="check" style="margin:0"><input type="checkbox" name="active" value="1"' . (!empty($p['active']) ? ' checked' : '') . '>Attivo</label>'
                . '<button class="mini" name="azione" value="plan_save">' . ($id === 0 ? 'Aggiungi piano' : 'Salva') . '</button>'
                . ($id !== 0 ? '<button class="mini danger" name="azione" value="plan_delete" onclick="return confirm(\'Eliminare questo piano?\')">Elimina</button>' : '')
                . '</form>';
        };
        foreach ($plans as $p) {
            $planRows .= $planForm($p);
        }
        $planRows .= $planForm(['id' => 0, 'name' => '', 'duration_days' => 365, 'price' => 0, 'stripe_price_id' => '', 'active' => true]);
        $planBox = '<details class="card" style="margin:0 0 18px"' . ($plans === [] ? ' open' : '') . '><summary style="cursor:pointer;font-weight:700">Piani di abbonamento (' . count($plans) . ')</summary>'
            . '<p class="sub" style="text-align:left;margin:8px 0 12px;font-size:14px">Nome, durata in giorni e prezzo. I piani attivi compaiono nella home del sito e nella pagina Abbonamento; con il codice Stripe il cliente può pagare online, senza codice li assegni tu da "Azioni".</p>'
            . $planRows . '</details>';
        $demoBox = self::adminDemoBox($csrf, $extra);
        $catBox = self::adminCategoriesBox($csrf, $extra);
        $n = $notice ? '<p class="ok">' . $notice . '</p>' : ''; // già escapato da chi lo costruisce
        $e = $error ? '<p class="err">' . self::h($error) . '</p>' : '';
        $q = self::h($f['q']);

        $html = <<<HTML
<h2 style="text-align:left;margin:10px 0 14px">Amministrazione</h2>
$n$e
<div class="stats">$cards</div>
$demoBox
$planBox
$catBox
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

    /**
     * Riquadro "Crea una demo per un cliente": duplica un negozio con i dati del possibile acquirente.
     *
     * @param array<string,mixed> $x sources (id => nome), dup (id scelto), old (valori del modulo dopo un errore)
     */
    private static function adminDemoBox(string $csrf, array $x): string
    {
        $sources = (array)($x['sources'] ?? []);
        if ($sources === []) {
            return '';
        }
        $old = (array)($x['old'] ?? []);
        $dup = (int)($x['dup'] ?? 0);
        $v = static fn(string $k): string => self::h((string)($old[$k] ?? ''));
        $src = '';
        foreach ($sources as $id => $label) {
            $src .= '<option value="' . (int)$id . '"' . ((int)$id === $dup ? ' selected' : '') . '>' . self::h((string)$label) . '</option>';
        }
        $cats = '<option value="">Come il negozio copiato</option>';
        foreach (Categories::options() as $k => $label) {
            $cats .= '<option value="' . self::h($k) . '"' . (($old['category'] ?? '') === $k ? ' selected' : '') . '>' . self::h($label) . '</option>';
        }
        $open = $dup > 0 || $old !== [] ? ' open' : '';
        $copy = ($old === [] || !empty($old['copy_data'])) ? ' checked' : '';
        return <<<HTML
<details class="card" id="demo" style="margin:0 0 18px"$open><summary style="cursor:pointer;font-weight:700">Crea una demo per un cliente</summary>
<p class="sub" style="text-align:left;margin:8px 0 12px;font-size:14px">Duplica un negozio già pronto (per esempio quello di esempio) e mettici nome, logo e contatti del possibile acquirente: quando vai da lui gli mostri la sua agenda e la sua pagina di prenotazione, non una demo generica. Vengono copiati servizi, collaboratori con gli orari, prodotti e impostazioni; se vuoi anche clienti e appuntamenti, così l'agenda non è vuota.</p>
<form method="post" action="/admin" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="{$csrf}"><input type="hidden" name="azione" value="duplicate">
<div class="grid2">
  <label>Negozio da copiare<select name="id" required>$src</select></label>
  <label>Nome dell'attività *<input name="business_name" required maxlength="120" value="{$v('business_name')}" placeholder="es. Parrucchiera Giulia"></label>
  <label>Categoria<select name="category">$cats</select></label>
  <label>Email di accesso<input type="email" name="email" maxlength="190" value="{$v('email')}" placeholder="vuota = indirizzo demo automatico"></label>
  <label>Telefono<input name="shop_phone" maxlength="64" value="{$v('shop_phone')}"></label>
  <label>WhatsApp<input name="shop_whatsapp" maxlength="64" value="{$v('shop_whatsapp')}"></label>
  <label>Email del negozio (pubblica)<input type="email" name="shop_email" maxlength="190" value="{$v('shop_email')}"></label>
  <label>Indirizzo<input name="shop_address" maxlength="255" value="{$v('shop_address')}" placeholder="Via, numero, città"></label>
  <label>Orari (testo per la pagina pubblica)<input name="shop_hours" maxlength="500" value="{$v('shop_hours')}" placeholder="vuoto = come il negozio copiato"></label>
  <label>Logo (PNG, JPG, WebP, max 400 KB)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"></label>
</div>
<label style="display:block;font-size:13px;font-weight:600;color:#57534e;margin:10px 0 0">Descrizione<textarea name="shop_description" maxlength="1000" rows="2" style="margin-top:4px;width:100%">{$v('shop_description')}</textarea></label>
<label class="check"><input type="checkbox" name="copy_data" value="1"$copy>Copia anche clienti, appuntamenti e chiusure (agenda già piena per la demo)</label>
<button class="btn" type="submit" style="margin-top:6px">Crea la demo</button>
</form></details>
HTML;
    }

    /**
     * Riquadro "Categorie merceologiche": tipi di attività con servizi di partenza e nomi usati nell'app.
     *
     * @param array<string,mixed> $x categories (Categories::all()), usage (chiave => negozi), cat_old (modulo dopo un errore)
     */
    private static function adminCategoriesBox(string $csrf, array $x): string
    {
        $cats = (array)($x['categories'] ?? []);
        $usage = (array)($x['usage'] ?? []);
        $old = (array)($x['cat_old'] ?? []);
        $oldId = isset($old['id']) ? (int)$old['id'] : -1;
        $allCats = $cats;
        $form = static function (string $key, array $c) use ($csrf, $usage, $old, $oldId, $allCats): string {
            $id = (int)($c['id'] ?? 0);
            $useOld = $oldId === $id;
            $val = static fn(string $k, string $def) => self::h($useOld ? (string)($old[$k] ?? '') : $def);
            $t = (array)($c['terms'] ?? []) + Categories::DEFAULT_TERMS;
            $termInputs = '';
            foreach (['staff' => 'Collaboratore (singolare)', 'staffs' => 'Collaboratori (menu)', 'service' => 'Servizio (singolare)', 'services' => 'Servizi (menu)', 'product' => 'Prodotto (singolare)', 'products' => 'Prodotti (menu)'] as $k => $label) {
                $termInputs .= '<label>' . $label . '<input name="t_' . $k . '" maxlength="40" value="' . $val('t_' . $k, $id === 0 ? '' : (string)$t[$k]) . '" placeholder="' . self::h(Categories::DEFAULT_TERMS[$k]) . '"></label>';
            }
            $active = $useOld ? !empty($old['active']) : ($id === 0 || !empty($c['active']));
            $n = (int)($usage[$key] ?? 0);
            $keyField = $id === 0
                ? '<label>Codice (facoltativo)<input name="key" maxlength="32" value="' . $val('key', '') . '" placeholder="es. fisioterapia"></label>'
                : '<label>Ordine<input name="sort_order" type="number" min="1" max="9999" value="' . $val('sort_order', (string)(int)($c['sort_order'] ?? 0)) . '"></label>';
            $services = $val('services', $id === 0 ? '' : Categories::servicesToText((array)($c['services'] ?? [])));
            $summary = $id === 0
                ? '+ Nuova categoria'
                : self::h((string)$c['label']) . ' <span class="muted">· ' . count((array)$c['services']) . ' servizi · ' . $n . ($n === 1 ? ' negozio' : ' negozi') . (empty($c['active']) ? ' · <b>disattivata</b>' : '') . '</span>';
            $del = '';
            if ($id !== 0) {
                $move = '';
                if ($n > 0) {
                    foreach ($allCats as $k2 => $c2) {
                        if ($k2 !== $key) {
                            $move .= '<option value="' . self::h((string)$k2) . '"' . ($k2 === 'altro' ? ' selected' : '') . '>' . self::h((string)$c2['label']) . '</option>';
                        }
                    }
                    $move = '<label style="display:flex;gap:6px;align-items:center;margin:0;font-weight:400">e sposta i ' . $n . ($n === 1 ? ' negozio' : ' negozi') . ' in <select name="move_to" style="height:32px;width:auto;font-size:13px;margin:0">' . $move . '</select></label>';
                }
                $msg = $n > 0 ? 'Eliminare questa categoria e spostare i suoi negozi nella categoria scelta?' : 'Eliminare questa categoria?';
                $del = '<button class="mini danger" name="azione" value="cat_delete" formnovalidate onclick="return confirm(\'' . $msg . '\')">Elimina</button>' . $move;
            }
            return '<details class="cat"' . ($useOld ? ' open' : '') . '><summary>' . $summary . '</summary>'
                . '<form method="post" action="/admin" class="catform" style="margin-top:10px"><input type="hidden" name="csrf" value="' . self::h($csrf) . '"><input type="hidden" name="id" value="' . $id . '">'
                . '<div class="grid2"><label>Nome della categoria *<input name="label" required maxlength="80" value="' . $val('label', (string)($c['label'] ?? '')) . '" placeholder="es. Fisioterapia"></label>'
                . $keyField
                . '<label>Ruolo del primo collaboratore<input name="staff_title" maxlength="80" value="' . $val('staff_title', (string)($c['staff_title'] ?? '')) . '" placeholder="es. Fisioterapista"></label>'
                . '<label>Esempio di ruolo (suggerimento)<input name="staff_hint" maxlength="120" value="' . $val('staff_hint', (string)($c['staff_hint'] ?? '')) . '" placeholder="es. Fisioterapista senior"></label>'
                . $termInputs . '</div>'
                . '<label style="margin-top:10px">Servizi di partenza, uno per riga: <span class="muted">Nome | Descrizione | Minuti | Prezzo | Gruppo | Colore (facoltativo)</span>'
                . '<textarea name="services" rows="8" placeholder="Seduta di fisioterapia | Trattamento manuale | 45 | 50 | Trattamenti">' . $services . '</textarea></label>'
                . '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px"><label class="check" style="margin:0"><input type="checkbox" name="active" value="1"' . ($active ? ' checked' : '') . '>Attiva (compare nella registrazione e nella home)</label>'
                . '<button class="mini" name="azione" value="cat_save">' . ($id === 0 ? 'Aggiungi categoria' : 'Salva') . '</button>' . $del . '</div>'
                . '<p class="muted" style="margin:6px 0 0">I servizi vengono copiati nei negozi che si registrano da ora in poi; i negozi esistenti non cambiano.</p>'
                . '</form></details>';
        };
        $html = '';
        foreach ($cats as $key => $c) {
            $html .= $form((string)$key, $c);
        }
        $html .= $form('', ['id' => 0]);
        return '<details class="card" id="categorie" style="margin:0 0 18px"' . ($old !== [] ? ' open' : '') . '><summary style="cursor:pointer;font-weight:700">Categorie merceologiche (' . count($cats) . ')</summary>'
            . '<p class="sub" style="text-align:left;margin:8px 0 12px;font-size:14px">I tipi di attività tra cui si sceglie in registrazione. Ognuno precompila i servizi del nuovo negozio e i nomi usati nell\'app (per esempio <i>Meccanici</i> e <i>Interventi</i> per un\'officina). Una categoria disattivata sparisce dalle scelte ma resta ai negozi che la usano; se la elimini, scegli in quale categoria spostare i suoi negozi (servizi e dati non cambiano).</p>'
            . $html . '</details>';
    }
}
