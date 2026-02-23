<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $connection;
    private $config;

    private function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    public static function getInstance(array $config = null)
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new \RuntimeException('Database configuration required for first initialization');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    private function connect()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['name'],
            $this->config['charset']
        );

        try {
            $this->connection = new PDO(
                $dsn,
                $this->config['user'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, array $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new \RuntimeException('Query failed: ' . $e->getMessage());
        }
    }

    public function fetchAll($sql, array $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, array $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function insert($table, array $data)
    {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) {
            return ':' . $field;
        }, $fields);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($data as $field => $value) {
            $params[':' . $field] = $value;
        }

        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    public function update($table, array $data, $where, array $whereParams = [])
    {
        $setParts = [];
        $params = [];

        foreach ($data as $field => $value) {
            $setParts[] = $field . ' = :set_' . $field;
            $params[':set_' . $field] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            $where
        );

        $params = array_merge($params, $whereParams);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($table, $where, array $params = [])
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollback()
    {
        return $this->connection->rollBack();
    }

    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }
}
