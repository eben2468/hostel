<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Audit;
use App\Models\Setting;
use App\Services\Mailer;
use App\Services\TwoFactor;

class SettingsController extends Controller
{
    /** Settings the form manages, with default values. */
    private const KEYS = [
        'institution_name', 'academic_year', 'semester', 'currency',
        'paystack_public_key', 'paystack_secret_key',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from',
        'sms_provider', 'sms_sender', 'sms_api_key', 'maintenance_mode',
    ];

    public function index(): void
    {
        $this->requireAuth('admin');
        $this->view('settings/index', [
            'pageTitle'     => 'System Settings',
            'settings'      => Setting::all(),
            'twofaRoles'    => TwoFactor::roles(),
            'twofaEmails'   => TwoFactor::recipientOverrides(),
            'smtpConfigured' => Mailer::isConfigured(),
        ]);
    }

    public function update(): void
    {
        $this->requireAuth('admin');
        Csrf::check();

        $error = $this->persist();
        if ($error !== null) {
            Session::flash('error', $error);
            $this->redirect('/settings');
        }

        Audit::log('update', 'settings');
        Session::flash('success', 'Settings saved.');
        $this->redirect('/settings');
    }

    /**
     * Save the settings, then email a test message to prove the SMTP details
     * work — the safe way to check Gmail credentials before turning 2FA on.
     */
    public function testEmail(): void
    {
        $this->requireAuth('admin');
        Csrf::check();

        // Save first, so the test uses exactly what is on screen.
        $warning = $this->persist();
        Audit::log('update', 'settings');

        $to = trim((string) $this->input('test_email_to', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Enter a valid address to send the test email to.');
            $this->redirect('/settings');
        }
        if (!Mailer::isConfigured()) {
            Session::flash('error', 'Fill in the SMTP host and username first — without them email is only written to storage/logs/mail.log.');
            $this->redirect('/settings');
        }

        $sent = Mailer::send(
            $to,
            'Test email from ' . brand_name(),
            Mailer::template('SMTP Test', '<p style="margin:0">Your SMTP settings are working. Two-factor sign-in codes will be delivered from this mailbox.</p>')
        );

        Audit::log('test_email', 'settings', null, $to);
        $message = $sent
            ? 'Test email sent to ' . $to . ' from ' . Mailer::fromAddress() . '. Check the inbox (and the spam folder).'
            : 'Sending failed. ' . Mailer::lastError();
        Session::flash($sent ? 'success' : 'error', $warning !== null ? $message . ' ' . $warning : $message);
        $this->redirect('/settings');
    }

    /**
     * Write the submitted settings.
     *
     * @return string|null an error message when nothing was saved, else null
     */
    private function persist(): ?string
    {
        foreach (self::KEYS as $key) {
            // Checkboxes are absent when unchecked.
            if ($key === 'maintenance_mode') {
                Setting::set($key, isset($_POST[$key]) ? '1' : '0');
                continue;
            }
            // Gmail App Passwords are displayed in groups of four; a pasted copy
            // usually keeps the spaces, which the SMTP server would reject.
            if ($key === 'smtp_pass') {
                Setting::set($key, preg_replace('/\s+/', '', (string) $this->input($key)));
                continue;
            }
            Setting::set($key, $this->input($key));
        }

        // Gmail authenticates on the full address, so complete a bare mailbox
        // name rather than let it fail later as "credentials not accepted".
        $smtpUser = Mailer::username();
        if ($smtpUser !== $this->input('smtp_user')) {
            Setting::set('smtp_user', $smtpUser);
        }

        $error = $this->persistTwoFactor();

        // System logo: upload replaces the old file; the remove box clears it.
        // Handled outside the KEYS loop because it is a file, not a text field.
        if (!empty($_FILES['system_logo']['name'])) {
            $result = \App\Core\Upload::image($_FILES['system_logo'], 'branding');
            if (!$result['ok']) {
                return $result['error'];
            }
            \App\Core\Upload::remove(Setting::get('system_logo'));
            \App\Core\Upload::remove(Setting::get('system_favicon'));
            Setting::set('system_logo', $result['path']);
            // Derive a tight favicon so the logo fills the browser tab.
            Setting::set('system_favicon', \App\Core\Upload::makeFavicon($result['path']) ?? '');
        } elseif (isset($_POST['remove_logo'])) {
            \App\Core\Upload::remove(Setting::get('system_logo'));
            \App\Core\Upload::remove(Setting::get('system_favicon'));
            Setting::set('system_logo', '');
            Setting::set('system_favicon', '');
        }

        return $error;
    }

    /**
     * Save the two-factor rules: which roles are challenged, and the mailboxes
     * their codes go to.
     *
     * @return string|null a warning to show the administrator, else null
     */
    private function persistTwoFactor(): ?string
    {
        $valid = array_keys(roles_list());

        $roles = array_values(array_intersect((array) ($_POST['twofa_roles'] ?? []), $valid));
        Setting::set('twofa_roles', implode(',', $roles));

        // Per-role override mailboxes. A blank entry means "use each user's own
        // account email", so blanks are dropped rather than stored.
        $recipients = [];
        $invalid    = [];
        foreach ((array) ($_POST['twofa_recipient'] ?? []) as $role => $raw) {
            if (!in_array($role, $valid, true)) {
                continue;
            }
            $clean = [];
            foreach (preg_split('/[,;\s]+/', trim((string) $raw)) as $email) {
                $email = trim($email);
                if ($email === '') {
                    continue;
                }
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $clean[strtolower($email)] = $email;
                } else {
                    $invalid[] = $email;
                }
            }
            if ($clean) {
                $recipients[$role] = implode(', ', $clean);
            }
        }
        Setting::set('twofa_recipients', $recipients ? json_encode($recipients) : '');

        // The master switch is applied last so the checks below can veto it.
        $wanted = isset($_POST['twofa_enabled']);
        if ($wanted && !Mailer::isConfigured()) {
            Setting::set('twofa_enabled', '0');
            return 'Settings saved, but two-factor authentication stays off: configure the SMTP host and username first, or nobody could receive a code.';
        }
        if ($wanted && !$roles) {
            Setting::set('twofa_enabled', '0');
            return 'Settings saved, but two-factor authentication stays off: tick at least one role that should be challenged.';
        }
        Setting::set('twofa_enabled', $wanted ? '1' : '0');

        if ($invalid) {
            return 'Settings saved, but these are not valid email addresses and were ignored: ' . implode(', ', array_slice($invalid, 0, 5)) . '.';
        }
        return null;
    }
}
