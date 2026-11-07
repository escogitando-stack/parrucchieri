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
        'name'   => 'CPANELUSER_parrucchieri',
        'user'   => 'CPANELUSER_parrucchieri',
        'pass'   => 'PASSWORD_DATABASE',
    ],

    // Password per entrare nell'app (modo semplice): scrivila qui tra apici singoli.
    // Il file server/config.php non è raggiungibile dal web.
    'admin_password' => '',

    // Alternativa più sicura: lascia vuoto sopra e inserisci un hash generato con
    //   php -r 'echo password_hash("LA_TUA_PASSWORD", PASSWORD_DEFAULT), "\n";'
    'admin_password_hash' => '',

    // true = al primo avvio inserisce anche collaboratori, clienti e prodotti di esempio.
    'seed_demo' => false,

    'timezone' => 'Europe/Rome',
];
