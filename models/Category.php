<?php
/**
 * Category model.
 */
class Category extends Model
{
    protected static string $table = 'categories';

    protected static array $fillable = [
        'name', 'description'
    ];

    /**
     * Paginated search with LIKE filters.
     */
    public static function search(array $filters, int $page = 1, int $perPage = 50, string $globalSearch = ''): array
    {
        $clean = array_filter(
            $filters,
            fn($v) => is_string($v) && trim($v) !== ''
        );
        $total      = static::count($clean, $globalSearch);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $page       = max(1, min($page, max($totalPages, 1)));
        $offset     = ($page - 1) * $perPage;
        $rows       = static::all($clean, ['id' => 'ASC'], $perPage, $offset, $globalSearch);
        
        $catIds = array_column($rows, 'id');
        if (!empty($catIds)) {
            $in = str_repeat('?,', count($catIds) - 1) . '?';
            $stmt = static::db()->prepare("SELECT category_id, COUNT(id) as cnt FROM products WHERE category_id IN ($in) GROUP BY category_id");
            $stmt->execute(array_values($catIds));
            $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($rows as &$row) {
                $row['product_count'] = $counts[$row['id']] ?? 0;
            }
        }
        
        return compact('rows', 'total', 'page', 'perPage', 'totalPages');
    }
}
