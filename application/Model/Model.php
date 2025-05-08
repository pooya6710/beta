<?php

namespace Application\Model;

use Exception;
use PDO;
use PDOException;

class Model
{
    protected static $connection;

    public function __construct()
    {
        if (!isset(self::$connection)) {
            $this->connect();
        }
    }

    private function connect()
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        try {
            // استفاده از متغیرهای محیطی PostgreSQL
            $host = $_ENV['PGHOST'] ?? 'localhost';
            $port = $_ENV['PGPORT'] ?? '5432';
            $dbname = $_ENV['PGDATABASE'] ?? 'postgres';
            $user = $_ENV['PGUSER'] ?? 'postgres';
            $password = $_ENV['PGPASSWORD'] ?? '';
            
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            self::$connection = new PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            // Consider logging the error instead of echoing it
            throw new Exception("Database connection error: " . $e->getMessage());
        }
    }

    protected function closeConnection()
    {
        self::$connection = null;
    }

    protected function query($query, $values = [])
    {
        try {
            $stmt = self::$connection->prepare($query);
            $stmt->execute($values);
            return $stmt;
        } catch (PDOException $e) {
            // Consider logging the error instead of echoing it
            throw new Exception("Query error: " . $e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->closeConnection();
    }

    public function retPDO()
    {
        return self::$connection->lastInsertId();
    }
}