<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

class Database
{
    private static ?\PDO $instance = null;

    public static function getInstance(): \PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES'",
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                Logger::log('critical', 'Database connection failed: ' . $e->getMessage());
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    die('Database connection failed: ' . $e->getMessage());
                }
                die('A database error occurred. Please try again later.');
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    public static function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        self::query($sql, $data);
        return (int) self::getInstance()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "`{$column}` = :{$column}";
        }
        $setClause = implode(', ', $setParts);
        $params = array_merge($data, $whereParams);
        $sql = "UPDATE `{$table}` SET {$setClause} WHERE {$where}";
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
