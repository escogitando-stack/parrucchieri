<?php
declare(strict_types=1);

namespace App;

/**
 * Abbonamento con Stripe (Checkout + Customer Portal + webhook), senza librerie esterne.
 * Le chiavi stanno in server/config.php, sezione "stripe".
 */
final class Billing
{
    /** @param array<string,mixed> $cfg */
    public static function enabled(array $cfg): bool
    {
        $s = $cfg['stripe'] ?? [];
        return !empty($s['secret_key']) && !empty($s['webhook_secret'])
            && (!empty($s['price_monthly']) || !empty($s['price_yearly']));
    }

    /**
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    private static function stripe(array $cfg, string $method, string $path, array $fields = []): array
    {
        $ch = curl_init('https://api.stripe.com/v1/' . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERPWD => (string)($cfg['stripe']['secret_key'] ?? '') . ':',
            CURLOPT_POSTFIELDS => $method === 'GET' ? null : http_build_query($fields),
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || $status >= 400) {
            $msg = is_array($data) ? (string)($data['error']['message'] ?? 'errore') : 'nessuna risposta';
            throw new \RuntimeException("Stripe: $msg (HTTP $status)");
        }
        return $data;
    }

    /** Pagina di pagamento ospitata da Stripe: restituisce l'indirizzo a cui mandare il cliente. */
    public static function checkoutUrl(array $cfg, array $acct, string $plan, string $baseUrl): string
    {
        $price = (string)($cfg['stripe'][$plan === 'annuale' ? 'price_yearly' : 'price_monthly'] ?? '');
        if ($price === '') {
            throw new \RuntimeException('Piano non configurato');
        }
        $fields = [
            'mode' => 'subscription',
            'line_items' => [['price' => $price, 'quantity' => 1]],
            'client_reference_id' => (string)$acct['id'],
            'success_url' => $baseUrl . '/abbonamento?esito=ok',
            'cancel_url' => $baseUrl . '/abbonamento?esito=annullato',
            'allow_promotion_codes' => 'true',
            'subscription_data' => ['metadata' => ['account_id' => (string)$acct['id']]],
        ];
        if (!empty($acct['stripe_customer_id'])) {
            $fields['customer'] = (string)$acct['stripe_customer_id'];
        } else {
            $fields['customer_email'] = (string)$acct['email'];
        }
        return (string)self::stripe($cfg, 'POST', 'checkout/sessions', $fields)['url'];
    }

    /** Pagina Stripe dove il cliente cambia carta, scarica fatture o disdice. */
    public static function portalUrl(array $cfg, array $acct, string $baseUrl): string
    {
        return (string)self::stripe($cfg, 'POST', 'billing_portal/sessions', [
            'customer' => (string)$acct['stripe_customer_id'],
            'return_url' => $baseUrl . '/abbonamento',
        ])['url'];
    }

    /** Controlla la firma inviata da Stripe (tolleranza 5 minuti). */
    public static function verifySignature(string $payload, string $header, string $secret, ?int $now = null): bool
    {
        $t = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $t = (int)$v;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }
        if ($t === null || !$sigs || abs(($now ?? time()) - $t) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($sigs as $s) {
            if (hash_equals($expected, $s)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Applica un evento Stripe già verificato allo stato del negozio.
     *
     * @param array<string,mixed> $event
     */
    public static function handleEvent(Db $db, array $event): void
    {
        $type = (string)($event['type'] ?? '');
        $obj = $event['data']['object'] ?? [];
        if (!is_array($obj)) {
            return;
        }
        if ($type === 'checkout.session.completed') {
            $id = (int)($obj['client_reference_id'] ?? 0);
            if ($id > 0 && ($obj['mode'] ?? '') === 'subscription') {
                $db->exec(
                    "UPDATE accounts SET plan_status = 'active', stripe_customer_id = ?, stripe_subscription_id = ? WHERE id = ?",
                    [(string)($obj['customer'] ?? ''), (string)($obj['subscription'] ?? ''), $id]
                );
            }
            return;
        }
        if ($type === 'customer.subscription.updated' || $type === 'customer.subscription.deleted' || $type === 'customer.subscription.created') {
            $acct = $db->row('SELECT id FROM accounts WHERE stripe_subscription_id = ?', [(string)($obj['id'] ?? '')]);
            if ($acct === null && !empty($obj['metadata']['account_id'])) {
                $acct = $db->row('SELECT id FROM accounts WHERE id = ?', [(int)$obj['metadata']['account_id']]);
            }
            if ($acct === null) {
                return;
            }
            $status = (string)($obj['status'] ?? '');
            $live = $type !== 'customer.subscription.deleted' && in_array($status, ['active', 'trialing'], true);
            $end = (int)($obj['current_period_end'] ?? ($obj['items']['data'][0]['current_period_end'] ?? 0));
            $db->exec(
                'UPDATE accounts SET plan_status = ?, paid_until = ?, stripe_customer_id = ?, stripe_subscription_id = ? WHERE id = ?',
                [
                    $live ? 'active' : 'expired',
                    $live && $end > 0 ? date('Y-m-d H:i:s', $end) : '',
                    (string)($obj['customer'] ?? ''),
                    (string)($obj['id'] ?? ''),
                    (int)$acct['id'],
                ]
            );
        }
    }
}
