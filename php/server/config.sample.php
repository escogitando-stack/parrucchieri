<?php
/**
 * Copia questo file in server/config.php (sul server, NON nel repository)
 * e compila i valori. server/config.php non viene mai sovrascritto dal deploy.
 */
return [
    // Database MySQL/MariaDB creato da cPanel → "Database MySQL".
    'db' => [
        'driver' => 'mysql',
        'host'   => 'localhost',
        'name'   => 'CPANELUSER_appuntamenti',
        'user'   => 'CPANELUSER_appuntamenti',
        'pass'   => 'PASSWORD_DATABASE',
    ],

    'site_name' => 'Appuntamenti4me',
    'timezone'  => 'Europe/Rome',

    // Indirizzo del sito (usato nei ritorni dal pagamento). Lascia vuoto per rilevarlo da solo.
    'base_url' => 'https://appuntamenti4me.it',

    // Giorni di prova gratuita dopo la registrazione.
    'trial_days' => 30,

    // Email degli amministratori: accedono al pannello /admin per gestire i negozi registrati.
    // Devono essere email di account già registrati. Gli amministratori non pagano l'abbonamento.
    'admin_emails' => [],   // es. ['tuaemail@esempio.it']

    // false = chiude le nuove registrazioni (chi ha già un account entra normalmente).
    // Accesso amministratore diretto: con questa email e password entri da /admin.
    // L'account viene creato al primo accesso. Il file config.php non è raggiungibile dal web.
    'admin_email' => '',
    'admin_password' => '',

    'allow_registration' => true,

    // Email di contatto mostrata nel sito e nella pagina /contatti.
    'contact_email' => 'info@appuntamenti4me.it',

    // Altri riferimenti per la pagina /contatti: compaiono solo se compilati.
    'contact' => [
        'name' => '',      // es. ragione sociale o nome
        'phone' => '',     // es. '+39 333 1234567'
        'whatsapp' => '',  // numero con prefisso, es. '393331234567'
        'address' => '',
        'vat' => '',       // partita IVA
    ],

    // Prezzi mostrati sul sito: sono solo testi, il prezzo vero si imposta in Stripe.
    'pricing' => [
        'monthly_label' => '19 € al mese',
        'yearly_label'  => '190 € all\'anno',
    ],

    // Pagine legali (obbligatorie se vendi a clienti in Italia/UE): se compilate compare
    // la casella "Accetto" nella registrazione e i link nel piè di pagina.
    'terms_url'   => '',
    'privacy_url' => '',

    // Pagamenti con Stripe. Senza queste chiavi il sito funziona ma l'acquisto è disattivato.
    // Vedi docs/DEPLOY.md → "Abbonamenti con Stripe".
    'stripe' => [
        'secret_key'     => '',   // sk_live_...
        'webhook_secret' => '',   // whsec_...
        'price_monthly'  => '',   // price_... (piano mensile)
        'price_yearly'   => '',   // price_... (piano annuale, facoltativo)
    ],
];
