<?php

declare(strict_types=1);

namespace App\models;

use PDO;

final class SettingProfile extends BaseModel
{
    private static bool $schemaEnsured = false;

    public const CATEGORIES = [
        'api_connect' => 'API Connect',
        'email' => 'Email',
        'general' => 'General',
        'payment' => 'Payment',
    ];

    public function all(array $filters = []): array
    {
        $this->ensureSchema();

        $where = ['1 = 1'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));

        if ($q !== '') {
            $where[] = '(description LIKE :q OR category LIKE :q OR config_name LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        if ($category !== '') {
            $where[] = 'category = :category';
            $params[':category'] = $category;
        }

        $sql = 'SELECT * FROM setting_profiles WHERE ' . implode(' AND ', $where) . ' ORDER BY category ASC, is_default DESC, id DESC';
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $this->ensureSchema();

        $stmt = $this->db()->prepare('SELECT * FROM setting_profiles WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->ensureSchema();

        $this->db()->beginTransaction();
        try {
            if ((int) ($data['is_default'] ?? 0) === 1) {
                $this->clearDefaultByCategory((string) $data['category']);
            }

            $stmt = $this->db()->prepare(
                'INSERT INTO setting_profiles (category, config_name, description, payload_json, is_default, is_active, created_at, updated_at)
                 VALUES (:category, :config_name, :description, :payload_json, :is_default, :is_active, NOW(), NOW())'
            );
            $stmt->execute([
                ':category' => $data['category'],
                ':config_name' => $data['config_name'],
                ':description' => $data['description'],
                ':payload_json' => $data['payload_json'],
                ':is_default' => (int) ($data['is_default'] ?? 0),
                ':is_active' => (int) ($data['is_active'] ?? 1),
            ]);

            $id = (int) $this->db()->lastInsertId();
            $this->db()->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }
    }

    public function update(int $id, array $data): void
    {
        $this->ensureSchema();

        $current = $this->findById($id);
        if (!$current) {
            throw new \RuntimeException('Config not found');
        }

        $this->db()->beginTransaction();
        try {
            $category = (string) ($data['category'] ?? $current['category']);
            $isDefault = (int) ($data['is_default'] ?? 0);

            if ($isDefault === 1) {
                $this->clearDefaultByCategory($category);
            }

            $stmt = $this->db()->prepare(
                'UPDATE setting_profiles
                    SET category = :category,
                        config_name = :config_name,
                        description = :description,
                        payload_json = :payload_json,
                        is_default = :is_default,
                        is_active = :is_active,
                        updated_at = NOW()
                  WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':category' => $category,
                ':config_name' => $data['config_name'],
                ':description' => $data['description'],
                ':payload_json' => $data['payload_json'],
                ':is_default' => $isDefault,
                ':is_active' => (int) ($data['is_active'] ?? 1),
            ]);

            $this->db()->commit();
        } catch (\Throwable $e) {
            if ($this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }
    }

    public function setDefault(int $id): void
    {
        $this->ensureSchema();

        $target = $this->findById($id);
        if (!$target) {
            throw new \RuntimeException('Config not found');
        }

        $category = (string) $target['category'];

        $this->db()->beginTransaction();
        try {
            $this->clearDefaultByCategory($category);

            $stmt = $this->db()->prepare('UPDATE setting_profiles SET is_default = 1, updated_at = NOW() WHERE id = :id');
            $stmt->execute([':id' => $id]);

            $this->db()->commit();
        } catch (\Throwable $e) {
            if ($this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $this->ensureSchema();

        $stmt = $this->db()->prepare('DELETE FROM setting_profiles WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    private function clearDefaultByCategory(string $category): void
    {
        $stmt = $this->db()->prepare('UPDATE setting_profiles SET is_default = 0, updated_at = NOW() WHERE category = :category');
        $stmt->execute([':category' => $category]);
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $this->db()->exec(
            'CREATE TABLE IF NOT EXISTS setting_profiles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category VARCHAR(60) NOT NULL,
                config_name VARCHAR(120) NULL,
                description VARCHAR(255) NOT NULL,
                payload_json LONGTEXT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_setting_profiles_category (category),
                INDEX idx_setting_profiles_default (category, is_default),
                INDEX idx_setting_profiles_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        self::$schemaEnsured = true;
    }
}
