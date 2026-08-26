<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\Setting;

/**
 * Email two-factor authentication.
 *
 * After a correct password, users whose role is listed under System Settings
 * must type a one-time code emailed to them before a session is created.
 * Codes are stored hashed, expire, are single-use, and are rate limited.
 *
 * Settings that drive it (all managed from /settings):
 *   twofa_enabled    "1" | "0"  — master switch
 *   twofa_roles      CSV of roles that must complete 2FA
 *   twofa_recipients JSON map of role => override recipient email(s)
 */
class TwoFactor
{
    // Session keys holding the half-finished login.
    private const S_USER      = '2fa_user_id';
    private const S_LOGIN     = '2fa_login';
    private const S_CODE      = '2fa_code_id';
    private const S_EXPIRES   = '2fa_expires';
    private const S_LAST_SENT = '2fa_last_sent';
    private const S_SENT_TO   = '2fa_sent_to';

    // --- Configuration ------------------------------------------------------

    public static function enabled(): bool
    {
        return Setting::get('twofa_enabled') === '1';
    }

    /** Roles an administrator has marked as requiring 2FA. */
    public static function roles(): array
    {
        $csv = (string) (Setting::get('twofa_roles') ?? '');
        $roles = array_filter(array_map('trim', explode(',', $csv)));
        return array_values(array_intersect($roles, array_keys(roles_list())));
    }

    /** Map of role => override recipient string, as saved by the admin. */
    public static function recipientOverrides(): array
    {
        $json = (string) (Setting::get('twofa_recipients') ?? '');
        $map  = $json !== '' ? json_decode($json, true) : [];
        return is_array($map) ? $map : [];
    }

    /** True when this user must clear a 2FA challenge to sign in. */
    public static function requiredFor(array $user): bool
    {
        return self::enabled() && in_array($user['role'] ?? '', self::roles(), true);
    }

