<?php

declare(strict_types=1);

namespace App\models;

final class Setting extends BaseModel
{
    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM settings WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByKey(string $key): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM settings WHERE key_name = :key_name LIMIT 1');
        $stmt->execute([':key_name' => $key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function value(string $key, ?string $default = null): ?string
    {
        $row = $this->findByKey($key);
        if (!$row) {
            return $default;
        }

        $value = (string) ($row['value_text'] ?? '');
        return $value !== '' ? $value : $default;
    }

    public function paginate(array $filters, int $page, int $perPage, array $options = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));

        if ($q !== '') {
            $where[] = '(key_name LIKE :q OR value_text LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        if ($category !== '') {
            $where[] = 'category = :category';
            $params[':category'] = $category;
        }

        $excludeKeyPrefixes = $options['exclude_key_prefixes'] ?? [];
        if (is_array($excludeKeyPrefixes)) {
            foreach (array_values($excludeKeyPrefixes) as $idx => $prefix) {
                $prefix = trim((string) $prefix);
                if ($prefix === '') {
                    continue;
                }

                $paramName = ':exclude_prefix_' . $idx;
                $where[] = "key_name NOT LIKE {$paramName}";
                $params[$paramName] = $prefix . '%';
            }
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM settings WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $safePerPage = max(1, $perPage);
        $safeOffset = max(0, ($page - 1) * $safePerPage);

        $sql = "SELECT * FROM settings
                WHERE {$whereSql}
                ORDER BY category ASC, key_name ASC
                LIMIT {$safePerPage} OFFSET {$safeOffset}";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function categories(): array
    {
        $stmt = $this->db()->query('SELECT DISTINCT category FROM settings ORDER BY category ASC');
        return array_map(static fn (array $row): string => (string) $row['category'], $stmt->fetchAll());
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare('INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
                                      VALUES (:category, :key_name, :value_text, :is_secret, NOW(), NOW())');
        $stmt->execute([
            ':category' => $data['category'],
            ':key_name' => $data['key_name'],
            ':value_text' => $data['value_text'],
            ':is_secret' => $data['is_secret'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db()->prepare('UPDATE settings
                                         SET category = :category,
                                             key_name = :key_name,
                                             value_text = :value_text,
                                             is_secret = :is_secret,
                                             updated_at = NOW()
                                       WHERE id = :id');

        $stmt->execute([
            ':id' => $id,
            ':category' => $data['category'],
            ':key_name' => $data['key_name'],
            ':value_text' => $data['value_text'],
            ':is_secret' => $data['is_secret'],
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM settings WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function upsert(string $category, string $keyName, string $valueText, bool $isSecret = false): void
    {
        $existing = $this->findByKey($keyName);
        if ($existing) {
            $this->update((int) $existing['id'], [
                'category' => $category,
                'key_name' => $keyName,
                'value_text' => $valueText,
                'is_secret' => $isSecret ? 1 : 0,
            ]);
            return;
        }

        $this->create([
            'category' => $category,
            'key_name' => $keyName,
            'value_text' => $valueText,
            'is_secret' => $isSecret ? 1 : 0,
        ]);
    }

    public function grouped(): array
    {
        $stmt = $this->db()->query('SELECT * FROM settings ORDER BY category ASC, key_name ASC');
        $items = $stmt->fetchAll();

        $grouped = [];
        foreach ($items as $item) {
            $category = (string) $item['category'];
            $grouped[$category] ??= [];
            $grouped[$category][] = $item;
        }

        return $grouped;
    }
}
