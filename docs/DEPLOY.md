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
   i dati del database (punto 3) e, se vuoi, `base_url`, `pricing` e le pagine legali. Il resto ha già valori adatti.
3. Apri **https://appuntamenti4me.it**: vedi la pagina di presentazione. Con **Prova gratis** crei il primo account.

## Come funziona (più negozi)

- Ogni negozio si registra da solo (nome attività, tipo di attività, email, password) e ha i propri dati,
  separati da tutti gli altri: clienti, collaboratori, servizi, prodotti e appuntamenti.
- Il tipo di attività (parrucchiere, barbiere, unghie, estetica, meccanico, tattoo, altro) precompila servizi e nomi
  (per esempio *Meccanici* e *Interventi* per un'officina). Si può cambiare da **Impostazioni** e ogni servizio è modificabile.
  Per aggiungere un'attività nuova basta una voce in `php/server/Categories.php`.
- Dopo la registrazione parte la **prova gratuita** (`trial_days`, 30 di default). Scaduta la prova, l'agenda si blocca
  e il titolare viene mandato alla pagina `/abbonamento`: i dati non si cancellano.
- **Aggiornamento da una versione precedente**: al primo accesso il database viene aggiornato da solo. I dati già
  presenti (clienti, appuntamenti...) passano al **primo account che si registra**; i successivi partono da zero.
- Per chiudere le nuove iscrizioni imposta `'allow_registration' => false` in `server/config.php`.
- Se qualcuno dimentica la password, per ora si può cambiare solo dalle Impostazioni (da dentro). Il recupero via email
  non c'è ancora: da phpMyAdmin si può sostituire `password_hash` della tabella `accounts` con un hash nuovo
  (`php -r 'echo password_hash("NUOVA", PASSWORD_DEFAULT);'` dal Terminale cPanel).

## Pannello amministratore (`/admin`)

Serve a gestire i negozi che si registrano. Lo vedono solo gli account la cui email è in `server/config.php`:

```php
'admin_emails' => ['tuaemail@esempio.it'],
```

L'email deve essere quella di un account già registrato. Poi, dal menu laterale dell'app, compare **Amministrazione**
(oppure apri `https://appuntamenti4me.it/admin`). Chi non è amministratore riceve "Non trovato".

Nella pagina vedi quanti negozi ci sono (in prova, abbonati, scaduti, sospesi, nuovi degli ultimi 7 giorni) e, per ognuno,
tipo di attività, stato del piano, data di iscrizione, ultimo accesso e quanti clienti e appuntamenti ha inserito.
Con la ricerca li trovi per nome o email, e puoi filtrarli per stato. Dal menu **Azioni** di ogni riga puoi:

- **+7 / +30 giorni**: prolunga la prova (per chi paga sposta il rinnovo).
- **Attiva fino al**: attiva a mano il negozio fino a una data (per esempio chi paga con bonifico).
- **Sospendi / Riattiva**: blocca o ripristina l'accesso senza cancellare nulla; chi è sospeso vede un messaggio.
- **Nuova password**: genera una password provvisoria da comunicare al cliente (si cambia poi dalle Impostazioni).
  È anche il modo per aiutare chi l'ha dimenticata.
- **Elimina account e dati**: cancella definitivamente il negozio e tutti i suoi dati. Non è possibile eliminare o sospendere il proprio account.

Gli amministratori usano l'agenda anche a prova scaduta (non pagano il proprio accesso).

## Abbonamenti con Stripe

Il sito usa Stripe Checkout (pagamento ospitato da Stripe) e il Portale clienti (cambio carta, fatture, disdetta).
Senza le chiavi l'acquisto resta disattivato e l'app funziona comunque.

1. Crea un account su stripe.com e, in *Prodotti*, un prodotto con un prezzo **ricorrente mensile** (e uno annuale, se vuoi).
   Copia gli ID dei prezzi (`price_...`).
2. In *Sviluppatori → Chiavi API* copia la **chiave segreta** (`sk_live_...`; per provare `sk_test_...`).
3. In *Sviluppatori → Webhook* aggiungi l'endpoint `https://appuntamenti4me.it/stripe-webhook` con gli eventi
   `checkout.session.completed`, `customer.subscription.created`, `customer.subscription.updated` e
   `customer.subscription.deleted`. Copia il **segreto di firma** (`whsec_...`).
4. In *Impostazioni → Billing → Portale clienti* attiva il portale (cambio metodo di pagamento, disdetta).
5. Inserisci le quattro voci nella sezione `stripe` di `server/config.php` e imposta i testi in `pricing`.
6. Prova prima in modalità test (carta `4242 4242 4242 4242`): registra un negozio di prova, vai su
   `/abbonamento`, paga e verifica che lo stato diventi *attivo*.

Da fare per vendere in Italia: pubblicare **Termini** e **Privacy** (poi inserire gli indirizzi in `terms_url`
e `privacy_url`) e controllare con il commercialista fatturazione e IVA.

## Alternativa più semplice: caricamento automatico via FTP in `public_html`

Ad ogni merge su `main` GitHub può caricare il sito direttamente in `public_html`, senza usare Git su cPanel.

1. In cPanel → *Account FTP* crea un account con directory `public_html` (o la cartella del dominio).
2. Su GitHub → *Settings → Secrets and variables → Actions → Secrets* crea:
   `FTP_SERVER` (il server FTP, es. `ftp.appuntamenti4me.it`), `FTP_USERNAME` e `FTP_PASSWORD`.
3. Facoltativo: nella scheda *Variables* crea `FTP_DIR` se la cartella non è `public_html/`.
4. In *Actions* avvia a mano **Build e pubblicazione** (*Run workflow*) oppure fai un nuovo merge.

Il caricamento aggiorna solo i file del sito: `server/config.php` (con le password) resta sul server e non viene toccato.
Attenzione: i file con lo stesso nome già presenti nella cartella (per esempio `index.php` e `.htaccess` di un altro sito) vengono sostituiti.

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


## Approvazione, pagina pubblica, prenotazioni e piani

- **Approvazione:** ogni nuova registrazione è "Da approvare". Ti arriva una email a `contact_email` (predefinito `info@appuntamenti4me.it`) con il link a `/admin`: premi **Approva** e parte la prova gratuita; il cliente riceve una email. Con `'require_approval' => false` si attivano subito.
- **Pagina pubblica:** dopo l'approvazione ogni negozio ha `https://appuntamenti4me.it/nome-negozio` (indirizzo modificabile da Impostazioni), con logo, descrizione, telefono, WhatsApp, indirizzo, orari e modulo di prenotazione.
- **Prenotazioni:** il cliente sceglie servizi, giorno e ora tra quelli liberi (orari di lavoro per collaboratore, da Impostazioni) oppure invia una richiesta "fuori disponibilità". La richiesta è "Da approvare": il titolare approva o rifiuta dal backoffice, e il cliente riceve una email (con link per annullare). Il messaggio WhatsApp è un link già scritto che il titolare invia a mano.
- **Email:** usano `mail()` del server. Imposta `'mail' => ['from' => 'no-reply@tuodominio.it']` con un indirizzo del tuo dominio e, se possibile, i record SPF/DKIM da cPanel per evitare lo spam. Se le email non partono, le richieste arrivano comunque nel backoffice.
- **Piani:** da `/admin` → "Piani di abbonamento" crei piani con nome, durata in giorni e prezzo. I piani attivi compaiono nella home e nella pagina Abbonamento; con il codice `price_...` di Stripe il cliente paga online, senza codice li assegni tu da "Azioni → Assegna piano".
- **Amministratore:** `admin_email` + `admin_password` (oppure `admin_emails`) nel `config.php`; il suo account non scade mai.
- Aggiornamento database: al primo accesso dopo il caricamento lo schema passa alla versione 6 da solo. Fai una copia del database prima.

## Conferma automatica, link nelle email, privacy (schema 7)

- **Conferma immediata**: in *Servizi* ogni servizio prenotabile online ha l'interruttore "Conferma immediata / Da approvare". Se tutti i servizi scelti dal cliente sono a conferma immediata e l'orario è tra quelli liberi, la prenotazione è subito confermata (email al cliente con calendario e annullamento, avviso al titolare). Le richieste "fuori disponibilità" restano sempre da approvare.
- **Approva dall'email**: la mail che ricevi per ogni richiesta ha il pulsante "Approva o rifiuta" (link firmato con `secret` di `config.php`): si apre una pagina con due pulsanti, senza accedere. Se a quell'ora non c'è nessun collaboratore libero, il link chiede di aprire il pannello.
- **Telefono o email**: il cliente deve lasciare almeno un recapito. Senza email non riceve messaggi automatici: scrivigli dal pulsante WhatsApp.
- **Privacy**: il modulo ha il consenso obbligatorio e il link a `/nome/privacy`, un'informativa generata dal nome e dai contatti del negozio. È un modello: fai verificare il testo prima di usarlo con clienti reali.
- **Calendario**: il link `/calendario/<codice>.ics` nelle email aggiunge l'appuntamento a Google/Apple Calendar.
- Aggiorna il database (migrazione automatica alla prima visita): nuove colonne e tabella `password_resets`.

## Pagina cliente (gruppo 2)

- **Primo orario libero**: scelti i servizi, la pagina propone subito il primo giorno e orario disponibile ("Scegli questo").
- **Riepilogo**: prima dei dati si vede servizi, durata e totale. Su telefono una barra fissa in basso mostra il riepilogo e il pulsante "Prenota/Continua".
- **Sposta la prenotazione**: le email ai clienti hanno il link `/sposta/<codice>` (e la pagina di annullamento lo propone). Se i servizi sono a conferma immediata resta confermata, altrimenti torna "da approvare" e ti arriva l'email con il pulsante Approva/Rifiuta.

## Email automatiche e promemoria (gruppo 3)

Serve **un cron ogni ora** (cPanel → *Operazioni cron* → "Una volta all'ora"):

```
php /home/UTENTE/public_html/cron.php
```

(adatta il percorso a quello della tua cartella; è lo stesso di `index.php`). Se il tuo hosting permette solo di aprire un indirizzo, metti `'cron_key' => 'una-chiave'` in `config.php` e usa `curl -s "https://appuntamenti4me.it/cron.php?key=una-chiave"`.

Cosa invia, una volta sola per evento:
- **Promemoria al cliente** il giorno prima (dalle 17, `reminder_hour`), con link per spostare o annullare. Solo prenotazioni confermate con email.
- **Riepilogo serale al titolare** (dalle 20, `summary_hour`) con gli appuntamenti di domani e le richieste da approvare; non parte se non c'è nulla. Si disattiva dalle Impostazioni.
- **Prova gratuita**: avviso a 7 giorni e a 2 giorni dalla fine, e un messaggio alla scadenza con il link all'abbonamento.
- **Configurazione**: dopo 2 giorni, se mancano telefono, indirizzo, logo o descrizione, un'email con cosa completare.

**SMTP**: in `config.php` → `mail` puoi usare una casella del tuo dominio (`driver`, `host`, `port`, `secure`, `user`, `pass`, vedi `config.sample.php`). Le email arrivano più spesso in posta in arrivo e non nello spam. Se l'invio non riesce l'errore finisce nel log del server.

## Strumenti del titolare (gruppo 4)

- **Dashboard**: le richieste da approvare compaiono in cima con i pulsanti *Conferma / Rifiuta* (se il cliente non ha email, appare il link WhatsApp già scritto). Sotto trovi "Completa la tua pagina" (checklist) e "Fai prenotare i tuoi clienti" con link da copiare, condivisione WhatsApp e **codice QR** (`/nome/qr.svg`) con cartello da stampare. Le statistiche degli ultimi 30 giorni contano completati, annullati, non presentati e prenotazioni online.
- **Ferie e chiusure** (Impostazioni): uno o più giorni in cui la pagina non offre orari. Gli appuntamenti già presi restano, con un avviso. Vale per i collaboratori attivi al momento della chiusura.
- **Esporta CSV**: pulsante in *Appuntamenti*; il file si apre con Excel o Fogli Google (da 90 giorni fa a un anno avanti).
- **Riepilogo serale**: si può disattivare dalle Impostazioni.
