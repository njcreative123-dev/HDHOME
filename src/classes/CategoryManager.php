<?php
/**
 * HDHome Live TV - Category manager.
 */

declare(strict_types=1);

final class CategoryManager
{
    public static function list(bool $withCounts = true, bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE c.is_active = 1' : '';
        $join  = $withCounts
            ? 'LEFT JOIN (SELECT category_id, COUNT(*) AS channel_count FROM channels GROUP BY category_id) cc ON cc.category_id = c.id'
            : '';

        $rows = DB::all(
            "SELECT c.*, " . ($withCounts ? 'COALESCE(cc.channel_count, 0)' : '0') . " AS channel_count
             FROM categories c $join $where
             ORDER BY c.sort_order ASC, c.name ASC"
        );

        return $rows;
    }

    public static function find(int $id): ?array
    {
        return DB::one("SELECT * FROM categories WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Category name is required.');
        }
        $slug = self::uniqueSlug($data['name']);
        return DB::insert('categories', [
            'name'       => trim($data['name']),
            'slug'       => $slug,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active'  => (int) ($data['is_active'] ?? 1),
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        $set = [];
        foreach (['name', 'sort_order', 'is_active'] as $col) {
            if (array_key_exists($col, $data)) {
                $set[$col] = match ($col) {
                    'sort_order', 'is_active' => (int) $data[$col],
                    default => trim((string) $data[$col]),
                };
            }
        }
        if (isset($set['name'])) {
            $set['slug'] = self::uniqueSlug($set['name'], $id);
        }
        return empty($set) ? false : DB::update('categories', $set, ['id' => $id]) > 0;
    }

    public static function delete(int $id): bool
    {
        // Set channel category to NULL
        DB::run("UPDATE channels SET category_id = NULL WHERE category_id = ?", [$id]);
        return DB::run("DELETE FROM categories WHERE id = ?", [$id])->rowCount() > 0;
    }

    /** Upsert by name (for M3U import). */
    public static function upsert(string $name): int
    {
        $exists = DB::one("SELECT id FROM categories WHERE name = ? LIMIT 1", [$name]);
        if ($exists) {
            return (int) $exists['id'];
        }
        return self::create(['name' => $name]);
    }

    private static function uniqueSlug(string $name, int $exceptId = 0): string
    {
        $base  = slugify($name);
        $slug  = $base;
        $index = 1;
        while (true) {
            $exists = DB::value(
                "SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1",
                [$slug, $exceptId]
            );
            if ($exists === null) {
                return $slug;
            }
            $slug = $base . '-' . ++$index;
        }
    }
}
