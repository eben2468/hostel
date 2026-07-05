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
    public static function isConfigured(): bool
    {
        return (Setting::get('smtp_host') ?? '') !== '' && (Setting::get('smtp_user') ?? '') !== '';
    }

    /** Send an email. Returns true on success (or when logged in fallback mode). */
    public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (!self::isConfigured()) {
            self::log("TO: {$to}\nSUBJECT: {$subject}\n" . strip_tags($htmlBody));
            return true;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = (string) Setting::get('smtp_host');
            $mail->SMTPAuth   = true;
            $mail->Username   = (string) Setting::get('smtp_user');
            $mail->Password   = (string) Setting::get('smtp_pass');
            $port             = (int) (Setting::get('smtp_port') ?: 587);
            $mail->Port       = $port;
            $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

            $fromEmail = Setting::get('smtp_from') ?: Setting::get('smtp_user');
            $fromName  = Setting::get('institution_name') ?: APP_SHORT;
            $mail->setFrom((string) $fromEmail, (string) $fromName);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (MailException $e) {
            error_log('Mailer failed: ' . $mail->ErrorInfo);
            self::log("FAILED TO: {$to}\nSUBJECT: {$subject}\nERROR: {$mail->ErrorInfo}");
            return false;
        }
    }

    /** Wrap content in a simple branded HTML template. */
    public static function template(string $heading, string $bodyHtml): string
    {
        $brand = Setting::get('institution_name') ?: APP_NAME;
        return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #eee;border-radius:8px;overflow:hidden">'
            . '<div style="background:#4f46e5;color:#fff;padding:18px 24px;font-size:18px;font-weight:bold">' . htmlspecialchars($brand) . '</div>'
            . '<div style="padding:24px;color:#333">'
            . '<h2 style="margin-top:0;color:#111;font-size:18px">' . htmlspecialchars($heading) . '</h2>'
            . $bodyHtml
            . '</div>'
            . '<div style="padding:14px 24px;background:#f9fafb;color:#9ca3af;font-size:12px">This is an automated message from ' . htmlspecialchars($brand) . '.</div>'
            . '</div>';
    }

    private static function log(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . "] EMAIL (not sent - SMTP not configured)\n{$message}\n" . str_repeat('-', 50) . "\n";
        @file_put_contents(STORAGE_PATH . '/logs/mail.log', $line, FILE_APPEND);
    }
}
