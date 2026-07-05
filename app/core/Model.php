<?php
namespace App\Core;

/**
 * Base Active-Record-lite model. Subclasses set $table and $fillable.
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    /** @var string[] columns allowed for mass insert/update */
    protected array $fillable = [];

    public function all(string $orderBy = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        return Database::all($sql);
    }

    public function find($id): ?array
    {
        return Database::first(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1",
            [$id]
        );
    }

    public function findBy(string $column, $value): ?array
    {
        return Database::first(
            "SELECT * FROM {$this->table} WHERE {$column} = ? LIMIT 1",
            [$value]
        );
    }

    public function where(string $column, $value): array
    {
        return Database::all("SELECT * FROM {$this->table} WHERE {$column} = ?", [$value]);
    }

    public function count(string $where = '1', array $params = []): int
    {
        return (int) Database::scalar("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", $params);
    }

    public function create(array $data): int
    {
        $data = $this->onlyFillable($data);
        $cols = array_keys($data);
        $quoted = array_map(fn($c) => "`{$c}`", $cols);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $quoted) . ") VALUES ({$placeholders})";
        return Database::insert($sql, array_values($data));
    }

    public function update($id, array $data): bool
    {
        $data = $this->onlyFillable($data);
        if (empty($data)) {
            return false;
        }
        $set = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $params = array_values($data);
        $params[] = $id;
        Database::run("UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = ?", $params);
        return true;
    }

    public function delete($id): bool
    {
        Database::run("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?", [$id]);
        return true;
    }

    /** Strip keys not present in $fillable (when defined). */
    protected function onlyFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }
}
