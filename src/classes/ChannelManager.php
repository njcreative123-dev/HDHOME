<?php
/**
 * HDHome Live TV - Channel CRUD manager.
 */

declare(strict_types=1);

final class ChannelManager
{
    /* ---- Read operations -------------------------------------------------- */

    public static function list(
        ?string $search = null,
        ?int    $categoryId = null,
        ?int    $activeOnly = null,
        ?bool   $featuredOnly = null,
        string  $sort = 'sort_order',
        string  $dir = 'ASC',
        int     $page = 1,
        int     $per = 24,
    ): array {
        $where  = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[]  = '(ch.`name` LIKE ? OR ch.`description` LIKE ? OR ch.`country` LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($categoryId !== null) {
            $where[]  = 'ch.`category_id` = ?';
            $params[] = $categoryId;
        }

        if ($activeOnly !== null) {
            $where[]  = 'ch.`is_active` = ?';
            $params[] = $activeOnly;
        }

        if ($featuredOnly !== null) {
            $where[]  = 'ch.`is_featured` = ?';
            $params[] = $featuredOnly;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Validate sort column to prevent injection
        $allowedSort = ['sort_order', 'name', 'view_count', 'created_at', 'updated_at'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'sort_order';
        }
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $total = (int) DB::value(
            "SELECT COUNT(*) FROM channels ch $whereSql",
            $params
        );

        $offset = max(0, ($page - 1) * $per);

        $rows = DB::all(
            "SELECT ch.*, cat.`name` AS category_name, cat.`slug` AS category_slug
             FROM channels ch
             LEFT JOIN categories cat ON cat.`id` = ch.`category_id`
             $whereSql
             ORDER BY ch.`$sort` $dir, ch.`name` ASC
             LIMIT ? OFFSET ?",
            array_merge($params, [$per, $offset])
        );

        return [
            'channels' => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per,
            'pages'    => (int) ceil($total / $per),
        ];
    }

    public static function find(int $id): ?array
    {
        return DB::one(
            "SELECT ch.*, cat.`name` AS category_name, cat.`slug` AS category_slug
             FROM channels ch
             LEFT JOIN categories cat ON cat.`id` = ch.`category_id`
             WHERE ch.`id` = ?",
            [$id]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return DB::one(
            "SELECT ch.*, cat.`name` AS category_name, cat.`slug` AS category_slug
             FROM channels ch
             LEFT JOIN categories cat ON cat.`id` = ch.`category_id`
             WHERE ch.`slug` = ?",
            [$slug]
        );
    }

    /* ---- Write operations ------------------------------------------------- */

    public static function create(array $data): int
    {
        self::validate($data);

        $slug = self::uniqueSlug($data['name'] ?? $data['slug'] ?? 'channel');

        $id = DB::insert('channels', [
            'name'        => trim((string) ($data['name'] ?? '')),
            'slug'        => $slug,
            'description' => trim((string) ($data['description'] ?? '')),
            'category_id' => (int) ($data['category_id'] ?? 0) ?: null,
            'stream_url'  => trim((string) ($data['stream_url'] ?? '')),
            'logo_url'    => trim((string) ($data['logo_url'] ?? '')),
            'country'     => strtoupper((string) ($data['country'] ?? '')),
            'language'    => strtolower((string) ($data['language'] ?? '')),
            'is_active'   => (int) ($data['is_active'] ?? 1),
            'is_featured' => (int) ($data['is_featured'] ?? 0),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'source'      => (string) ($data['source'] ?? 'manual'),
            'extra'       => is_string($data['extra'] ?? null) ? $data['extra'] : json_encode($data['extra'] ?? []),
        ]);

        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        $current = self::find($id);
        if (!$current) {
            return false;
        }

        $allowed = [
            'name', 'description', 'category_id', 'stream_url', 'logo_url',
            'country', 'language', 'is_active', 'is_featured', 'sort_order', 'source',
        ];

        $set = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $set[$col] = match ($col) {
                    'is_active', 'is_featured', 'sort_order' => (int) $data[$col],
                    'category_id' => (int) ($data[$col] ?? 0) ?: null,
                    'country' => strtoupper((string) $data[$col]),
                    'language' => strtolower((string) $data[$col]),
                    'name' => trim((string) $data[$col]),
                    'stream_url' => trim((string) $data[$col]),
                    'logo_url' => trim((string) $data[$col]),
                    default => trim((string) $data[$col]),
                };
            }
        }

