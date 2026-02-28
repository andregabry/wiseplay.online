<?php

declare(strict_types=1);

namespace App\core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = config('database.host');
        $port = config('database.port');
        $name = config('database.database');
        $charset = config('database.charset', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);

        try {
            self::$pdo = new PDO($dsn, (string) config('database.username'), (string) config('database.password'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('[WisePlay][DB] ' . $e->getMessage());
            throw $e;
        }

        return self::$pdo;
    }
}
