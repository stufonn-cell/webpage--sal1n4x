<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

use InvalidArgumentException;
use PDO;
use PDOStatement;

/**
 * Data access. Values always travel as bound parameters of real (server-side)
 * prepared statements; table and column names, which cannot be bound, are
 * checked against a strict pattern and quoted. The few SQL fragments built in
 * code (LIMIT, LIKE patterns) go through the helpers below.
 */
final class Database
{
    /** Lower-case table and column names: the only identifiers this schema uses. */
    private const IDENTIFIER = '/^[a-z_][a-z0-9_]*$/';

    /** Highest page offset accepted; deeper pages are meaningless and could overflow. */
    private const MAX_OFFSET = 1000000000;

    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) Env::get('DB_HOST', '127.0.0.1'),
            Env::int('DB_PORT', 3306),
            (string) Env::get('DB_DATABASE', 'psiclinic'),
            (string) Env::get('DB_CHARSET', 'utf8mb4')
        );

        self::$connection = new PDO(
            $dsn,
            (string) Env::get('DB_USERNAME', 'root'),
            (string) Env::get('DB_PASSWORD', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements: the query and the values reach
                // MySQL separately, so a value can never become SQL.
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                // A single statement per call: no "1; DROP TABLE ..." stacking.
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );

        return self::$connection;
    }

    public static function disconnect(): void
    {
        self::$connection = null;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    public static function insert(string $table, array $data): int
    {
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach (array_values(array_keys($data)) as $index => $column) {
            $columns[] = self::identifier((string) $column);
            $placeholders[] = ':v' . $index;
            $params['v' . $index] = $data[$column];
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            self::identifier($table),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        self::run($sql, $params);

        return (int) self::connection()->lastInsertId();
    }

    public static function update(string $table, int $id, array $data): void
    {
        if ($data === []) {
            return;
        }

        $assignments = [];
        $params = [];

        foreach (array_values(array_keys($data)) as $index => $column) {
            $assignments[] = sprintf('%s = :v%d', self::identifier((string) $column), $index);
            $params['v' . $index] = $data[$column];
        }

        // Positional names (:v0, :v1...) and a separate :where_id: a column
        // called "id" in $data can never change which row is updated.
        $params['where_id'] = $id;

        self::run(
            sprintf('UPDATE %s SET %s WHERE `id` = :where_id', self::identifier($table), implode(', ', $assignments)),
            $params
        );
    }

    public static function delete(string $table, int $id): void
    {
        self::run(sprintf('DELETE FROM %s WHERE `id` = :where_id', self::identifier($table)), ['where_id' => $id]);
    }

    /**
     * Validates a table or column name and returns it quoted with backticks.
     * Anything outside ^[a-z_][a-z0-9_]*$ (spaces, quotes, backticks, dots,
     * comments, upper case...) is refused with an exception.
     */
    public static function identifier(string $name): string
    {
        if (strlen($name) > 64 || preg_match(self::IDENTIFIER, $name) !== 1) {
            throw new InvalidArgumentException('Invalid SQL identifier: ' . Log::clean(substr($name, 0, 64)));
        }

        return '`' . $name . '`';
    }

    /**
     * LIKE pattern for a term typed by a person. %, _ and \ are escaped so
     * they match literally; $mode is 'contains' (%term%) or 'prefix' (term%).
     */
    public static function like(string $term, string $mode = 'contains'): string
    {
        $escaped = addcslashes($term, '%_\\');

        return $mode === 'prefix' ? $escaped . '%' : '%' . $escaped . '%';
    }

    /** LIMIT/OFFSET clause from integers, clamped to sane bounds. */
    public static function limit(int $limit, int $offset = 0, int $maxLimit = 500): string
    {
        return sprintf(
            ' LIMIT %d OFFSET %d',
            max(1, min($limit, max(1, $maxLimit))),
            max(0, min($offset, self::MAX_OFFSET))
        );
    }

    /** Offset of a page without integer overflow, whatever page number arrives. */
    public static function offset(int $page, int $perPage): int
    {
        $page = max(1, min($page, intdiv(self::MAX_OFFSET, max(1, $perPage))));

        return ($page - 1) * max(1, $perPage);
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            $pdo->rollBack();

            throw $exception;
        }
    }
}
