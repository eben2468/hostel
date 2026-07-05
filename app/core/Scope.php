<?php
namespace App\Core;

/**
 * Multi-hostel data isolation helper.
 *
 * Every staff/admin user is either GLOBAL (the `admin` super admin, with no
 * hostel binding) or bound to a single hostel via `users.hostel_id`. This
 * helper produces SQL WHERE fragments (+ bind params) so list, dashboard,
 * report and export queries only ever touch the caller's hostel.
 *
 * Each method returns a `[string $fragment, array $params]` tuple. Callers
 * splice the fragment into their SQL and append the params to their bind list,
 * e.g.:
 *
 *     [$scope, $bind] = Scope::on('hostel_id');
 *     $sql .= $scope;
 *     array_push($params, ...$bind);
 *
 * When the caller is global, the fragment is '' and params are [] — a no-op.
 */
class Scope
{
    /** The hostel the current user is bound to, or null for the global super admin. */
    public static function hostelId(): ?int
    {
        $id = Session::get('hostel_id');
        return $id === null ? null : (int) $id;
    }

    /** True when the current user sees every hostel (the `admin` super admin). */
    public static function isGlobal(): bool
    {
        return Auth::role() === 'admin' || self::hostelId() === null;
    }

    /**
     * Filter a table that has a direct hostel column.
     * Used for students, rooms, blocks, inventory, notices, fees, and hostels.id.
     */
    public static function on(string $column = 'hostel_id'): array
    {
        if (self::isGlobal()) {
            return ['', []];
        }
        return [" AND {$column} = ?", [self::hostelId()]];
    }

    /**
     * Filter a table linked to a hostel through the student.
     * Used for payments, invoices, applications, complaints, visitors.
     */
    public static function onStudent(string $column = 'student_id'): array
    {
        if (self::isGlobal()) {
            return ['', []];
        }
        return [" AND {$column} IN (SELECT id FROM students WHERE hostel_id = ?)", [self::hostelId()]];
    }

    /**
     * Filter a table linked to a hostel through the room.
     * Used for allocations, beds.
     */
    public static function onRoom(string $column = 'room_id'): array
    {
        if (self::isGlobal()) {
            return ['', []];
        }
        return [" AND {$column} IN (SELECT id FROM rooms WHERE hostel_id = ?)", [self::hostelId()]];
    }
}
