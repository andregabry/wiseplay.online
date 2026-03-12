<?php

declare(strict_types=1);

namespace App\models;

use App\core\Database;
use PDO;

abstract class BaseModel
{
    /** @var array<string, bool> */
    private static array $tableExistsCache = [];

    protected function db(): PDO
    {
        return Database::connection();
    }

    protected function tableExists(string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        if (array_key_exists($tableName, self::$tableExistsCache)) {
            return self::$tableExistsCache[$tableName];
        }

        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
               FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name = :table_name'
        );
        $stmt->execute([':table_name' => $tableName]);
        self::$tableExistsCache[$tableName] = ((int) $stmt->fetchColumn() > 0);

        return self::$tableExistsCache[$tableName];
    }
}
