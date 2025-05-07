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
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ];
        try {
            self::$connection = new PDO("mysql:host={$_ENV['dbHostName']};dbname={$_ENV['dbName']}", $_ENV['dbUserName'], $_ENV['dbPassword'], $options);
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