    /**
     * Where this user's code should be delivered.
     *
     * An administrator may pin a role's codes to specific mailboxes (useful for
     * shared staff accounts); otherwise the code goes to the user's own address.
     *
     * @return string[] valid, de-duplicated email addresses
     */
    public static function recipientsFor(array $user): array
    {
        $override = trim((string) (self::recipientOverrides()[$user['role'] ?? ''] ?? ''));
        $raw = $override !== '' ? $override : (string) ($user['email'] ?? '');

        $emails = [];
        foreach (preg_split('/[,;\s]+/', $raw) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                $emails[strtolower($candidate)] = $candidate;
            }
        }
        return array_values($emails);
    }

    // --- Challenge lifecycle ------------------------------------------------

    /**
     * Generate a code, email it, and open a pending-login window in the session.
     *
     * @return array{ok:bool,error:?string,sent_to:string[]}
     */
    public static function issue(array $user, string $login = ''): array
    {
        $recipients = self::recipientsFor($user);
        if (!$recipients) {
            return [
                'ok'      => false,
                'error'   => 'No verification email address is set for your account. Please contact the administrator.',
                'sent_to' => [],
            ];
        }

        // Retire any code still outstanding for this user so only the newest works.
        Database::run(
            "UPDATE two_factor_codes SET consumed_at = NOW() WHERE user_id = ? AND consumed_at IS NULL",
            [$user['id']]
        );

        $code = self::randomCode();
        $codeId = Database::insert(
            "INSERT INTO two_factor_codes (user_id, code_hash, sent_to, expires_at, ip_address)
             VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)",
            [
                $user['id'],
                password_hash($code, PASSWORD_BCRYPT),
                implode(', ', $recipients),
                TWOFA_EXPIRY_MINUTES,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );

        // Delivery to any one of the listed mailboxes is enough to proceed.
        $delivered = false;
        foreach ($recipients as $to) {
            $delivered = self::mail($to, $user, $code) || $delivered;
        }
        if (!$delivered) {
            Database::run("UPDATE two_factor_codes SET consumed_at = NOW() WHERE id = ?", [$codeId]);
            return [
                'ok'      => false,
                'error'   => 'We could not send your verification code. Please try again or contact the administrator.',
                'sent_to' => [],
            ];
        }

        Session::set(self::S_USER, (int) $user['id']);
        Session::set(self::S_CODE, $codeId);
        Session::set(self::S_EXPIRES, time() + (TWOFA_EXPIRY_MINUTES * 60));
        Session::set(self::S_LAST_SENT, time());
        Session::set(self::S_SENT_TO, $recipients);
        if ($login !== '') {
            Session::set(self::S_LOGIN, $login);
        }

        return ['ok' => true, 'error' => null, 'sent_to' => $recipients];
    }

    /**
     * Check a submitted code against the pending challenge.
     *
     * @return array{ok:bool,error:?string,dead:bool} `dead` means the challenge
     *         is finished and the user must start the login over.
     */
    public static function verify(string $submitted): array
    {
        if (!self::pending()) {
            return ['ok' => false, 'error' => 'Your verification session expired. Please sign in again.', 'dead' => true];
        }

        $row = Database::first(
            "SELECT * FROM two_factor_codes WHERE id = ? AND user_id = ? LIMIT 1",
            [Session::get(self::S_CODE), Session::get(self::S_USER)]
        );
        if (!$row || $row['consumed_at'] !== null || strtotime($row['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'That code has expired. Please sign in again to get a new one.', 'dead' => true];
        }

        $submitted = preg_replace('/\D/', '', $submitted);
        if (!password_verify((string) $submitted, $row['code_hash'])) {
            $attempts = (int) $row['attempts'] + 1;
            Database::run("UPDATE two_factor_codes SET attempts = ? WHERE id = ?", [$attempts, $row['id']]);
            if ($attempts >= TWOFA_MAX_ATTEMPTS) {
                Database::run("UPDATE two_factor_codes SET consumed_at = NOW() WHERE id = ?", [$row['id']]);
                return ['ok' => false, 'error' => 'Too many incorrect codes. Please sign in again.', 'dead' => true];
            }
            $left = TWOFA_MAX_ATTEMPTS - $attempts;
            return [
                'ok'    => false,
                'error' => "Incorrect code. {$left} attempt" . ($left === 1 ? '' : 's') . ' remaining.',
                'dead'  => false,
            ];
        }

        Database::run("UPDATE two_factor_codes SET consumed_at = NOW() WHERE id = ?", [$row['id']]);
        return ['ok' => true, 'error' => null, 'dead' => false];
    }

    /** True while an unexpired challenge is waiting on a code. */
    public static function pending(): bool
    {
        return Session::has(self::S_USER) && (int) Session::get(self::S_EXPIRES) > time();
    }

    public static function pendingUserId(): ?int
    {
        return self::pending() ? (int) Session::get(self::S_USER) : null;
    }

    /** The login string typed on the sign-in form, for lockout bookkeeping. */
    public static function pendingLogin(): string
    {
        return (string) Session::get(self::S_LOGIN, '');
    }

    /** Addresses the current code went to, for the "sent to ..." line. */
    public static function pendingRecipients(): array
    {
        $sent = Session::get(self::S_SENT_TO, []);
        return is_array($sent) ? $sent : [];
    }

    /** Seconds left on the resend cooldown; 0 when a resend is allowed. */
    public static function resendWait(): int
    {
        $last = (int) Session::get(self::S_LAST_SENT, 0);
        return max(0, TWOFA_RESEND_SECONDS - (time() - $last));
    }

    /** Drop the pending challenge (successful login, cancel, or failure). */
    public static function clear(): void
    {
        foreach ([self::S_USER, self::S_LOGIN, self::S_CODE, self::S_EXPIRES, self::S_LAST_SENT, self::S_SENT_TO] as $key) {
            Session::forget($key);
        }
    }

    /** Best-effort tidy-up of codes that are long dead. */
    public static function prune(): void
    {
        try {
            Database::run("DELETE FROM two_factor_codes WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        } catch (\Throwable $e) {
            error_log('2FA prune failed: ' . $e->getMessage());
        }
    }

    // --- Presentation helpers ----------------------------------------------

    /** Mask an address for display: j*****n@example.com */
    public static function mask(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at < 1) {
            return $email;
        }
        $name   = substr($email, 0, $at);
        $domain = substr($email, $at);
        if (strlen($name) <= 2) {
            return substr($name, 0, 1) . '***' . $domain;
        }
        return substr($name, 0, 1) . str_repeat('*', min(5, strlen($name) - 2)) . substr($name, -1) . $domain;
    }

    // --- Internals ----------------------------------------------------------

    /** A zero-padded numeric code drawn from a cryptographic source. */
    private static function randomCode(): string
    {
        $max = (10 ** TWOFA_CODE_LENGTH) - 1;
        return str_pad((string) random_int(0, $max), TWOFA_CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private static function mail(string $to, array $user, string $code): bool
    {
        $body = '<p style="margin:0 0 8px;">Hello ' . e($user['name'] ?? '') . ',</p>'
            . '<p style="margin:0 0 20px;">Use this code to finish signing in to your account:</p>'
            . Mailer::codeBlock($code)
            . '<p style="margin:0 0 16px;color:#64748b;font-size:14px;line-height:21px;text-align:center;">'
            . 'Expires in ' . TWOFA_EXPIRY_MINUTES . ' minutes &middot; can only be used once</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="margin-top:4px;"><tr>'
            . '<td bgcolor="#fef2f2" style="background:#fef2f2;border-left:3px solid #dc2626;border-radius:6px;'
            . 'padding:12px 14px;color:#b91c1c;font-size:13px;line-height:20px;">'
            . 'If you did not try to sign in, change your password immediately and tell an administrator.'
            . '</td></tr></table>';

        return Mailer::send(
            $to,
            'Your sign-in verification code',
            Mailer::template('Two-Factor Verification', $body),
            "Your verification code is {$code}. It expires in " . TWOFA_EXPIRY_MINUTES . ' minutes.'
        );
    }
}
