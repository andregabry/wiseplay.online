<?php

declare(strict_types=1);

namespace App\models;

use App\core\Database;
use PDO;

abstract class BaseModel
{
    protected function db(): PDO
    {
        return Database::connection();
    }
}
