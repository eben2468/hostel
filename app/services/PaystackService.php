<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Setting;

/**
 * Minimal Paystack REST client using cURL (no external dependencies).
 *
 * Each hostel can carry its own Paystack account so it collects its own
 * payments. The secret key is resolved per hostel first, then the global
 * System Settings, then the config constant.
 */
class PaystackService
{
    private const BASE = 'https://api.paystack.co';

    /** The global (fallback) secret key from settings, then config constant. */
    public static function secretKey(): string
    {
        $key = Setting::get('paystack_secret_key');
        return $key !== null && $key !== '' ? $key : (defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : '');
    }

    /**
     * Resolve the secret key to use for a given hostel: the hostel's own key
     * when set, otherwise the global key.
     */
    public static function secretKeyFor(?int $hostelId): string
    {
        if ($hostelId) {
            $key = Database::scalar("SELECT paystack_secret_key FROM hostels WHERE id = ?", [$hostelId]);
            if ($key !== false && $key !== null && $key !== '') {
                return (string) $key;
            }
        }
        return self::secretKey();
    }

    /** True when a usable secret key is configured (optionally for a hostel). */
    public static function isConfigured(?int $hostelId = null): bool
    {
        return self::secretKeyFor($hostelId) !== '';
    }

    /**
     * Initialize a transaction. Amount is in major units (e.g. GHS) and is
     * converted to the Paystack subunit (pesewas/kobo).
     *
     * @return array{status:bool, authorization_url?:string, reference?:string, message?:string}
     */
    public static function initialize(string $email, float $amount, string $reference, string $callbackUrl, array $metadata = [], ?int $hostelId = null): array
    {
        $payload = [
            'email'        => $email ?: 'guest@chms.test',
            'amount'       => (int) round($amount * 100),
            'currency'     => defined('CURRENCY') ? CURRENCY : 'GHS',
            'reference'    => $reference,
            'callback_url' => $callbackUrl,
            'metadata'     => $metadata,
        ];

        $res = self::request('POST', '/transaction/initialize', $payload, self::secretKeyFor($hostelId));
        if (($res['status'] ?? false) === true) {
            return [
                'status'            => true,
                'authorization_url' => $res['data']['authorization_url'] ?? '',
                'reference'         => $res['data']['reference'] ?? $reference,
            ];
        }
        return ['status' => false, 'message' => $res['message'] ?? 'Could not reach Paystack.'];
    }

    /**
     * Verify a transaction by reference.
     *
     * @return array{status:bool, paid:bool, amount?:float, metadata?:array, message?:string}
     */
    public static function verify(string $reference, ?int $hostelId = null): array
    {
        $res = self::request('GET', '/transaction/verify/' . rawurlencode($reference), null, self::secretKeyFor($hostelId));
        if (($res['status'] ?? false) === true) {
            $data = $res['data'] ?? [];
            return [
                'status'   => true,
                'paid'     => ($data['status'] ?? '') === 'success',
                'amount'   => isset($data['amount']) ? ((float) $data['amount']) / 100 : 0.0,
                'metadata' => $data['metadata'] ?? [],
            ];
        }
        return ['status' => false, 'paid' => false, 'message' => $res['message'] ?? 'Verification failed.'];
    }

    /** Perform an HTTP request with the given secret key and decode the JSON body. */
    private static function request(string $method, string $path, ?array $body, string $secretKey): array
    {
        $ch = curl_init(self::BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/json',
                'Cache-Control: no-cache',
            ],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['status' => false, 'message' => 'Network error: ' . $err];
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['status' => false, 'message' => 'Invalid response from Paystack.'];
    }

    public static function reference(): string
    {
        return 'PSK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
    }
}
