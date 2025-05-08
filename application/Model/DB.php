<?php

namespace Application\Model;

class DB extends Model
{
    protected $table;
    protected $query;
    protected $bindings = [];
    protected $wheres = [];

    public static function table($name)
    {
        $instance = new self();
        $instance->table = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
        return $instance;
    }

    public function insert(array $data)
    {
        $columns = implode(', ', array_map([$this, 'sanitizeColumn'], array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $query = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $this->query($query, array_values($data));
        return $this->retPDO();
    }

    public function delete()
    {
        if (empty($this->wheres)) {
            throw new \Exception("Delete operations require a where clause to prevent accidental deletions.");
        }

        $conditionString = $this->buildConditions();
        $query = "DELETE FROM {$this->table} WHERE {$conditionString}";
        $this->query($query, $this->bindings);
        return $this;
    }

    public function update(array $data)
    {
        if (empty($this->wheres)) {
            throw new \Exception("Update operations require a where clause to prevent accidental updates.");
        }

        $setString = implode(', ', array_map(function($column) {
            return $this->sanitizeColumn($column) . " = ?";
        }, array_keys($data)));

        $conditionString = $this->buildConditions();
        $query = "UPDATE {$this->table} SET {$setString} WHERE {$conditionString}";

        $this->query($query, array_merge(array_values($data), $this->bindings));
        return $this;
    }


    public function where($conditions , $sec = null)
    {
        if (isset($sec)){
            $this->wheres[$conditions] = $sec;
            $this->bindings[] = $sec;
        }else{
            foreach ($conditions as $key => $value) {
                $this->wheres[$key] = $value;
                $this->bindings[] = $value;
            }
        }
        return $this;
    }


    public function select($columns = '*')
    {
        if ($columns !== '*') {
            $columns = implode(', ', array_map([$this, 'sanitizeColumn'], explode(',', $columns)));
        }
        $this->query = "SELECT {$columns} FROM {$this->table}";

        if (!empty($this->wheres)) {
            $conditionString = $this->buildConditions();
            $this->query .= " WHERE {$conditionString}";
        }

        return $this;
    }

    public function first()
    {
        // اگر کوئری هنوز ایجاد نشده است، یک کوئری SELECT ایجاد می‌کنیم
        if (empty($this->query)) {
            $this->select('*');
        }
        
        $this->query .= " LIMIT 1";
        return $this->get()[0] ?? null;
    }

    public function get()
    {
        $stmt = $this->query($this->query, $this->bindings);
        return $stmt->fetchAll();
    }

    public static function rawQuery($query, $bindings = [])
    {
        $instance = new self();  // Create an instance of the DB class
        return $instance->executeRawQuery($query, $bindings);  // Call the instance method
    }

    // Instance method to execute the raw query
    protected function executeRawQuery($query, $bindings = [])
    {
        $this->query = $query;
        $this->bindings = $bindings;

        $stmt = $this->query($this->query, $this->bindings);  // Execute the raw query
        return $stmt->fetchAll();  // Return the result
    }

    private function buildConditions()
    {
        return implode(' AND ', array_map(fn($k) => $this->sanitizeColumn($k) . " = ?", array_keys($this->wheres)));
    }

    private function sanitizeColumn($column)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    }
}