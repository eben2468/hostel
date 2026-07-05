<?php
namespace App\Core;

/** Writes entries to the audit_logs table. */
class Audit
{
    public static function log(string $action, string $module = null, $recordId = null, string $details = null): void
    {
        try {
            Database::run(
                "INSERT INTO audit_logs (user_id, role, action, module, record_id, details, ip_address)
                 VALUES (?,?,?,?,?,?,?)",
                [
                    Auth::id(),
                    Auth::role(),
                    $action,
                    $module,
                    $recordId !== null ? (string) $recordId : null,
                    $details,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            // Auditing must never break the request.
            error_log('Audit failed: ' . $e->getMessage());
        }
    }
}
