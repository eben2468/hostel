<?php
namespace App\Services;

use App\Core\Database;

/** Creates in-app notifications for users. Never throws into the request flow. */
class Notify
{
    /** Send a notification to a single user. */
    public static function send(?int $userId, string $title, string $body = '', string $link = '', string $icon = 'fa-bell'): void
    {
        if (!$userId) {
            return;
        }
        try {
            Database::run(
                "INSERT INTO notifications (user_id, title, body, icon, link) VALUES (?,?,?,?,?)",
                [$userId, $title, $body, $icon, $link]
            );
        } catch (\Throwable $e) {
            error_log('Notify failed: ' . $e->getMessage());
        }
    }

    /** Send to every active user holding the given role(s). */
    public static function toRole(array $roles, string $title, string $body = '', string $link = '', string $icon = 'fa-bell'): void
    {
        if (!$roles) {
            return;
        }
        try {
            $in = implode(',', array_fill(0, count($roles), '?'));
            $users = Database::all("SELECT id FROM users WHERE is_active=1 AND role IN ($in)", $roles);
            foreach ($users as $u) {
                self::send((int) $u['id'], $title, $body, $link, $icon);
            }
        } catch (\Throwable $e) {
            error_log('Notify::toRole failed: ' . $e->getMessage());
        }
    }

    /** Resolve a student's linked user id (or null). */
    public static function studentUserId(int $studentId): ?int
    {
        $id = Database::scalar("SELECT user_id FROM students WHERE id = ?", [$studentId]);
        return $id ? (int) $id : null;
    }

    /** Student contact details for multi-channel delivery. */
    public static function studentContact(int $studentId): array
    {
        $s = Database::first("SELECT user_id, full_name, email, phone FROM students WHERE id = ?", [$studentId]);
        return $s ?: ['user_id' => null, 'full_name' => '', 'email' => '', 'phone' => ''];
    }

    /**
     * Deliver a student-facing message across all channels: in-app notification,
     * email (when SMTP configured) and SMS (when a provider is configured).
     */
    public static function student(int $studentId, string $title, string $body, string $link = '', string $icon = 'fa-bell'): void
    {
        $c = self::studentContact($studentId);
        self::send($c['user_id'] ? (int) $c['user_id'] : null, $title, $body, $link, $icon);
        if (!empty($c['email'])) {
            Mailer::send($c['email'], $title, Mailer::template($title, '<p>' . htmlspecialchars($body) . '</p>'));
        }
        if (!empty($c['phone'])) {
            Sms::send($c['phone'], $title . ': ' . $body);
        }
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::scalar("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0", [$userId]);
    }

    public static function recent(int $userId, int $limit = 8): array
    {
        return Database::all(
            "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT " . (int) $limit,
            [$userId]
        );
    }
}
