#!/usr/bin/env bash
# Prova la demo /esempio sul sito già assemblato, con PHP integrato e SQLite,
# e salva gli screenshot. Uso: scripts/demo-smoke.sh <cartella-sito> <cartella-screenshot>
set -euo pipefail
SITE="${1:?cartella del sito}"; OUT="${2:?cartella screenshot}"
mkdir -p "$OUT"
cat > "$SITE/server/config.php" <<'PHP'
<?php
return ['db' => ['driver' => 'sqlite', 'path' => __DIR__ . '/prova.sqlite'], 'site_name' => 'Appuntamenti4me', 'timezone' => 'Europe/Rome', 'base_url' => 'http://127.0.0.1:8099', 'trial_days' => 30];
PHP
# Router per il server integrato: fa quello che fa .htaccess.
cat > "$SITE/_router.php" <<'PHP'
<?php
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(esempio/)?api(/|$)#', $p)) { require __DIR__ . '/api.php'; return true; }
if ($p !== '/' && is_file(__DIR__ . $p)) { return false; }
require __DIR__ . '/index.php';
PHP
(cd "$SITE" && php -S 127.0.0.1:8099 _router.php > "$OUT/server.log" 2>&1 &)
sleep 2
J="$OUT/cookies.txt"
code() { curl -s -o /dev/null -w '%{http_code}' -b "$J" -c "$J" "$@"; }
fail=0
check() { if [ "$2" = "$3" ]; then echo "ok   $1"; else echo "FAIL $1 (atteso $3, avuto $2)"; fail=1; fi; }
check "home" "$(code http://127.0.0.1:8099/)" 200
curl -s http://127.0.0.1:8099/ | grep -q 'href="/esempio">Vedi un esempio' && echo "ok   pulsante in home" || { echo "FAIL pulsante in home"; fail=1; }
check "api demo senza sessione" "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8099/esempio/api/stats)" 401
check "pagina /esempio" "$(code http://127.0.0.1:8099/esempio)" 200
curl -s -b "$J" http://127.0.0.1:8099/esempio | grep -q 'A4M_DEMO' && echo "ok   modalità demo accesa" || { echo "FAIL modalità demo"; fail=1; }
check "api demo con sessione" "$(code http://127.0.0.1:8099/esempio/api/stats)" 200
curl -s -b "$J" http://127.0.0.1:8099/esempio/api/stats; echo
check "api vera non toccata dalla demo" "$(code http://127.0.0.1:8099/api/stats)" 401
check "pagina negozio demo" "$(code http://127.0.0.1:8099/esempio/negozio)" 200
curl -s -b "$J" http://127.0.0.1:8099/esempio/negozio > "$OUT/negozio.html"
grep -q "var BASE='/esempio/negozio'" "$OUT/negozio.html" && echo "ok   prenotazione sul negozio demo" || { echo "FAIL base negozio demo"; fail=1; }
check "rotta demo sconosciuta" "$(code http://127.0.0.1:8099/esempio/negozio/boh)" 404
# Screenshot con Chrome: un profilo condiviso tiene il cookie, quindi tutte le pagine sono della stessa demo.
CH=$(command -v google-chrome || command -v chromium || true)
if [ -n "$CH" ]; then
  for page in "esempio" "esempio/calendar" "esempio/clients" "esempio/negozio" ""; do
    name=$(echo "${page:-home}" | tr '/' '-')
    "$CH" --user-data-dir="$OUT/profilo" --headless=new --no-sandbox --disable-gpu --hide-scrollbars --window-size=1366,900 --virtual-time-budget=6000 --screenshot="$OUT/$name.png" "http://127.0.0.1:8099/$page" > /dev/null 2>&1 || true
    "$CH" --user-data-dir="$OUT/profilo" --headless=new --no-sandbox --disable-gpu --hide-scrollbars --window-size=390,844 --virtual-time-budget=6000 --screenshot="$OUT/$name-mobile.png" "http://127.0.0.1:8099/$page" > /dev/null 2>&1 || true
  done
fi
rm -rf "$OUT/profilo"
grep -i -E "fatal|warning|error" "$OUT/server.log" | grep -v " 40[14] " | head -20 || true
exit $fail
