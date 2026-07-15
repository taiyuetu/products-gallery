<?php
/**
 * Category model – tenant-scoped.
 */
class Category extends Model
{
    protected static string $table = 'categories';
    protected static bool   $tenantScoped = true;

    protected static array $fillable = [
        'user_id', 'name', 'description',
    ];

    public static function search(int $userId, array $filters, int $page = 1, int $perPage = 50, string $globalSearch = ''): array
    {
        $clean = array_filter(
            $filters,
            fn($v) => is_string($v) && trim($v) !== ''
        );
        $total      = static::count($clean, $globalSearch, $userId);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $page       = max(1, min($page, max($totalPages, 1)));
        $offset     = ($page - 1) * $perPage;
        $rows       = static::all($clean, ['name' => 'ASC'], $perPage, $offset, $globalSearch, $userId);

        $catIds = array_column($rows, 'id');
        if (!empty($catIds)) {
            $in = str_repeat('?,', count($catIds) - 1) . '?';
            $stmt = static::db()->prepare(
                "SELECT category_id, COUNT(id) as cnt FROM products WHERE user_id = ? AND category_id IN ($in) GROUP BY category_id"
            );
            $stmt->execute(array_merge([$userId], array_values($catIds)));
            $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($rows as &$row) {
                $row['product_count'] = $counts[$row['id']] ?? 0;
            }
            unset($row);
        }

        return compact('rows', 'total', 'page', 'perPage', 'totalPages');
    }

    public static function allForUser(int $userId): array
    {
        return static::all([], ['name' => 'ASC'], 0, 0, '', $userId);
    }
}
