<?php
namespace App\Models;

use App\Core\Database;

/** Key/value system settings store. */
class Setting
{
    /** Get all settings as an associative array. */
    public static function all(): array
    {
        $rows = Database::all("SELECT `key`, value FROM settings");
        $out = [];
        foreach ($rows as $r) {
            $out[$r['key']] = $r['value'];
        }
        return $out;
    }

    public static function get(string $key, $default = null)
    {
        $val = Database::scalar("SELECT value FROM settings WHERE `key` = ?", [$key]);
        return $val === false ? $default : $val;
    }

    /** Upsert a single setting. */
    public static function set(string $key, ?string $value): void
    {
        Database::run(
            "INSERT INTO settings (`key`, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$key, $value]
        );
    }
}
