<?php
/**
 * Product model.
 * $fillable mirrors the DB columns defined in config/columns.php.
 */
class Product extends Model
{
    protected static string $table = 'products';

    protected static array $globalSearchSkip = ['category_id', 'gallery'];

    protected static array $fillable = [
        'category_id', 'name', 'tqb_code', 'oem_number', 'production_code', 'no_stock_purchase',
        'car_series', 'car_model', 'universal_model',
        'trade_car_series', 'trade_car_model', 'trade_universal',
        'bca', 'skf', 'snr', 'timken', 'nsk', 'ntn', 'koyo',
        'dimensions', 'weight', 'inner_box_size', 'spline_teeth', 'cost',
        'original_category', 'stock_status', 'in_system', 'system_code', 'warehouse_a',
        'gallery',
        'stock_qty', 'stock_max', 'stock_min',
        'supplier1', 'supplier1_price',
        'supplier2', 'supplier2_price',
        'supplier3', 'supplier3_price',
        'supplier4', 'supplier4_price',
    ];

    /** Decode the gallery JSON column into an array of relative paths. */
    public static function parseGallery(?string $json): array
    {
        if ($json === null || $json === '') return [];
        $arr = json_decode($json, true);
        return is_array($arr) ? array_values(array_filter($arr, fn($v) => is_string($v) && $v !== '')) : [];
    }

    /** Encode an array of relative paths into a gallery JSON string (or null if empty). */
    public static function galleryJson(array $paths): ?string
    {
        $paths = array_values(array_filter($paths, fn($v) => is_string($v) && $v !== ''));
        return empty($paths) ? null : json_encode($paths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Paginated search with LIKE filters.
     *
     * @return array{rows: array, total: int, page: int, perPage: int, totalPages: int}
     */
    public static function findByTqbCode(string $tqbCode): ?array
    {
        $stmt = static::db()->prepare("SELECT * FROM `" . static::$table . "` WHERE tqb_code = ? LIMIT 1");
        $stmt->execute([trim($tqbCode)]);
        return $stmt->fetch() ?: null;
    }

    public static function search(array $filters, int $page = 1, int $perPage = 50, string $globalSearch = '', array $order = ['updated_at' => 'DESC', 'id' => 'DESC']): array
    {
        $clean = array_filter(
            $filters,
            fn($v) => is_string($v) && trim($v) !== ''
        );
        $total      = static::count($clean, $globalSearch);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $page       = max(1, min($page, max($totalPages, 1)));
        $offset     = ($page - 1) * $perPage;
        $rows       = static::all(
            $clean,
            $order,
            $perPage,
            $offset,
            $globalSearch
        );
        
        $categoryIds = array_filter(array_unique(array_column($rows, 'category_id')));
        if (!empty($categoryIds)) {
            $in = str_repeat('?,', count($categoryIds) - 1) . '?';
            $stmt = static::db()->prepare("SELECT id, name FROM categories WHERE id IN ($in)");
            $stmt->execute(array_values($categoryIds));
            $categories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($rows as &$row) {
                $row['category_name'] = $categories[$row['category_id']] ?? '';
            }
        }
        
        return compact('rows', 'total', 'page', 'perPage', 'totalPages');
    }

    /**
     * Build a row array from a CSV row (keyed by CSV header label).
     * Uses columns config to map label ? field.
     */
    public static function fromCsvRow(array $csvRow, array $columns): array
    {
        $data = [];
        foreach ($columns as $col) {
            $val          = $csvRow[$col['label']] ?? '';
            $data[$col['field']] = trim((string) $val);
        }
        return $data;
    }
}
