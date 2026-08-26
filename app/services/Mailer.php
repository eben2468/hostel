<?php
namespace App\Services;

use App\Models\Setting;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Email sender. Uses SMTP via the vendored PHPMailer when configured in
 * System Settings; otherwise logs the message to storage/logs/mail.log so the
 * app keeps working without mail credentials.
 */
class Mailer
{
    /** Reason the last send() failed, for the settings screen to show. */
    private static string $lastError = '';

    public static function isConfigured(): bool
    {
        return (Setting::get('smtp_host') ?? '') !== '' && self::username() !== '';
    }

    /** The SMTP error from the most recent failed send (empty if none). */
    public static function lastError(): string
    {
        return self::$lastError;
    }

    /**
     * The SMTP login name.
     *
     * Google authenticates on the full address, so a bare mailbox name is
     * completed rather than left to fail as "Username and Password not
     * accepted". The domain comes from the From Address when one is set — a
     * Google Workspace school signs in as name@school.edu.gh, not @gmail.com —
     * and only falls back to gmail.com when there is no other clue.
     */
    public static function username(): string
    {
        $user = trim((string) (Setting::get('smtp_user') ?? ''));
        if ($user === '' || str_contains($user, '@')) {
            return $user;
        }

        $domain = self::domainOf(trim((string) (Setting::get('smtp_from') ?? '')));
        if ($domain !== '') {
            return $user . '@' . $domain;
        }
        return self::isGoogle() ? $user . '@gmail.com' : $user;
    }

    /**
     * True when the host is Google's SMTP service — the same servers serve
     * personal Gmail and Google Workspace (smtp.gmail.com), plus the Workspace
     * relay (smtp-relay.gmail.com).
     */
    public static function isGoogle(): bool
    {
        $host = strtolower(trim((string) (Setting::get('smtp_host') ?? '')));
        return str_contains($host, 'gmail.com') || str_contains($host, 'googlemail.com');
    }

