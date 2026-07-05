<?php
namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin PDO wrapper providing a shared connection and small query helpers.
 * All access uses prepared statements.
 */
class Database
{
    private static ?PDO $pdo = null;

    /** Get (and lazily create) the shared PDO connection. */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $cfg = require ROOT_PATH . '/config/database.php';
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";
            try {
                self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
            } catch (PDOException $e) {
                if (APP_ENV === 'development') {
                    throw new RuntimeException('Database connection failed: ' . $e->getMessage());
                }
                throw new RuntimeException('Database connection failed.');
            }
        }
        return self::$pdo;
    }

    /** Run a prepared statement and return it. */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch a single row (or null). */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch all rows. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Fetch a single scalar value. */
    public static function scalar(string $sql, array $params = [])
    {
        return self::run($sql, $params)->fetchColumn();
    }

    /** Insert and return the new id. */
    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }
}
