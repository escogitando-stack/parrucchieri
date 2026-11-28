<?php
declare(strict_types=1);

namespace App;

/** Testi delle email (e del messaggio WhatsApp) di registrazione e prenotazione. */
final class Notify
{
    private const DAYS = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
    private const MONTHS = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

    /** @param array<string,mixed> $cfg */
    public static function baseUrl(array $cfg): string
    {
        if (!empty($cfg['base_url'])) {
            return rtrim((string)$cfg['base_url'], '/');
        }
        return (Auth::isHttps() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    /** Chiave per firmare i link inviati per email (da config 'secret', altrimenti ricavata dalla configurazione). @param array<string,mixed> $cfg */
    public static function secret(array $cfg): string
    {
        if (!empty($cfg['secret'])) {
            return (string)$cfg['secret'];
        }
        return hash('sha256', 'a4m|' . ($cfg['db']['pass'] ?? '') . '|' . ($cfg['db']['name'] ?? '') . '|' . ($cfg['admin_password'] ?? '') . '|' . ($cfg['db']['path'] ?? ''));
    }

    /** Firma di 32 caratteri per un testo. @param array<string,mixed> $cfg */
    public static function sign(array $cfg, string $what): string
    {
        return substr(hash_hmac('sha256', $what, self::secret($cfg)), 0, 32);
    }

    /** @param array<string,mixed> $cfg */
    public static function verify(array $cfg, string $what, string $sig): bool
    {
        return $sig !== '' && hash_equals(self::sign($cfg, $what), $sig);
    }

    /** Link per approvare o rifiutare una richiesta senza entrare nel pannello. @param array<string,mixed> $cfg */
    public static function manageUrl(array $cfg, string $token): string
    {
        return self::baseUrl($cfg) . '/gestisci/' . $token . '/' . self::sign($cfg, 'gestisci|' . $token);
    }

    /** Link per approvare un nuovo negozio dall'email dell'amministratore. @param array<string,mixed> $cfg */
    public static function approveUrl(array $cfg, int $accountId): string
    {
        return self::baseUrl($cfg) . '/approva/' . $accountId . '/' . self::sign($cfg, 'approva|' . $accountId);
    }

    /** Link per reimpostare la password. @param array<string,mixed> $cfg @param array<string,mixed> $acct */
    public static function passwordReset(array $cfg, array $acct, string $token): bool
    {
        $text = "Hai chiesto di reimpostare la password. Il link vale un'ora.\n\nSe non sei stato tu, ignora questo messaggio: la tua password non cambia.";
        return self::mail($cfg, (string)$acct['email'], 'Reimposta la tua password', $text, 'Scegli una nuova password', self::baseUrl($cfg) . '/reimposta/' . $token, Pages::contactEmail($cfg));
    }

    /** Il giorno dopo un appuntamento completato: com'è andata? Lascia una recensione. */
    public static function reviewRequest(array $cfg, array $acct, array $client): bool
    {
        $text = 'Ciao ' . $client['name'] . ', grazie per essere passato da ' . $acct['business_name'] . "!\n\n"
            . "Com'è andata? Se ti sei trovato bene, una tua recensione ci aiuta tantissimo: bastano trenta secondi.";
        return self::mail($cfg, (string)$client['email'], "Com'è andata da " . $acct['business_name'] . '?', $text, 'Lascia una recensione', (string)$acct['review_url'], self::ownerEmail($acct), (string)$acct['business_name']);
    }

    /** Consegna di una demo al cliente: il suo account è pronto, sceglie la password dal link (valido 7 giorni). */
    public static function accountDelivered(array $cfg, array $acct, string $token): bool
    {
        $text = 'Ciao! L\'account di ' . $acct['business_name'] . ' è pronto: agenda, servizi e pagina di prenotazione sono già impostati.'
            . "\n\nScegli la tua password dal pulsante qui sotto (il link vale 7 giorni), poi accedi con questa email."
            . "\n\nLa tua pagina di prenotazione: " . self::shopUrl($cfg, $acct);
        return self::mail($cfg, (string)$acct['email'], 'Il tuo account è pronto', $text, 'Scegli la password e accedi', self::baseUrl($cfg) . '/reimposta/' . $token, Pages::contactEmail($cfg));
    }

    /** @param array<string,mixed> $cfg */
    public static function icsUrl(array $cfg, string $token): string
    {
        return self::baseUrl($cfg) . '/calendario/' . $token . '.ics';
    }

    /** "2026-11-07" → "sabato 7 novembre 2026". */
    public static function dateIt(string $date): string
    {
        $ts = strtotime($date . ' 12:00:00');
        if ($ts === false) {
            return $date;
        }
        return self::DAYS[(int)date('w', $ts)] . ' ' . (int)date('j', $ts) . ' ' . self::MONTHS[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    }

    /** @param array<string,mixed> $acct */
    public static function shopUrl(array $cfg, array $acct): string
    {
        return self::baseUrl($cfg) . '/' . $acct['slug'];
    }

    private static function html(string $title, string $text, string $buttonLabel = '', string $buttonUrl = '', string $extraHtml = ''): string
    {
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $paras = '';
        foreach (preg_split('/\n{2,}/', trim($text)) ?: [] as $p) {
            $paras .= '<p style="margin:0 0 14px;line-height:1.5">' . nl2br($h($p)) . '</p>';
        }
        $btn = $buttonUrl !== '' ? '<p style="margin:22px 0"><a href="' . $h($buttonUrl) . '" style="background:#be185d;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;display:inline-block">' . $h($buttonLabel) . '</a></p>' : '';
        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#1c1917;max-width:560px;margin:0 auto;padding:20px">'
            . '<h2 style="margin:0 0 16px;font-size:20px">' . $h($title) . '</h2>' . $paras . $extraHtml . $btn . '</div>';
    }

    /** @param array<string,mixed> $cfg */
    private static function mail(array $cfg, string $to, string $subject, string $text, string $btnLabel = '', string $btnUrl = '', string $replyTo = '', string $fromName = '', string $extraHtml = ''): bool
    {
        $plain = $text . ($btnUrl !== '' ? "\n\n$btnLabel: $btnUrl" : '');
        return Mailer::send($cfg, $to, $subject, $plain, self::html($subject, $text, $btnLabel, $btnUrl, $extraHtml), $replyTo, $fromName);
    }

    /** Un nuovo negozio si è registrato: avvisa l'amministratore. @param array<string,mixed> $cfg @param array<string,mixed> $acct */
    public static function newRegistration(array $cfg, array $acct): bool
    {
        $text = "Si è registrato un nuovo negozio e aspetta la tua approvazione.\n\n"
            . 'Attività: ' . $acct['business_name'] . "\n"
            . 'Tipo: ' . Categories::get((string)$acct['category'])['label'] . "\n"
            . 'Email: ' . $acct['email'] . "\n"
            . 'Indirizzo pagina: ' . self::shopUrl($cfg, $acct);
        $text .= "\n\nCon il pulsante approvi subito, senza accedere. Oppure usa il pannello: " . self::baseUrl($cfg) . '/admin';
        return self::mail($cfg, Pages::contactEmail($cfg), 'Nuova registrazione: ' . $acct['business_name'], $text, 'Approva ora', self::approveUrl($cfg, (int)$acct['id']), (string)$acct['email']);
    }

    /** Il cliente ha ricevuto l'approvazione. @param array<string,mixed> $cfg @param array<string,mixed> $acct */
    public static function accountApproved(array $cfg, array $acct): bool
    {
        $days = (int)($cfg['trial_days'] ?? 30);
        $text = 'Ciao, la tua registrazione è stata approvata: puoi entrare e iniziare la prova gratuita di ' . $days . " giorni.\n\n"
            . 'La pagina dove i tuoi clienti possono prenotare è: ' . self::shopUrl($cfg, $acct) . "\n"
            . 'Dalle Impostazioni puoi aggiungere logo, telefono, orari e descrizione.';
        return self::mail($cfg, (string)$acct['email'], 'Registrazione approvata', $text, 'Accedi', self::baseUrl($cfg) . '/login', Pages::contactEmail($cfg));
    }

    /** @param array<string,mixed> $cfg @param array<string,mixed> $acct @param array<string,mixed> $apt */
    private static function summary(array $acct, array $apt, string $services): string
    {
        return $acct['business_name'] . "\n"
            . self::dateIt((string)$apt['scheduled_date']) . ' alle ' . $apt['start_time'] . "\n"
            . ($services !== '' ? 'Servizi: ' . $services . "\n" : '')
            . (!empty($apt['staff_name']) ? 'Con: ' . $apt['staff_name'] . "\n" : '');
    }

    /** @param array<string,mixed> $client */
    private static function contactLines(array $client, array $apt): string
    {
        return 'Cliente: ' . $client['name'] . "\nTelefono: " . (($client['phone'] ?? '') !== '' ? $client['phone'] : '-') . (!empty($client['email']) ? "\nEmail: " . $client['email'] : '')
            . (!empty($apt['notes']) ? "\nNote: " . $apt['notes'] : '');
    }

    /** Richiesta ricevuta: email al cliente e al titolare (con il link per rispondere). @param array<string,mixed> $cfg @param array<string,mixed> $acct @param array<string,mixed> $apt @param array<string,mixed> $client */
    public static function requestReceived(array $cfg, array $acct, array $apt, array $client, string $services): void
    {
        $cancel = self::baseUrl($cfg) . '/annulla/' . $apt['public_token'];
        $replyTo = self::ownerEmail($acct);
        if (!empty($client['email'])) {
            $text = 'Ciao ' . $client['name'] . ", abbiamo ricevuto la tua richiesta di prenotazione.\n\n" . self::summary($acct, $apt, $services)
                . "\nLa richiesta deve essere confermata dal negozio: ti scriveremo appena possibile.\nPer cambiare giorno o orario: " . self::baseUrl($cfg) . '/sposta/' . $apt['public_token'] . "\nSe non puoi più venire, puoi annullarla qui sotto.";
            self::mail($cfg, (string)$client['email'], 'Richiesta di prenotazione ricevuta', $text, 'Annulla la richiesta', $cancel, $replyTo, (string)$acct['business_name']);
        }
        $text = "Nuova richiesta di prenotazione da approvare.\n\n" . self::summary($acct, $apt, $services) . self::contactLines($client, $apt)
            . "\n\nPuoi rispondere con un clic dal pulsante qui sotto, senza accedere al pannello.";
        self::mail($cfg, $replyTo, 'Nuova richiesta di prenotazione', $text, 'Approva o rifiuta', self::manageUrl($cfg, (string)$apt['public_token']), !empty($client['email']) ? (string)$client['email'] : '');
    }

    /** Prenotazione confermata subito (servizi a conferma automatica): email al cliente e avviso al titolare. @param array<string,mixed> $cfg @param array<string,mixed> $acct @param array<string,mixed> $apt @param array<string,mixed> $client */
    public static function bookingConfirmed(array $cfg, array $acct, array $apt, array $client, string $services): void
    {
        $replyTo = self::ownerEmail($acct);
        if (!empty($client['email'])) {
            $text = 'Ciao ' . $client['name'] . ", la tua prenotazione è confermata.\n\n" . self::summary($acct, $apt, $services)
                . (!empty($acct['shop_phone']) ? "\nPer informazioni: " . $acct['shop_phone'] : '')
                . "\n\nAggiungila al calendario: " . self::icsUrl($cfg, (string)$apt['public_token'])
                . "\nSe devi cambiare orario: " . self::baseUrl($cfg) . '/sposta/' . $apt['public_token'];
            self::mail($cfg, (string)$client['email'], 'Prenotazione confermata', $text, 'Annulla la prenotazione', self::baseUrl($cfg) . '/annulla/' . $apt['public_token'], $replyTo, (string)$acct['business_name']);
        }
        $text = "Nuova prenotazione confermata automaticamente.\n\n" . self::summary($acct, $apt, $services) . self::contactLines($client, $apt);
        self::mail($cfg, $replyTo, 'Nuova prenotazione: ' . $client['name'], $text, 'Apri il calendario', self::baseUrl($cfg) . '/calendar', !empty($client['email']) ? (string)$client['email'] : '');
    }

    /** Il cliente ha spostato la prenotazione. @param array<string,mixed> $cfg @param array<string,mixed> $acct @param array<string,mixed> $apt @param array<string,mixed> $client */
    public static function rescheduled(array $cfg, array $acct, array $apt, array $client, string $services, bool $confirmed, string $oldWhen): void
    {
        $replyTo = self::ownerEmail($acct);
        $token = (string)$apt['public_token'];
        if (!empty($client['email'])) {
            $text = 'Ciao ' . $client['name'] . ', ' . ($confirmed ? 'la tua prenotazione è stata spostata ed è confermata' : 'abbiamo ricevuto la richiesta di spostamento: il negozio la deve confermare') . ".\n\n" . self::summary($acct, $apt, $services)
                . "\n(Prima era: $oldWhen)"
                . ($confirmed ? "\n\nAggiungila al calendario: " . self::icsUrl($cfg, $token) : '')
                . "\nPer cambiare ancora: " . self::baseUrl($cfg) . '/sposta/' . $token;
            self::mail($cfg, (string)$client['email'], $confirmed ? 'Prenotazione spostata' : 'Richiesta di spostamento ricevuta', $text, 'Annulla la prenotazione', self::baseUrl($cfg) . '/annulla/' . $token, $replyTo, (string)$acct['business_name']);
        }
        $text = $client['name'] . ' ha spostato la prenotazione' . ($confirmed ? ' (confermata automaticamente)' : ': ora è da approvare') . ".\n\n" . self::summary($acct, $apt, $services) . "(Prima era: $oldWhen)\n" . self::contactLines($client, $apt);
        self::mail(
            $cfg,
            $replyTo,
            'Prenotazione spostata: ' . $client['name'],
            $text,
            $confirmed ? 'Apri il calendario' : 'Approva o rifiuta',
            $confirmed ? self::baseUrl($cfg) . '/calendar' : self::manageUrl($cfg, $token),
            !empty($client['email']) ? (string)$client['email'] : ''
        );
    }

    /** Esito deciso dal titolare. @param array<string,mixed> $apt @param array<string,mixed> $client */
    public static function decision(array $cfg, array $acct, array $apt, array $client, string $services, bool $approved): bool
    {
        if (empty($client['email'])) {
            return false;
        }
        $text = 'Ciao ' . $client['name'] . ($approved ? ", la tua prenotazione è confermata.\n\n" : ", purtroppo non riusciamo a confermare la richiesta.\n\n")
            . self::summary($acct, $apt, $services)
            . ($approved ? '' : "\nPuoi scegliere un altro giorno o orario dalla nostra pagina.\n")
            . (!empty($acct['shop_phone']) ? "\nPer informazioni: " . $acct['shop_phone'] : '')
            . ($approved && !empty($apt['public_token']) ? "\n\nAggiungila al calendario: " . self::icsUrl($cfg, (string)$apt['public_token']) : '');
        return self::mail(
            $cfg,
            (string)$client['email'],
            $approved ? 'Prenotazione confermata' : 'Richiesta non confermata',
            $text,
            $approved ? '' : 'Scegli un altro orario',
            $approved ? '' : self::shopUrl($cfg, $acct),
            self::ownerEmail($acct),
            (string)$acct['business_name']
        );
    }

    /** Il cliente ha annullato dal link. @param array<string,mixed> $apt @param array<string,mixed> $client */
    public static function cancelledByCustomer(array $cfg, array $acct, array $apt, array $client, string $services): void
    {
        $text = $client['name'] . " ha annullato la prenotazione.\n\n" . self::summary($acct, $apt, $services);
        self::mail($cfg, self::ownerEmail($acct), 'Prenotazione annullata dal cliente', $text);
    }

    /** @param array<string,mixed> $acct */
    public static function ownerEmail(array $acct): string
    {
        return !empty($acct['shop_email']) ? (string)$acct['shop_email'] : (string)$acct['email'];
    }

    /** Numero → solo cifre con prefisso (Italia se manca). */
    public static function digits(string $phone, string $country = '39'): string
    {
        $d = preg_replace('/\D/', '', $phone) ?? '';
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        } elseif (str_starts_with($d, '3') && strlen($d) <= 10) {
            $d = $country . $d;
        } elseif (str_starts_with($d, '0')) {
            $d = $country . $d;
        }
        return $d;
    }

    /** Link WhatsApp con messaggio già scritto, da inviare a mano dal titolare. @param array<string,mixed> $acct @param array<string,mixed> $apt */
    public static function whatsappUrl(array $acct, array $apt, string $clientName, string $clientPhone, bool $approved): string
    {
        $num = self::digits($clientPhone);
        if (strlen($num) < 8) {
            return '';
        }
        $when = self::dateIt((string)$apt['scheduled_date']) . ' alle ' . $apt['start_time'];
        $msg = $approved
            ? "Ciao $clientName, la tua prenotazione da {$acct['business_name']} è confermata: $when. A presto!"
            : "Ciao $clientName, purtroppo non riusciamo a confermare la prenotazione di $when da {$acct['business_name']}. Vuoi scegliere un altro orario?";
        return 'https://wa.me/' . $num . '?text=' . rawurlencode($msg);
    }

    /** Promemoria del giorno prima. @param array<string,mixed> $cfg @param array<string,mixed> $acct @param array<string,mixed> $apt @param array<string,mixed> $client */
    public static function reminder(array $cfg, array $acct, array $apt, array $client, string $services): bool
    {
        $token = (string)$apt['public_token'];
        $text = 'Ciao ' . $client['name'] . ", ti ricordiamo la tua prenotazione di domani.\n\n" . self::summary($acct, $apt, $services)
            . (!empty($acct['shop_address']) ? 'Dove: ' . $acct['shop_address'] . "\n" : '')
            . (!empty($acct['shop_phone']) ? 'Telefono: ' . $acct['shop_phone'] . "\n" : '')
            . ($token !== '' ? "\nSe devi cambiare orario: " . self::baseUrl($cfg) . '/sposta/' . $token : '');
        return self::mail($cfg, (string)$client['email'], 'Promemoria: domani alle ' . $apt['start_time'], $text, $token !== '' ? 'Annulla la prenotazione' : '', $token !== '' ? self::baseUrl($cfg) . '/annulla/' . $token : '', self::ownerEmail($acct), (string)$acct['business_name']);
    }

    /**
     * Riepilogo serale al titolare: appuntamenti di domani e richieste da approvare.
     *
     * @param array<string,mixed> $cfg @param array<string,mixed> $acct
     * @param array<int,array<string,mixed>> $rows appuntamenti di domani (start_time, client_name, services, staff_name)
     */
    public static function dailySummary(array $cfg, array $acct, string $tomorrow, array $rows, int $pending): bool
    {
        $lines = [];
        foreach ($rows as $r) {
            $lines[] = $r['start_time'] . ' - ' . $r['client_name'] . (($r['services'] ?? '') !== '' ? ' (' . $r['services'] . ')' : '') . (!empty($r['staff_name']) ? ' con ' . $r['staff_name'] : '');
        }
        $text = 'Domani, ' . self::dateIt($tomorrow) . ($rows === [] ? ": nessun appuntamento.\n" : ' - ' . count($rows) . (count($rows) === 1 ? " appuntamento:\n\n" : " appuntamenti:\n\n") . implode("\n", $lines) . "\n")
            . ($pending > 0 ? "\nHai " . $pending . ($pending === 1 ? ' richiesta' : ' richieste') . " in attesa di risposta." : '');
        $subject = 'Domani: ' . count($rows) . (count($rows) === 1 ? ' appuntamento' : ' appuntamenti') . ($pending > 0 ? ' e ' . $pending . ' da approvare' : '');
        // Promemoria WhatsApp con un tocco, per i clienti che hanno lasciato il telefono.
        $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $wa = '';
        foreach ($rows as $r) {
            $url = self::reminderWhatsappUrl($acct, $tomorrow, (string)$r['start_time'], (string)$r['client_name'], (string)($r['client_phone'] ?? ''));
            if ($url !== '') {
                $wa .= '<tr><td style="padding:6px 0">' . $h((string)$r['start_time'] . ' - ' . $r['client_name']) . '</td><td style="padding:6px 0;text-align:right"><a href="' . $h($url) . '" style="background:#25d366;color:#fff;text-decoration:none;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600;display:inline-block">Promemoria WhatsApp</a></td></tr>';
            }
        }
        $extra = $wa !== '' ? '<p style="margin:18px 0 6px;font-weight:600">Vuoi ricordarlo anche su WhatsApp? Tocca il pulsante: il messaggio è già scritto.</p><table style="width:100%;border-collapse:collapse;font-size:14px">' . $wa . '</table>' : '';
        return self::mail($cfg, self::ownerEmail($acct), $subject, $text, 'Apri il calendario', self::baseUrl($cfg) . '/calendar', '', '', $extra);
    }

    /** Link wa.me con il promemoria dell'appuntamento di domani, pronto da inviare. @param array<string,mixed> $acct */
    public static function reminderWhatsappUrl(array $acct, string $date, string $time, string $clientName, string $clientPhone): string
    {
        $num = self::digits($clientPhone);
        if (strlen($num) < 8) {
            return '';
        }
        $msg = "Ciao $clientName, ti ricordiamo l'appuntamento di domani, " . self::dateIt($date) . " alle $time, da {$acct['business_name']}"
            . (!empty($acct['shop_address']) ? " ({$acct['shop_address']})" : '') . '. Se hai un imprevisto avvisaci. A presto!';
        return 'https://wa.me/' . $num . '?text=' . rawurlencode($msg);
    }

    /** La prova gratuita sta per finire. @param array<string,mixed> $cfg @param array<string,mixed> $acct */
    public static function trialEnding(array $cfg, array $acct, int $daysLeft): bool
    {
        $text = 'Ciao, la tua prova gratuita di ' . ($cfg['site_name'] ?? 'Appuntamenti4me') . ' termina ' . ($daysLeft <= 1 ? 'domani' : 'tra ' . $daysLeft . ' giorni') . ".\n\n"
            . 'Per continuare a ricevere prenotazioni dalla tua pagina (' . self::shopUrl($cfg, $acct) . ') scegli un abbonamento: i tuoi dati restano al sicuro e non si perde nulla.';
        return self::mail($cfg, (string)$acct['email'], 'La tua prova gratuita sta per finire', $text, 'Scegli l\'abbonamento', self::baseUrl($cfg) . '/abbonamento', Pages::contactEmail($cfg));
    }

    /** La prova è finita. @param array<string,mixed> $cfg @param array<string,mixed> $acct */
    public static function trialExpired(array $cfg, array $acct): bool
    {
        $text = "Ciao, la tua prova gratuita è terminata e la pagina di prenotazione è sospesa.\n\nI tuoi dati sono conservati: attivando un abbonamento riparte tutto da dove eri rimasto.";
        return self::mail($cfg, (string)$acct['email'], 'Prova gratuita terminata', $text, 'Attiva l\'abbonamento', self::baseUrl($cfg) . '/abbonamento', Pages::contactEmail($cfg));
    }

    /** Suggerimenti per completare la configurazione. @param array<string,mixed> $cfg @param array<string,mixed> $acct @param array<int,string> $missing */
    public static function onboarding(array $cfg, array $acct, array $missing): bool
    {
        $text = "Ciao, la tua pagina di prenotazione è pronta ma si può rendere più efficace.\n\nManca ancora:\n- " . implode("\n- ", $missing)
            . "\n\nBastano due minuti dalle Impostazioni. Poi condividi il link con i tuoi clienti: " . self::shopUrl($cfg, $acct);
        return self::mail($cfg, (string)$acct['email'], 'Completa la tua pagina di prenotazione', $text, 'Apri le impostazioni', self::baseUrl($cfg) . '/settings', Pages::contactEmail($cfg));
    }
}
