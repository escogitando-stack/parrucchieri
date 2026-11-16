<?php
declare(strict_types=1);

namespace App;

/** Piani di abbonamento gestiti dall'amministratore: nome, durata in giorni e prezzo. */
final class Plans
{
    /** @return array<int,array<string,mixed>> */
    public static function all(Db $db): array
    {
        return array_map([self::class, 'norm'], $db->all('SELECT * FROM plans ORDER BY sort_order, duration_days, id'));
    }

    /** @return array<int,array<string,mixed>> */
    public static function active(Db $db): array
    {
        return array_values(array_filter(self::all($db), static fn(array $p): bool => $p['active']));
    }

    /** @return array<string,mixed>|null */
    public static function find(Db $db, int $id): ?array
    {
        $r = $db->row('SELECT * FROM plans WHERE id = ?', [$id]);
        return $r === null ? null : self::norm($r);
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    private static function norm(array $r): array
    {
        return [
            'id' => (int)$r['id'],
            'name' => (string)$r['name'],
            'duration_days' => (int)$r['duration_days'],
            'price' => (float)$r['price'],
            'stripe_price_id' => (string)$r['stripe_price_id'],
            'active' => (int)$r['active'] === 1,
            'sort_order' => (int)$r['sort_order'],
        ];
    }

    /**
     * Crea (id = 0) o aggiorna un piano. Restituisce un messaggio di errore oppure null.
     *
     * @param array<string,mixed> $in name, duration_days, price, stripe_price_id, active
     */
    public static function save(Db $db, int $id, array $in): ?string
    {
        $name = trim((string)($in['name'] ?? ''));
        $days = (int)($in['duration_days'] ?? 0);
        $priceRaw = str_replace(',', '.', trim((string)($in['price'] ?? '0')));
        $stripe = trim((string)($in['stripe_price_id'] ?? ''));
        if ($name === '' || mb_strlen($name) > 60) {
            return 'Dai un nome al piano (massimo 60 caratteri).';
        }
        if ($days < 1 || $days > 3650) {
            return 'La durata deve essere tra 1 e 3650 giorni.';
        }
        if (!is_numeric($priceRaw) || (float)$priceRaw < 0 || (float)$priceRaw > 100000) {
            return 'Il prezzo non è valido.';
        }
        if ($stripe !== '' && !preg_match('/^price_[A-Za-z0-9]{6,60}$/', $stripe)) {
            return 'Il codice prezzo Stripe deve iniziare con "price_".';
        }
        $active = !empty($in['active']) ? 1 : 0;
        $price = round((float)$priceRaw, 2);
        if ($id === 0) {
            $max = (int)($db->row('SELECT COALESCE(MAX(sort_order), 0) AS m FROM plans')['m'] ?? 0);
            $db->exec('INSERT INTO plans (name, duration_days, price, stripe_price_id, active, sort_order) VALUES (?, ?, ?, ?, ?, ?)', [$name, $days, $price, $stripe, $active, $max + 1]);
            return null;
        }
        if (self::find($db, $id) === null) {
            return 'Piano non trovato.';
        }
        $db->exec('UPDATE plans SET name = ?, duration_days = ?, price = ?, stripe_price_id = ?, active = ? WHERE id = ?', [$name, $days, $price, $stripe, $active, $id]);
        return null;
    }

    public static function delete(Db $db, int $id): bool
    {
        return $db->exec('DELETE FROM plans WHERE id = ?', [$id]) > 0;
    }

    public static function priceLabel(float $price): string
    {
        return (fmod($price, 1.0) === 0.0 ? number_format($price, 0, ',', '.') : number_format($price, 2, ',', '.')) . ' €';
    }

    public static function durationLabel(int $days): string
    {
        return match (true) {
            $days === 7 => 'a settimana',
            $days >= 28 && $days <= 31 => 'al mese',
            $days >= 89 && $days <= 92 => 'ogni 3 mesi',
            $days >= 180 && $days <= 184 => 'ogni 6 mesi',
            $days >= 365 && $days <= 366 => "all'anno",
            default => 'per ' . $days . ' giorni',
        };
    }

    /** Assegna un piano a un negozio: attivo da ora (o dalla scadenza attuale, se già attivo) per la durata del piano. */
    public static function assign(Db $db, int $accountId, int $planId, ?int $now = null): bool
    {
        $now ??= time();
        $plan = self::find($db, $planId);
        $acct = Auth::account($db, $accountId);
        if ($plan === null || $acct === null) {
            return false;
        }
        $base = $acct['plan_status'] === 'active' && $acct['paid_until'] !== '' ? max($now, (int)strtotime((string)$acct['paid_until'])) : $now;
        $db->exec("UPDATE accounts SET plan_status = 'active', approved = 1, paid_until = ? WHERE id = ?", [date('Y-m-d H:i:s', $base + $plan['duration_days'] * 86400), $accountId]);
        return true;
    }
}
