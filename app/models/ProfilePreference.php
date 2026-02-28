<?php

declare(strict_types=1);

namespace App\models;

final class ProfilePreference extends BaseModel
{
    public const TYPES = ['GENRE', 'TYPE', 'TOPIC'];

    public function types(): array
    {
        return self::TYPES;
    }

    public function findByProfile(int $profileId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM profile_preferences WHERE profile_id = :profile_id ORDER BY preference_type ASC, weight DESC, preference_key ASC');
        $stmt->execute([':profile_id' => $profileId]);
        return $stmt->fetchAll();
    }

    public function groupedByProfile(int $profileId): array
    {
        $rows = $this->findByProfile($profileId);
        $grouped = [
            'GENRE' => [],
            'TYPE' => [],
            'TOPIC' => [],
        ];

        foreach ($rows as $row) {
            $type = strtoupper((string) ($row['preference_type'] ?? 'GENRE'));
            if (!isset($grouped[$type])) {
                continue;
            }
            $grouped[$type][] = [
                'key' => (string) ($row['preference_key'] ?? ''),
                'weight' => (int) ($row['weight'] ?? 100),
            ];
        }

        return $grouped;
    }

    public function byProfileIds(array $profileIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $profileIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db()->prepare(
            "SELECT * FROM profile_preferences WHERE profile_id IN ({$placeholders}) ORDER BY profile_id ASC, preference_type ASC, weight DESC"
        );

        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $profileId = (int) ($row['profile_id'] ?? 0);
            if ($profileId <= 0) {
                continue;
            }
            $map[$profileId] ??= [];
            $map[$profileId][] = $row;
        }

        return $map;
    }

    public function replaceForProfile(int $profileId, array $preferences): void
    {
        $this->db()->beginTransaction();

        try {
            $delete = $this->db()->prepare('DELETE FROM profile_preferences WHERE profile_id = :profile_id');
            $delete->execute([':profile_id' => $profileId]);

            if ($preferences !== []) {
                $insert = $this->db()->prepare(
                    'INSERT INTO profile_preferences (profile_id, preference_type, preference_key, weight, created_at, updated_at)
                     VALUES (:profile_id, :preference_type, :preference_key, :weight, NOW(), NOW())'
                );

                foreach ($preferences as $item) {
                    $type = strtoupper(trim((string) ($item['type'] ?? 'GENRE')));
                    $key = trim((string) ($item['key'] ?? ''));
                    if (!in_array($type, self::TYPES, true) || $key === '') {
                        continue;
                    }

                    $insert->execute([
                        ':profile_id' => $profileId,
                        ':preference_type' => $type,
                        ':preference_key' => substr($key, 0, 120),
                        ':weight' => max(1, min(1000, (int) ($item['weight'] ?? 100))),
                    ]);
                }
            }

            $this->db()->commit();
        } catch (\Throwable $exception) {
            $this->db()->rollBack();
            throw $exception;
        }
    }

    public function userHasPreferences(int $userId): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT pp.id
               FROM profile_preferences pp
               INNER JOIN profiles p ON p.id = pp.profile_id
              WHERE p.user_id = :user_id
              LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        return (bool) $stmt->fetch();
    }
}
