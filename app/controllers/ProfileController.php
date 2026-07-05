<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Database;

class ProfileController extends Controller
{
    public function edit(): void
    {
        $this->requireAuth();
        $this->view('profile/edit', ['pageTitle' => 'My Profile', 'user' => Auth::user()]);
    }

    public function update(): void
    {
        $this->requireAuth();
        Csrf::check();
        Database::run(
            "UPDATE users SET name=?, phone=?, email=? WHERE id=?",
            [$this->input('name'), $this->input('phone'), $this->input('email'), Auth::id()]
        );
        Session::set('user_name', $this->input('name'));

        // Optional avatar upload.
        if (!empty($_FILES['avatar']['name'])) {
            $result = \App\Core\Upload::image($_FILES['avatar'], 'avatars');
            if ($result['ok']) {
                $user = Auth::user();
                \App\Core\Upload::remove($user['avatar'] ?? null);
                Database::run("UPDATE users SET avatar=? WHERE id=?", [$result['path'], Auth::id()]);
            } else {
                Session::flash('error', $result['error']);
                $this->redirect('/profile');
            }
        }

        Session::flash('success', 'Profile updated.');
        $this->redirect('/profile');
    }

    public function password(): void
    {
        $this->requireAuth();
        Csrf::check();
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $user = Auth::user();
        if (!password_verify($current, $user['password'])) {
            Session::flash('error', 'Current password is incorrect.');
            $this->redirect('/profile');
        }
        if (strlen($new) < 6) {
            Session::flash('error', 'New password must be at least 6 characters.');
            $this->redirect('/profile');
        }
        if ($new !== $confirm) {
            Session::flash('error', 'New passwords do not match.');
            $this->redirect('/profile');
        }
        Database::run("UPDATE users SET password=? WHERE id=?", [Auth::hash($new), Auth::id()]);
        Session::flash('success', 'Password changed successfully.');
        $this->redirect('/profile');
    }
}
