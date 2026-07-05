<?php
namespace App\Services;

use App\Models\Setting;

/**
 * SMS sender. When an API key + sender are configured in System Settings it
 * posts to the provider's REST API over cURL; otherwise the message is logged
 * to storage/logs/sms.log so the rest of the app is unaffected.
 *
 * Default provider is Arkesel (popular in Ghana). Hubtel is also supported.
 */
class Sms
{
    public static function isConfigured(): bool
    {
        return (Setting::get('sms_api_key') ?? '') !== '' && (Setting::get('sms_sender') ?? '') !== '';
    }

    public static function send(string $phone, string $message): bool
    {
        $phone = preg_replace('/\s+/', '', $phone);
        if ($phone === '') {
            return false;
        }

        if (!self::isConfigured()) {
            self::log("TO: {$phone}\n{$message}");
            return true;
        }

        $provider = strtolower((string) (Setting::get('sms_provider') ?: 'arkesel'));
        $sender   = (string) Setting::get('sms_sender');
        $apiKey   = (string) Setting::get('sms_api_key');

        try {
            if ($provider === 'hubtel') {
                // Hubtel: clientid:clientsecret in the api key field, basic auth style.
                $url = 'https://smsc.hubtel.com/v1/messages/send?' . http_build_query([
                    'from' => $sender, 'to' => $phone, 'content' => $message,
                ]);
                $headers = ['Authorization: Basic ' . base64_encode($apiKey)];
                $resp = self::curl('GET', $url, null, $headers);
            } else {
                // Arkesel v2 JSON API.
                $resp = self::curl('POST', 'https://sms.arkesel.com/api/v2/sms/send', [
                    'sender'     => $sender,
                    'message'    => $message,
                    'recipients' => [$phone],
                ], ['api-key: ' . $apiKey]);
            }

            if ($resp['ok']) {
                return true;
            }
            self::log("FAILED TO: {$phone}\nHTTP {$resp['code']} {$resp['body']}");
            return false;
        } catch (\Throwable $e) {
            error_log('Sms failed: ' . $e->getMessage());
            self::log("ERROR TO: {$phone}\n" . $e->getMessage());
            return false;
        }
    }

    private static function curl(string $method, string $url, ?array $json, array $headers): array
    {
        $ch = curl_init($url);
        $headers[] = 'Content-Type: application/json';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => (string) $body];
    }

    private static function log(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . "] SMS (not sent - provider not configured)\n{$message}\n" . str_repeat('-', 50) . "\n";
        @file_put_contents(STORAGE_PATH . '/logs/sms.log', $line, FILE_APPEND);
    }
}
