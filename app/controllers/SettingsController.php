<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Audit;
use App\Models\Setting;

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
            'pageTitle' => 'System Settings',
            'settings'  => Setting::all(),
        ]);
    }

    public function update(): void
    {
        $this->requireAuth('admin');
        Csrf::check();
        foreach (self::KEYS as $key) {
            // Checkboxes are absent when unchecked.
            if ($key === 'maintenance_mode') {
                Setting::set($key, isset($_POST[$key]) ? '1' : '0');
                continue;
            }
            Setting::set($key, $this->input($key));
        }

        // System logo: upload replaces the old file; the remove box clears it.
        // Handled outside the KEYS loop because it is a file, not a text field.
        if (!empty($_FILES['system_logo']['name'])) {
            $result = \App\Core\Upload::image($_FILES['system_logo'], 'branding');
            if (!$result['ok']) {
                Session::flash('error', $result['error']);
                $this->redirect('/settings');
            }
            \App\Core\Upload::remove(Setting::get('system_logo'));
            Setting::set('system_logo', $result['path']);
        } elseif (isset($_POST['remove_logo'])) {
            \App\Core\Upload::remove(Setting::get('system_logo'));
            Setting::set('system_logo', '');
        }

        Audit::log('update', 'settings');
        Session::flash('success', 'Settings saved.');
        $this->redirect('/settings');
    }
}
