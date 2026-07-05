<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Auth;
use App\Core\Database;

class NotificationController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $notifications = Database::all(
            "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100",
            [Auth::id()]
        );
        $this->view('notifications/index', [
            'pageTitle'     => 'Notifications',
            'notifications' => $notifications,
        ]);
    }

    /** Mark one notification read and follow its link. */
    public function read($id): void
    {
        $this->requireAuth();
        $n = Database::first("SELECT * FROM notifications WHERE id=? AND user_id=?", [$id, Auth::id()]);
        if ($n) {
            Database::run("UPDATE notifications SET is_read=1 WHERE id=?", [$id]);
            if (!empty($n['link'])) {
                $this->redirect($n['link']);
            }
        }
        $this->redirect('/notifications');
    }

    public function readAll(): void
    {
        $this->requireAuth();
        Csrf::check();
        Database::run("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0", [Auth::id()]);
        $this->redirect('/notifications');
    }
}
