<?php
declare(strict_types=1);

class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config['db_host'],
            $config['db_name']
        );
        $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data): int
    {
        $this->validateIdentifiers($table, array_keys($data));
        $cols         = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO `{$table}` ({$cols}) VALUES ({$placeholders})", array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        $this->validateIdentifiers($table, array_merge(array_keys($data), array_keys($where)));
        $set    = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $cond   = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        $params = array_merge(array_values($data), array_values($where));
        return $this->query("UPDATE `{$table}` SET {$set} WHERE {$cond}", $params)->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $this->validateIdentifiers($table, array_keys($where));
        $cond = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        return $this->query("DELETE FROM `{$table}` WHERE {$cond}", array_values($where))->rowCount();
    }

    private function validateIdentifiers(string $table, array $columns): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid table name: ' . $table);
        }
        foreach ($columns as $col) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $col)) {
                throw new \InvalidArgumentException('Invalid column name: ' . $col);
            }
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }
}
