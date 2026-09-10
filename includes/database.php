<?php
/**
 * HDHome Live TV - PDO database layer.
 *
 * A tiny singleton wrapper around PDO with convenient helpers:
 *   DB::run($sql, $params)         -> PDOStatement
 *   DB::one($sql, $params)         -> first row (assoc) or null
 *   DB::value($sql, $params)       -> first column value or null
 *   DB::all($sql, $params)         -> all rows
 *   DB::insert($table, $data)      -> last insert id
 *   DB::update($table, $data, $where)
 */

declare(strict_types=1);

final class DB
{
    private static ?PDO $pdo = null;
    private static int $queries = 0;

    /** Returns the shared PDO connection (lazily created). */
    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $c = config();
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $c['db']['host'],
            $c['db']['name'],
            $c['db']['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $c['db']['user'], $c['db']['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$c['db']['charset']} COLLATE {$c['db']['charset']}_unicode_ci",
            ]);
        } catch (PDOException $e) {
            logError('database.connection', $e->getMessage());
            throw new RuntimeException('Database connection failed. Check your .env DB_* values.');
        }

        return self::$pdo;
    }

    /** Prepare + execute a statement with bound params. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        self::$queries++;
        return $stmt;
    }

    /** Fetch the first row as an assoc array, or null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch a single column value, or null. */
    public static function value(string $sql, array $params = []): mixed
    {
        $val = self::run($sql, $params)->fetchColumn();
        return $val === false ? null : $val;
    }

    /** Fetch every row. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Simple INSERT helper. Returns the new auto-increment id. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`,`', $cols),
            implode(',', array_fill(0, count($cols), '?'))
        );
        self::run($sql, array_values($data));
        return (int) self::connection()->lastInsertId();
    }

    /** Simple UPDATE helper (only for trusted where columns). */
    public static function update(string $table, array $data, array $where): int
    {
        $set    = implode(',', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $clause = implode(' AND ', array_map(fn($k) => "`$k` = ?", array_keys($where)));
        return (int) self::run(
            "UPDATE `$table` SET $set WHERE $clause",
            array_merge(array_values($data), array_values($where))
        )->rowCount();
    }

    /** Execute a statement inside a transaction with automatic rollback. */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Total queries executed in this request (diagnostics). */
    public static function queryCount(): int
    {
        return self::$queries;
    }
}
