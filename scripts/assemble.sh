#!/usr/bin/env bash
# Assembla la cartella pronta per il server (document root di cPanel):
# backend PHP + interfaccia compilata (dist/) rinominata in app.html.
#
# Uso:  pnpm build && scripts/assemble.sh <cartella-di-output>
set -euo pipefail

OUT="${1:?uso: scripts/assemble.sh <cartella-di-output>}"
cd "$(dirname "$0")/.."

[ -f dist/index.html ] || { echo "dist/ non trovato: esegui prima 'pnpm build'." >&2; exit 1; }

rm -rf "$OUT"
mkdir -p "$OUT"
cp -R php/. "$OUT"/
rm -rf "$OUT/tests"
rm -f "$OUT/server/config.php" "$OUT/server/.installed"

# L'interfaccia viene servita da index.php solo dopo il login, quindi non si chiama index.html.
cp dist/index.html "$OUT/app.html"
for f in dist/*; do
  [ "$(basename "$f")" = "index.html" ] || cp -R "$f" "$OUT"/
done

echo "Pronto in: $OUT"
