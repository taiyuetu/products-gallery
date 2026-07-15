<?php
/**
 * Product model – tenant-scoped; dynamic attrs JSON.
 */
class Product extends Model
{
    protected static string $table = 'products';
    protected static bool   $tenantScoped = true;

    protected static array $globalSearchSkip = ['category_id', 'gallery', 'attrs', 'user_id'];

    protected static array $fillable = [
        'user_id', 'category_id', 'primary_value', 'oem_value', 'gallery', 'attrs',
    ];

    public static function parseGallery(?string $json): array
    {
        if ($json === null || $json === '') return [];
        $arr = json_decode($json, true);
        return is_array($arr) ? array_values(array_filter($arr, fn($v) => is_string($v) && $v !== '')) : [];
    }

    public static function galleryJson(array $paths): ?string
    {
        $paths = array_values(array_filter($paths, fn($v) => is_string($v) && $v !== ''));
        return empty($paths) ? null : json_encode($paths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function parseAttrs(mixed $attrs): array
    {
        if (is_array($attrs)) return $attrs;
        if ($attrs === null || $attrs === '') return [];
        $arr = json_decode((string) $attrs, true);
        return is_array($arr) ? $arr : [];
    }

    public static function attrsJson(array $attrs): string
    {
        return json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Flatten product row for views: attrs keys as top-level fields. */
    public static function hydrate(array $row): array
    {
        $attrs = self::parseAttrs($row['attrs'] ?? null);
        $row['attrs'] = $attrs;
        foreach ($attrs as $k => $v) {
            if (!array_key_exists($k, $row)) {
                $row[$k] = $v;
            }
        }
        return $row;
    }

    public static function findByPrimary(int $userId, string $primaryValue): ?array
    {
        $primaryValue = trim($primaryValue);
        if ($primaryValue === '') return null;
        $stmt = static::db()->prepare(
            "SELECT * FROM products WHERE user_id = ? AND primary_value = ? LIMIT 1"
        );
        $stmt->execute([$userId, $primaryValue]);
        $row = $stmt->fetch();
        return $row ? self::hydrate($row) : null;
    }

    public static function search(
        int $userId,
        array $filters,
        int $page = 1,
        int $perPage = 50,
        string $globalSearch = '',
        array $order = ['updated_at' => 'DESC', 'id' => 'DESC'],
        array $columns = []
    ): array {
        [$where, $params] = self::buildProductWhere($userId, $filters, $globalSearch, $columns);
        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM products" . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $page = max(1, min($page, max($totalPages, 1)));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM products" . $where . static::buildOrder($order);
        if ($perPage > 0) {
            $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        }
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        $rows = array_map([self::class, 'hydrate'], $stmt->fetchAll());

        $categoryIds = array_filter(array_unique(array_column($rows, 'category_id')));
        if (!empty($categoryIds)) {
            $in = str_repeat('?,', count($categoryIds) - 1) . '?';
            $cStmt = static::db()->prepare(
                "SELECT id, name FROM categories WHERE user_id = ? AND id IN ($in)"
            );
            $cStmt->execute(array_merge([$userId], array_values($categoryIds)));
            $categories = $cStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($rows as &$row) {
                $row['category_name'] = $categories[$row['category_id']] ?? '';
            }
            unset($row);
        }

        return compact('rows', 'total', 'page', 'perPage', 'totalPages');
    }

    private static function buildProductWhere(
        int $userId,
        array $filters,
        string $globalSearch,
        array $columns
    ): array {
        $clauses = ['`user_id` = ?'];
        $params  = [$userId];

        $fieldKeys = array_column($columns, 'field');
        foreach ($filters as $field => $value) {
            $value = trim((string) $value);
            if ($value === '') continue;
            if ($field === 'category_id') {
                $clauses[] = '`category_id` = ?';
                $params[]  = $value;
                continue;
            }
            if ($field === 'primary_value') {
                $clauses[] = '`primary_value` LIKE ?';
                $params[]  = '%' . $value . '%';
                continue;
            }
            if (in_array($field, $fieldKeys, true)) {
                // MySQL JSON extract – works on 5.7+ / 8
                $clauses[] = "JSON_UNQUOTE(JSON_EXTRACT(`attrs`, ?)) LIKE ?";
                $params[]  = '$.' . $field;
                $params[]  = '%' . $value . '%';
            }
        }

        $globalSearch = trim($globalSearch);
        if ($globalSearch !== '') {
            $g = [];
            $g[] = '`primary_value` LIKE ?';
            $params[] = '%' . $globalSearch . '%';
            $g[] = '`oem_value` LIKE ?';
            $params[] = '%' . $globalSearch . '%';
            $g[] = 'CAST(`attrs` AS CHAR) LIKE ?';
            $params[] = '%' . $globalSearch . '%';
            $clauses[] = '(' . implode(' OR ', $g) . ')';
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    public static function createForUser(int $userId, array $data): int
    {
        $data['user_id'] = $userId;
        if (isset($data['attrs']) && is_array($data['attrs'])) {
            $data['attrs'] = self::attrsJson($data['attrs']);
        }
        return static::create($data);
    }

    public static function updateAttrs(int $userId, int $id, array $data): bool
    {
        if (isset($data['attrs']) && is_array($data['attrs'])) {
            $data['attrs'] = self::attrsJson($data['attrs']);
        }
        return static::updateForUser($userId, $id, $data);
    }

    /** Build attrs from form POST product[field] using column defs. */
    public static function attrsFromForm(array $posted, array $columns): array
    {
        $attrs = [];
        $primary = '';
        $oem = '';
        foreach ($columns as $col) {
            $val = trim((string) ($posted[$col['field']] ?? ''));
            $attrs[$col['field']] = $val;
            if (!empty($col['is_primary'])) $primary = $val;
            if (!empty($col['is_oem'])) $oem = $val;
        }
        return [$attrs, $primary, $oem];
    }
}
