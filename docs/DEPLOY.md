# Pubblicare su appuntamenti4me.it (cPanel)

L'app gira su **PHP 8.1+ e MySQL/MariaDB** (come gli altri siti su cPanel). Il codice TypeScript/Hono
originale resta nel repository, ma per il dominio si usa il backend in `php/`.

Come funziona il deploy:

1. fai merge su `main` →
2. GitHub Actions compila l'interfaccia, prova l'API e pubblica il sito già pronto sul branch **`deploy`** →
3. cPanel scarica il branch `deploy` e copia i file nella cartella del dominio.

`server/config.php` (password del database e dell'app) vive solo sul server e il deploy non lo tocca.

## Una tantum su cPanel

1. **Dominio**: in *Domini* aggiungi `appuntamenti4me.it` (o verifica che esista) e annota la cartella radice,
   per esempio `/home/NOMEUTENTE/appuntamenti4me.it`. Attiva **AutoSSL** per avere l'HTTPS.
2. **PHP**: in *MultiPHP Manager* imposta il dominio su **PHP 8.1 o superiore**
   (estensioni `pdo_mysql` e `mbstring`, di solito già attive).
3. **Database**: in *Database MySQL* crea un database, un utente e associali con *Tutti i privilegi*.
   Le tabelle si creano da sole alla prima apertura.
4. **Git**: in *Git™ Version Control* → *Crea*:
   - Clone URL: `https://github.com/escogitando-stack/parrucchieri.git`
   - Repository Path: per esempio `/home/NOMEUTENTE/repositories/parrucchieri`
   - dopo la creazione, apri *Gestisci* → *Basic Information* e scegli il branch **`deploy`**.

## Su GitHub

In *Settings → Secrets and variables → Actions → Variables* crea la variabile
**`CPANEL_DEPLOYPATH`** con la cartella del dominio (stessa del punto 1), per esempio
`/home/NOMEUTENTE/appuntamenti4me.it`.

Poi fai il merge del branch su `main`. Quando l'azione è verde il branch `deploy` esiste.

## Primo avvio

1. In cPanel → *Git Version Control* → *Gestisci* → **Update from Remote**, poi **Deploy HEAD Commit**.
2. In *File Manager*, nella cartella del dominio, copia `server/config.sample.php` in `server/config.php` e compilalo:
   - dati del database (punto 3),
   - `admin_password_hash`: dal *Terminale* di cPanel esegui
     `php -r 'echo password_hash("LA_TUA_PASSWORD", PASSWORD_DEFAULT), "\n";'` e incolla il risultato.
3. Apri **https://appuntamenti4me.it** e accedi con la password scelta.

Alla prima apertura vengono creati gli otto servizi tipici da parrucchiere e un collaboratore "Titolare":
modificali dall'app. Con `'seed_demo' => true` si aggiungono anche clienti, collaboratori e prodotti di esempio.

## Aggiornamenti successivi

Ad ogni merge su `main` il branch `deploy` si aggiorna. Su cPanel basta *Update from Remote* + *Deploy HEAD Commit*.
Per farlo in automatico aggiungi questi **secrets** su GitHub (l'azione usa l'API di cPanel):
`CPANEL_HOST`, `CPANEL_USER`, `CPANEL_TOKEN` (token API da *Gestisci token API*), `CPANEL_REPO_ROOT`
(il Repository Path del punto 4). Questo passaggio è facoltativo.

## Sicurezza

- Tutto l'accesso è dietro password (sessione di 30 giorni, cookie `HttpOnly`/`Secure`).
- Le scritture accettano solo JSON dello stesso sito; la cartella `server/` non è raggiungibile dal web.
- Fai un backup periodico del database da cPanel (*Backup*).

## Provare in locale (senza cPanel)

```bash
pnpm install && pnpm build
scripts/assemble.sh /tmp/parrucchieri-site
cat > /tmp/parrucchieri-site/server/config.php <<'PHP'
<?php return [
  'db' => ['driver' => 'sqlite', 'path' => '/tmp/parrucchieri-site/prova.db'],
  'admin_password_hash' => password_hash('prova', PASSWORD_DEFAULT),
  'seed_demo' => true,
];
PHP
php -S localhost:8080 -t /tmp/parrucchieri-site scripts/dev-router.php
```

Poi apri http://localhost:8080 (password `prova`). I test dell'API: `php php/tests/run.php`.
