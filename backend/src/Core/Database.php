<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

use PDO;
use PDOStatement;

final class Database
{
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
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
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
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        self::run($sql, $data);

        return (int) self::connection()->lastInsertId();
    }

    public static function update(string $table, int $id, array $data): void
    {
        if ($data === []) {
            return;
        }

        $assignments = array_map(
            static fn (string $column): string => sprintf('`%s` = :%s', $column, $column),
            array_keys($data)
        );

        $sql = sprintf('UPDATE `%s` SET %s WHERE id = :id', $table, implode(', ', $assignments));
        self::run($sql, $data + ['id' => $id]);
    }

    public static function delete(string $table, int $id): void
    {
        self::run(sprintf('DELETE FROM `%s` WHERE id = :id', $table), ['id' => $id]);
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
