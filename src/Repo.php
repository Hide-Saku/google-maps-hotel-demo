<?php
declare(strict_types=1);

/** DB へのアクセス。SQL はすべてプレースホルダで組み立てる（文字列連結しない）。 */
final class Repo
{
    public static function hotels(): array
    {
        return Db::pdo()->query('SELECT id, slug, name, center_lat, center_lng, zoom FROM hotels ORDER BY id')->fetchAll();
    }

    public static function hotelsWithCounts(): array
    {
        return Db::pdo()->query(
            'SELECT h.id, h.slug, h.name,
                    (SELECT COUNT(*) FROM facilities f WHERE f.hotel_id = h.id) AS facility_count
               FROM hotels h ORDER BY h.id'
        )->fetchAll();
    }

    public static function hotelBySlug(string $slug): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, slug, name, center_lat, center_lng, zoom FROM hotels WHERE slug = ?');
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    }

    public static function hotelById(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, slug, name, center_lat, center_lng, zoom FROM hotels WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** そのホテルの地図に表示するカテゴリ（キーの配列）。 */
    public static function enabledCategories(int $hotelId): array
    {
        $st = Db::pdo()->prepare('SELECT category FROM hotel_categories WHERE hotel_id = ? ORDER BY category');
        $st->execute([$hotelId]);
        return array_column($st->fetchAll(), 'category');
    }

    /**
     * 地図に表示する施設（そのホテルで有効なカテゴリのものだけ）。
     * $categories を渡すと、さらにその中で絞り込む。
     */
    public static function visibleFacilities(int $hotelId, ?array $categories = null): array
    {
        $sql = 'SELECT f.id, f.name, f.category, f.lat, f.lng, f.address, f.description, f.updated_at
                  FROM facilities f
                  JOIN hotel_categories hc ON hc.hotel_id = f.hotel_id AND hc.category = f.category
                 WHERE f.hotel_id = ?';
        $params = [$hotelId];
        if ($categories !== null) {
            if (!$categories) {
                return [];
            }
            $sql .= ' AND f.category IN (' . implode(',', array_fill(0, count($categories), '?')) . ')';
            array_push($params, ...$categories);
        }
        $st = Db::pdo()->prepare($sql . ' ORDER BY f.id');
        $st->execute($params);
        return $st->fetchAll();
    }

    /** 管理画面用: そのホテルの全施設（表示対象かどうかも返す）。 */
    public static function allFacilities(int $hotelId): array
    {
        $st = Db::pdo()->prepare(
            'SELECT f.id, f.name, f.category, f.lat, f.lng, f.address, f.source, f.external_id, f.updated_at,
                    (hc.category IS NOT NULL) AS visible
               FROM facilities f
               LEFT JOIN hotel_categories hc ON hc.hotel_id = f.hotel_id AND hc.category = f.category
              WHERE f.hotel_id = ? ORDER BY f.id'
        );
        $st->execute([$hotelId]);
        return $st->fetchAll();
    }

    public static function facility(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM facilities WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function createFacility(int $hotelId, array $c, string $source = 'manual', ?string $externalId = null): int
    {
        $st = Db::pdo()->prepare(
            'INSERT INTO facilities (hotel_id, source, external_id, name, category, lat, lng, address, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$hotelId, $source, $externalId, $c['name'], $c['category'], $c['lat'], $c['lng'], $c['address'], $c['description']]);
        return (int)Db::pdo()->lastInsertId();
    }

    public static function updateFacility(int $id, array $c): void
    {
        $st = Db::pdo()->prepare(
            'UPDATE facilities SET name = ?, category = ?, lat = ?, lng = ?, address = ?, description = ? WHERE id = ?'
        );
        $st->execute([$c['name'], $c['category'], $c['lat'], $c['lng'], $c['address'], $c['description'], $id]);
    }

    public static function deleteFacility(int $id): void
    {
        $st = Db::pdo()->prepare('DELETE FROM facilities WHERE id = ?');
        $st->execute([$id]);
    }

    public static function updateHotel(int $id, array $c): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('UPDATE hotels SET name = ?, center_lat = ?, center_lng = ?, zoom = ? WHERE id = ?');
            $st->execute([$c['name'], $c['center_lat'], $c['center_lng'], $c['zoom'], $id]);
            $pdo->prepare('DELETE FROM hotel_categories WHERE hotel_id = ?')->execute([$id]);
            $ins = $pdo->prepare('INSERT INTO hotel_categories (hotel_id, category) VALUES (?, ?)');
            foreach ($c['categories'] as $cat) {
                $ins->execute([$id, $cat]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function adminByUsername(string $username): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ?');
        $st->execute([$username]);
        return $st->fetch() ?: null;
    }

    public static function createAdmin(string $username, string $hash): void
    {
        Db::pdo()->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')->execute([$username, $hash]);
    }

    public static function count(string $table): int
    {
        if (!in_array($table, ['hotels', 'facilities', 'admin_users', 'import_runs'], true)) {
            throw new InvalidArgumentException('table not allowed');
        }
        return (int)Db::pdo()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
}
