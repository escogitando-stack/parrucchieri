<?php
declare(strict_types=1);

namespace App;

/** Esportazione dei dati del negozio in CSV (si apre con Excel e Fogli Google). */
final class Export
{
    /** Neutralizza le formule (=, +, -, @) che Excel eseguirebbe aprendo il file. */
    private static function cell(mixed $v): string
    {
        $s = (string)$v;
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $s = "'" . $s;
        }
        return '"' . str_replace('"', '""', $s) . '"';
    }

    private const STATUS = ['requested' => 'Da approvare', 'booked' => 'Prenotato', 'confirmed' => 'Confermato', 'in_progress' => 'In corso', 'completed' => 'Completato', 'cancelled' => 'Annullato', 'no_show' => 'Non presentato'];

    /** @return string CSV (separatore ";", con BOM per Excel) */
    public static function appointmentsCsv(Db $db, int $accountId, string $from, string $to): string
    {
        $rows = $db->all(
            "SELECT a.*, cl.name AS client_name, cl.phone AS client_phone, cl.email AS client_email, s.name AS staff_name
             FROM appointments a LEFT JOIN clients cl ON cl.id = a.client_id LEFT JOIN staff s ON s.id = a.staff_id
             WHERE a.account_id = ? AND a.scheduled_date >= ? AND a.scheduled_date <= ? ORDER BY a.scheduled_date, a.start_time",
            [$accountId, $from, $to]
        );
        $out = "\xEF\xBB\xBF" . implode(';', array_map([self::class, 'cell'], ['Codice', 'Data', 'Inizio', 'Fine', 'Stato', 'Cliente', 'Telefono', 'Email', 'Collaboratore', 'Servizi', 'Totale', 'Note', 'Origine'])) . "\r\n";
        foreach ($rows as $r) {
            $out .= implode(';', array_map([self::class, 'cell'], [
                $r['identifier'], $r['scheduled_date'], $r['start_time'], $r['end_time'], self::STATUS[$r['status']] ?? $r['status'],
                $r['client_name'], $r['client_phone'], $r['client_email'], $r['staff_name'], Booking::serviceNames($db, (int)$r['id']),
                number_format((float)$r['total_price'], 2, ',', ''), $r['notes'], str_starts_with((string)$r['source'], 'online') ? 'Online' : 'Inserito dal titolare',
            ])) . "\r\n";
        }
        return $out;
    }
}
