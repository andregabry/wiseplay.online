<?php

declare(strict_types=1);

namespace App\models;

final class Title extends BaseModel
{
    public const TYPES = ['MOVIE', 'SERIES', 'COURSE'];
    public const STATUSES = ['DRAFT', 'PUBLISHED', 'ARCHIVED'];

    public function types(): array
    {
        return self::TYPES;
    }

    public function statuses(): array
    {
        return self::STATUSES;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM titles WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findPublishedById(int $id): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM titles WHERE id = :id AND status = 'PUBLISHED' LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM titles WHERE slug = :slug';
        $params = [':slug' => $slug];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        $type = trim((string) ($filters['type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $genre = trim((string) ($filters['genre'] ?? ''));

        if ($search !== '') {
            $where[] = '(t.title_name LIKE :search OR t.slug LIKE :search OR t.description LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($type !== '') {
            $where[] = 't.type = :type';
            $params[':type'] = $type;
        }

        if ($status !== '') {
            $where[] = 't.status = :status';
            $params[':status'] = $status;
        }

        if ($genre !== '') {
            $where[] = 't.genre = :genre';
            $params[':genre'] = $genre;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM titles t WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    t.*,
                    (SELECT COUNT(*) FROM media_items m WHERE m.title_id = t.id) AS media_count
                FROM titles t
                WHERE {$whereSql}
                ORDER BY t.featured DESC, t.updated_at DESC, t.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO titles
                    (title_name, slug, type, description, genre, year, maturity_rating, featured, status, cover_image, backdrop_image, created_by_user_id, created_at, updated_at)
                VALUES
                    (:title_name, :slug, :type, :description, :genre, :year, :maturity_rating, :featured, :status, :cover_image, :backdrop_image, :created_by_user_id, NOW(), NOW())';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':title_name' => $data['title_name'],
            ':slug' => $data['slug'],
            ':type' => $data['type'],
            ':description' => $data['description'] ?: null,
            ':genre' => $data['genre'],
            ':year' => $data['year'] > 0 ? $data['year'] : null,
            ':maturity_rating' => $data['maturity_rating'],
            ':featured' => $data['featured'],
            ':status' => $data['status'],
            ':cover_image' => $data['cover_image'] ?: null,
            ':backdrop_image' => $data['backdrop_image'] ?: null,
            ':created_by_user_id' => $data['created_by_user_id'] > 0 ? $data['created_by_user_id'] : null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE titles
                   SET title_name = :title_name,
                       slug = :slug,
                       type = :type,
                       description = :description,
                       genre = :genre,
                       year = :year,
                       maturity_rating = :maturity_rating,
                       featured = :featured,
                       status = :status,
                       cover_image = :cover_image,
                       backdrop_image = :backdrop_image,
                       updated_at = NOW()
                 WHERE id = :id';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':title_name' => $data['title_name'],
            ':slug' => $data['slug'],
            ':type' => $data['type'],
            ':description' => $data['description'] ?: null,
            ':genre' => $data['genre'],
            ':year' => $data['year'] > 0 ? $data['year'] : null,
            ':maturity_rating' => $data['maturity_rating'],
            ':featured' => $data['featured'],
            ':status' => $data['status'],
            ':cover_image' => $data['cover_image'] ?: null,
            ':backdrop_image' => $data['backdrop_image'] ?: null,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM titles WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function listGenres(): array
    {
        $stmt = $this->db()->query("SELECT DISTINCT genre FROM titles WHERE genre <> '' ORDER BY genre ASC");
        return array_map(static fn (array $row): string => (string) $row['genre'], $stmt->fetchAll());
    }

    public function featuredForBrowse(?bool $kidsMode = null): ?array
    {
        $where = ["status = 'PUBLISHED'", 'featured = 1'];
        if ($kidsMode === true) {
            $where[] = "(maturity_rating IN ('Livre','10','12'))";
        }

        $sql = 'SELECT * FROM titles WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC LIMIT 1';
        $row = $this->db()->query($sql)->fetch();

        if ($row) {
            return $row;
        }

        $fallbackWhere = ["status = 'PUBLISHED'"];
        if ($kidsMode === true) {
            $fallbackWhere[] = "(maturity_rating IN ('Livre','10','12'))";
        }

        $fallbackSql = 'SELECT * FROM titles WHERE ' . implode(' AND ', $fallbackWhere) . ' ORDER BY updated_at DESC LIMIT 1';
        $fallback = $this->db()->query($fallbackSql)->fetch();
        return $fallback ?: null;
    }

    public function rowsForBrowse(?bool $kidsMode = null): array
    {
        $rows = [];

        foreach (self::TYPES as $type) {
            $rows[$type] = $this->listPublishedByType($type, $kidsMode, 18);
        }

        $genres = $this->listGenres();
        foreach ($genres as $genre) {
            $rows['GENRE:' . $genre] = $this->listPublishedByGenre($genre, $kidsMode, 18);
        }

        return $rows;
    }

    public function listPublishedByType(string $type, ?bool $kidsMode = null, int $limit = 20): array
    {
        $where = ["status = 'PUBLISHED'", 'type = :type'];
        if ($kidsMode === true) {
            $where[] = "(maturity_rating IN ('Livre','10','12'))";
        }

        $sql = 'SELECT * FROM titles WHERE ' . implode(' AND ', $where) . ' ORDER BY featured DESC, updated_at DESC LIMIT :limit';
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listPublishedByGenre(string $genre, ?bool $kidsMode = null, int $limit = 20): array
    {
        $where = ["status = 'PUBLISHED'", 'genre = :genre'];
        if ($kidsMode === true) {
            $where[] = "(maturity_rating IN ('Livre','10','12'))";
        }

        $sql = 'SELECT * FROM titles WHERE ' . implode(' AND ', $where) . ' ORDER BY featured DESC, updated_at DESC LIMIT :limit';
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':genre', $genre);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