        // Update slug if name changed
        if (isset($set['name']) && $set['name'] !== $current['name']) {
            $set['slug'] = self::uniqueSlug($set['name'], $id);
        }

        if (empty($set)) {
            return false;
        }

        DB::update('channels', $set, ['id' => $id]);
        return true;
    }

    public static function delete(int $id): bool
    {
        return DB::run("DELETE FROM channels WHERE id = ?", [$id])->rowCount() > 0;
    }

    public static function toggle(int $id, string $column): bool
    {
        if (!in_array($column, ['is_active', 'is_featured'], true)) {
            return false;
        }
        DB::run(
            "UPDATE channels SET `$column` = NOT `$column` WHERE id = ?",
            [$id]
        );
        return DB::connection()->rowCount() > 0;
    }

    public static function incrementViews(int $id): void
    {
        DB::run("UPDATE channels SET view_count = view_count + 1 WHERE id = ?", [$id]);
    }

    /* ---- Bulk import from M3U -------------------------------------------- */

    public static function importFromPlaylist(
        ?string $url = null,
        ?string $content = null,
        ?int    $fallbackCategoryId = null,
        bool    $validate = false,
    ): array {
        if ($url !== null) {
            $content = (new M3U8Parser())->fetchContent($url);
        }
        if (empty($content)) {
            throw new RuntimeException('No playlist content to import.');
        }

        $parser    = new M3U8Parser();
        $channels  = $parser->parsePlaylist($content);
        $categories = $parser->extractCategories($content);

        $imported  = 0;
        $skipped   = 0;
        $errors    = [];

        // Upsert categories first
        foreach ($categories as $catName) {
            CategoryManager::upsert($catName);
        }

        foreach ($channels as $ch) {
            $streamUrl = trim($ch['stream_url'] ?? '');
            if ($streamUrl === '' || !isValidUrl($streamUrl)) {
                $errors[] = ['stream_url' => $streamUrl, 'reason' => 'Invalid URL'];
                continue;
            }

            // Check duplicate by stream URL
            $exists = DB::value(
                "SELECT id FROM channels WHERE stream_url = ? LIMIT 1",
                [$streamUrl]
            );
            if ($exists !== null) {
                $skipped++;
                continue;
            }

            // Resolve category
            $catId = $fallbackCategoryId;
            if (!empty($ch['category_name'])) {
                $cat = DB::one(
                    "SELECT id FROM categories WHERE name = ? LIMIT 1",
                    [$ch['category_name']]
                );
                if ($cat) {
                    $catId = (int) $cat['id'];
                }
            }

            try {
                self::create([
                    'name'        => $ch['name'] ?? 'Untitled',
                    'description' => $ch['description'] ?? '',
                    'category_id' => $catId,
                    'stream_url'  => $streamUrl,
                    'logo_url'    => $ch['logo'] ?? '',
                    'country'     => $ch['country'] ?? '',
                    'language'    => $ch['language'] ?? '',
                    'is_active'   => 1,
                    'source'      => $ch['source'] ?? 'm3u_import',
                ]);
                $imported++;
            } catch (Throwable $e) {
                $errors[] = ['stream_url' => $streamUrl, 'reason' => $e->getMessage()];
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    /* ---- Counts ----------------------------------------------------------- */

    public static function counts(): array
    {
        return [
            'total'    => (int) DB::value("SELECT COUNT(*) FROM channels"),
            'active'   => (int) DB::value("SELECT COUNT(*) FROM channels WHERE is_active = 1"),
            'featured' => (int) DB::value("SELECT COUNT(*) FROM channels WHERE is_featured = 1"),
            'views'    => (int) DB::value("SELECT COALESCE(SUM(view_count), 0) FROM channels"),
        ];
    }

    /* ---- Helpers ---------------------------------------------------------- */

    private static function validate(array $data): void
    {
        if (empty($data['name']) || trim((string) $data['name']) === '') {
            throw new InvalidArgumentException('Channel name is required.');
        }
        if (empty($data['stream_url']) || !isValidUrl((string) $data['stream_url'])) {
            throw new InvalidArgumentException('A valid stream URL (http/https) is required.');
        }
    }

    private static function uniqueSlug(string $name, int $exceptId = 0): string
    {
        $base  = slugify($name);
        $slug  = $base;
        $index = 1;

        while (true) {
            $exists = DB::value(
                "SELECT id FROM channels WHERE slug = ? AND id != ? LIMIT 1",
                [$slug, $exceptId]
            );
            if ($exists === null) {
                return $slug;
            }
            $slug = $base . '-' . ++$index;
        }
    }
}