    /**
     * The address mail is sent from.
     *
     * Google only lets you send as the account you signed in with or one of its
     * verified aliases. Sending as another address in the SAME domain is the
     * normal Workspace setup ("Send mail as", or the domain relay), so that is
     * allowed through; a foreign domain would be silently rewritten by Google,
     * so the sign-in address is used and the configured one becomes the
     * Reply-To instead (see send()).
     */
    public static function fromAddress(): string
    {
        $user      = self::username();
        $userValid = filter_var($user, FILTER_VALIDATE_EMAIL) !== false;
        $from      = trim((string) (Setting::get('smtp_from') ?? ''));

        if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            if (!self::isGoogle() || !$userValid || self::sameDomain($from, $user)) {
                return $from;
            }
            return $user;
        }
        return $userValid ? $user : '';
    }

    /**
     * True for a consumer @gmail.com sign-in, false for a Google Workspace one
     * (a school domain on the same servers) — they fail authentication for
     * different reasons, so the advice differs.
     */
    public static function isPersonalGmail(): bool
    {
        $domain = self::domainOf(self::username());
        return $domain === 'gmail.com' || $domain === 'googlemail.com';
    }

    /** Lower-cased domain part of an address, or '' when there isn't one. */
    private static function domainOf(string $email): string
    {
        $at = strrpos($email, '@');
        return $at === false ? '' : strtolower(substr($email, $at + 1));
    }

    /** True when two addresses share a mail domain (case-insensitively). */
    private static function sameDomain(string $a, string $b): bool
    {
        $da = self::domainOf($a);
        return $da !== '' && $da === self::domainOf($b);
    }

    /** Send an email. Returns true on success (or when logged in fallback mode). */
    public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        self::$lastError = '';

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'The recipient address is not a valid email address.';
            return false;
        }

        if (!self::isConfigured()) {
            self::log("TO: {$to}\nSUBJECT: {$subject}\n" . self::plainText($htmlBody), 'not sent - SMTP not configured');
            return true;
        }

        $fromEmail = self::fromAddress();
        if ($fromEmail === '') {
            self::$lastError = 'The SMTP username must be a full email address (for Gmail, the whole @gmail.com address), or set a valid From Address.';
            error_log('Mailer failed: ' . self::$lastError);
            self::log("FAILED TO: {$to}\nSUBJECT: {$subject}\nERROR: " . self::$lastError, 'send FAILED');
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = trim((string) Setting::get('smtp_host'));
            $mail->SMTPAuth   = true;
            $mail->Username   = self::username();
            // App passwords are shown in groups of four; a pasted copy often
            // carries the spaces, which SMTP would reject.
            $mail->Password   = preg_replace('/\s+/', '', (string) Setting::get('smtp_pass'));
            $port             = (int) (Setting::get('smtp_port') ?: 587);
            $mail->Port       = $port;
            $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;
            $mail->Timeout    = 20;

            $fromName = Setting::get('institution_name') ?: APP_SHORT;
            $mail->setFrom($fromEmail, (string) $fromName);

            // Keep replies going to the configured address when Gmail forced the
            // From to the account we logged in with.
            $configuredFrom = trim((string) (Setting::get('smtp_from') ?? ''));
            if ($configuredFrom !== '' && strcasecmp($configuredFrom, $fromEmail) !== 0
                && filter_var($configuredFrom, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($configuredFrom, (string) $fromName);
            }

            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?: self::plainText($htmlBody);

            // Attach the logo inline when the template asked for it, so it shows
            // without the recipient having to allow remote images.
            $logoPath = self::logoPath();
            if ($logoPath !== null && str_contains($htmlBody, 'cid:' . self::LOGO_CID)) {
                $mail->addEmbeddedImage(
                    $logoPath,
                    self::LOGO_CID,
                    'logo.' . strtolower(pathinfo($logoPath, PATHINFO_EXTENSION))
                );
            }

            $mail->send();
            return true;
        } catch (MailException $e) {
            self::$lastError = self::explain($mail->ErrorInfo ?: $e->getMessage());
            error_log('Mailer failed: ' . $mail->ErrorInfo);
            self::log("FAILED TO: {$to}\nSUBJECT: {$subject}\nERROR: {$mail->ErrorInfo}", 'send FAILED');
            return false;
        }
    }

    /**
     * Turn an SMTP error into something an administrator can act on.
     * The raw message is kept — it is the only clue when the cause is unusual.
     */
    private static function explain(string $error): string
    {
        $error = trim(preg_replace('/\s+/', ' ', $error));
        $hint  = '';

        if (stripos($error, 'authenticate') !== false || stripos($error, 'Username and Password not accepted') !== false) {
            if (self::isGoogle()) {
                $hint = 'Google rejected the login. The SMTP Username must be the complete address (your full @gmail.com or school address such as name@vvu.edu.gh) and the password must be a 16-character App Password generated on that same account — not the account password.';
                // Workspace adds a second failure mode a personal Gmail cannot have.
                if (!self::isPersonalGmail()) {
                    $hint .= ' On a Google Workspace (school) account the sign-in must have 2-Step Verification turned on, and the Workspace administrator must allow App Passwords for it.';
                }
            } else {
                $hint = 'The mail server rejected the username or password.';
            }
        } elseif (stripos($error, 'Invalid address') !== false) {
            $hint = 'One of the addresses is not valid — check the SMTP Username and From Address.';
        } elseif (stripos($error, 'connect') !== false || stripos($error, 'timed out') !== false) {
            $hint = 'The server could not be reached on that host/port. Port 587 (TLS) or 465 (SSL) must be open for outgoing connections.';
        }

        return $hint !== '' ? $hint . ' [' . $error . ']' : $error;
    }

    /** Content ID of the logo embedded by send(); referenced as cid:brandlogo. */
    private const LOGO_CID = 'brandlogo';

    /**
     * Absolute path of the brand mark to embed, or null when none is set.
     *
     * The favicon is preferred: it is the same mark derived from the system
     * logo but tightly cropped and far smaller, and it travels with every
     * message — the full-size logo would add ~100 KB to each email.
     */
    public static function logoPath(): ?string
    {
        foreach (['system_favicon', 'system_logo'] as $key) {
            $file = trim((string) (Setting::get($key) ?? ''));
            if ($file !== '' && is_file(UPLOAD_PATH . '/' . $file)) {
                return UPLOAD_PATH . '/' . $file;
            }
        }
        return null;
    }

    /**
     * Wrap content in the branded HTML email shell.
     *
     * Built from tables with inline styles because that is what mail clients
     * render reliably — Outlook ignores flexbox, border-radius and gradients,
     * so the navy header carries a solid colour underneath the gradient and
     * every width degrades to a single readable column on a phone.
     */
    public static function template(string $heading, string $bodyHtml): string
    {
        $brand = htmlspecialchars((string) (Setting::get('institution_name') ?: APP_NAME));

        // The logo travels with the message (see send()), so it shows even when
        // the client blocks remote images.
        $logo = self::logoPath()
            ? '<img src="cid:' . self::LOGO_CID . '" width="70" height="70" alt="" '
                . 'style="display:block;margin:0 auto 12px;border:0;outline:none;text-decoration:none;'
                . 'width:70px;height:70px;max-width:70px;object-fit:contain;">'
            : '';

        return '<!DOCTYPE html><html lang="en"><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="x-apple-disable-message-reformatting">'
            . '<title>' . $brand . '</title>'
            . '<style>'
            . 'body{margin:0;padding:0;width:100%!important;background:#eef1f6;}'
            . 'img{-ms-interpolation-mode:bicubic;}'
            . 'a{color:#2b5b97;}'
            . '@media only screen and (max-width:480px){'
            . '.m-wrap{padding:14px 10px!important}'
            . '.m-head{padding:22px 18px!important}'
            . '.m-pad{padding:22px 18px!important}'
            . '.m-foot{padding:14px 18px!important}'
            . '.m-code{font-size:26px!important;letter-spacing:3px!important;text-indent:3px!important;padding:14px 18px!important}'
            . '}'
            . '</style></head>'
            . '<body style="margin:0;padding:0;background:#eef1f6;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef1f6;">'
            . '<tr><td class="m-wrap" align="center" style="padding:28px 14px;">'

            // Card. No legacy width attribute — it stops the table shrinking on a
            // phone; max-width alone keeps it to 560px on a desktop client.
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="width:100%;max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;'
            . 'border:1px solid #e3e8ef;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;">'

            // Header — solid navy, with the app's gradient where it is supported
            . '<tr><td class="m-head" align="center" bgcolor="#203655" '
            . 'style="background-color:#203655;'
            . 'background-image:linear-gradient(135deg,#233f66 0%,#203655 55%,#142339 100%);'
            . 'padding:26px 24px;">'
            . $logo
            . '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;'
            . 'font-size:19px;line-height:26px;font-weight:700;color:#ffffff;letter-spacing:.2px;">' . $brand . '</div>'
            . '</td></tr>'

            // Body
            . '<tr><td class="m-pad" style="padding:30px 32px 28px;color:#334155;'
            . 'font-size:15px;line-height:23px;">'
            . '<h1 style="margin:0 0 18px;font-size:19px;line-height:26px;font-weight:700;color:#0f172a;">'
            . htmlspecialchars($heading) . '</h1>'
            . $bodyHtml
            . '</td></tr>'

            // Footer
            . '<tr><td class="m-foot" style="padding:16px 32px;background:#f6f8fb;border-top:1px solid #e9edf3;'
            . 'color:#94a3b8;font-size:12px;line-height:18px;">'
            . 'This is an automated message from ' . $brand . '. Please do not reply.'
            . '</td></tr>'

            . '</table></td></tr></table></body></html>';
    }

    /**
     * A centred, boxed one-time code.
     * Kept here so every code email is spaced and sized identically.
     */
    public static function codeBlock(string $code): string
    {
        return '<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" '
            . 'style="margin:0 auto 22px;"><tr>'
            . '<td class="m-code" align="center" bgcolor="#f1f5f9" '
            . 'style="background:#f1f5f9;border:1px solid #dfe6ef;border-radius:12px;padding:16px 24px;'
            . 'font-family:Consolas,\'SF Mono\',Menlo,\'Courier New\',monospace;font-size:30px;line-height:36px;'
            // The trailing letter-space is offset by an equal indent so the digits sit centred.
            . 'font-weight:700;color:#142339;letter-spacing:5px;text-indent:5px;white-space:nowrap;">'
            . htmlspecialchars($code) . '</td>'
            . '</tr></table>';
    }

    /**
     * Readable plain-text fallback for the HTML body.
     * The head, styles and title are dropped first so the alternative part is
     * the message rather than a dump of the stylesheet.
     */
    private static function plainText(string $html): string
    {
        $text = preg_replace('#<(head|style|title|script)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = preg_replace('#<(br|/p|/div|/tr|/h1|/h2|/td)\b[^>]*>#i', "\n", (string) $text);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n\s*\n\s*\n+/", "\n\n", (string) $text);
        return trim((string) $text);
    }

    private static function log(string $message, string $reason): void
    {
        $line = '[' . date('Y-m-d H:i:s') . "] EMAIL ({$reason})\n{$message}\n" . str_repeat('-', 50) . "\n";
        @file_put_contents(STORAGE_PATH . '/logs/mail.log', $line, FILE_APPEND);
    }
}
