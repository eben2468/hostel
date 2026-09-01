<?php
namespace App\Services;

use App\Core\Database;

/**
 * Bulk-creates rooms from an uploaded sheet.
 *
 * Reads a header row and maps columns by name, so column order does not matter
 * and the common spellings all work. A room that already exists in the hostel is
 * skipped rather than duplicated, which makes re-uploading the same file safe.
 */
class RoomImporter
{
    /** Header aliases, normalised to lowercase letters+digits. */
    private const ALIASES = [
        'room_number' => ['roomnumber', 'room', 'number', 'roomno', 'roomnum'],
        'floor'       => ['floor', 'floorname', 'floorlabel', 'level'],
        'room_type'   => ['type', 'roomtype'],
        'capacity'    => ['capacity', 'beds', 'bedspaces', 'cap'],
        'price'       => ['price', 'fee', 'amount', 'cost'],
        'status'      => ['status'],
    ];

    private const TYPES    = ['single', 'double', 'triple', 'quad', 'deluxe', 'vip'];
    private const STATUSES = ['available', 'occupied', 'reserved', 'maintenance', 'closed'];

    /**
     * @param array<int,array<int,string>> $rows raw cells from SheetReader
     * @return array{created:int, beds:int, floors:int, skipped:array<int,string>, error:?string}
     */
    public static function import(array $rows, int $hostelId, bool $createFloors): array
    {
        $result = ['created' => 0, 'beds' => 0, 'floors' => 0, 'skipped' => [], 'error' => null];

        $map = self::readHeader($rows);
        if ($map === null) {
            $result['error'] = 'No header row found. The sheet needs a first row naming the columns — '
                . 'at minimum a "Room Number" column.';
            return $result;
        }
        [$headerIndex, $columns] = $map;

        // Existing numbers are pulled once; checking per row would be a query each.
        $existing = [];
        foreach (Database::all("SELECT room_number FROM rooms WHERE hostel_id = ?", [$hostelId]) as $r) {
            $existing[self::key($r['room_number'])] = true;
        }
        $floorCache = [];

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $i => $cells) {
                if ($i <= $headerIndex) {
                    continue;
                }
                $get = fn(string $field) => isset($columns[$field])
                    ? trim((string) ($cells[$columns[$field]] ?? '')) : '';

                $number = $get('room_number');
                if ($number === '') {
                    // Blank spacer rows are normal in a spreadsheet; only report a
                    // row that has content but no room number.
                    if (array_filter($cells, fn($c) => trim((string) $c) !== '')) {
                        $result['skipped'][] = 'Row ' . ($i + 1) . ': no room number.';
                    }
                    continue;
                }
                if (isset($existing[self::key($number)])) {
                    $result['skipped'][] = 'Row ' . ($i + 1) . ': room ' . $number . ' already exists.';
                    continue;
                }

                $type = strtolower($get('room_type'));
                $type = in_array($type, self::TYPES, true) ? $type : 'double';

                $status = strtolower($get('status'));
                $status = in_array($status, self::STATUSES, true) ? $status : 'available';

                $capacity = (int) preg_replace('/\D/', '', $get('capacity'));
                $capacity = max(1, min(50, $capacity ?: 1));

                $price = (float) str_replace([',', ' '], '', $get('price'));

                $floorId = null;
                if ($createFloors && ($label = $get('floor')) !== '') {
                    $floorId = self::floorId($hostelId, $label, $floorCache, $result);
                }

                $roomId = Database::insert(
                    "INSERT INTO rooms (hostel_id, floor_id, room_number, room_type, capacity, price, status)
                     VALUES (?,?,?,?,?,?,?)",
                    [$hostelId, $floorId, $number, $type, $capacity, $price, $status]
                );
                // Beds are created to match capacity, exactly as adding a room by
                // hand does — without them a room cannot be allocated.
                for ($b = 1; $b <= $capacity; $b++) {
                    Database::insert("INSERT INTO beds (room_id, bed_number) VALUES (?,?)", [$roomId, 'Bed ' . $b]);
                }
                $existing[self::key($number)] = true;
                $result['created']++;
                $result['beds'] += $capacity;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['created' => 0, 'beds' => 0, 'floors' => 0, 'skipped' => [],
                    'error' => 'Nothing was imported: ' . $e->getMessage()];
        }

        return $result;
    }

    /**
     * Locate the header row and map our field names to its column positions.
     * @return array{0:int,1:array<string,int>}|null
     */
    private static function readHeader(array $rows): ?array
    {
        foreach ($rows as $i => $cells) {
            $found = [];
            foreach ($cells as $col => $cell) {
                $norm = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $cell)));
                if ($norm === '') {
                    continue;
                }
                foreach (self::ALIASES as $field => $names) {
                    if (!isset($found[$field]) && in_array($norm, $names, true)) {
                        $found[$field] = $col;
                    }
                }
            }
            // The room number is the only column that must be present.
            if (isset($found['room_number'])) {
                return [$i, $found];
            }
            if ($i > 20) {
                break; // a header this far down means the sheet is not one of ours
            }
        }
        return null;
    }

    /** Resolve a floor label to an id within the hostel, creating it when needed. */
    private static function floorId(int $hostelId, string $label, array &$cache, array &$result): ?int
    {
        $key = strtoupper($label);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $found = Database::first(
            "SELECT f.id FROM floors f JOIN blocks b ON b.id = f.block_id
             WHERE b.hostel_id = ? AND UPPER(f.number) = ? LIMIT 1",
            [$hostelId, $key]
        );
        if ($found) {
            return $cache[$key] = (int) $found['id'];
        }

        // Floors hang off a block, so the hostel needs one before a floor exists.
        $blockId = Database::scalar("SELECT id FROM blocks WHERE hostel_id = ? ORDER BY id LIMIT 1", [$hostelId]);
        if (!$blockId) {
            $blockId = Database::insert(
                "INSERT INTO blocks (hostel_id, name, description) VALUES (?, 'Main Block', 'Created automatically by the room import')",
                [$hostelId]
            );
        }
        $id = Database::insert("INSERT INTO floors (block_id, number) VALUES (?,?)", [(int) $blockId, $label]);
        $result['floors']++;
        return $cache[$key] = $id;
    }

    /** Room numbers compare case-insensitively and ignoring spaces. */
    private static function key(string $number): string
    {
        return strtoupper(preg_replace('/\s+/', '', $number));
    }
}
