<?php
/**
 * Model �C generic active-record base.
 * Subclasses set $table and $fillable; all queries use PDO prepared statements.
 */
abstract class Model
{
    protected static string $table    = '';
    protected static array  $fillable = [];

    /** Fillable keys omitted from the global text search (q) OR/LIKE clause */
    protected static array $globalSearchSkip = [];

    // ���� Connection ������������������������������������������������������������������������������������������������������������������������

    protected static function db(): PDO
    {
        return Database::getInstance();
    }

    // ���� Read ������������������������������������������������������������������������������������������������������������������������������������

    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare("SELECT * FROM `" . static::$table . "` WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Fetch rows with optional WHERE (LIKE), ORDER and LIMIT/OFFSET.
     * $conditions: ['field' => 'value'] �C uses LIKE %value%
     */
    public static function all(
        array $conditions = [],
        array $order      = ['id' => 'ASC'],
        int   $limit      = 0,
        int   $offset     = 0,
        string $globalSearch = ''
    ): array {
        [$where, $params] = static::buildWhere($conditions, $globalSearch);
        $sql = "SELECT * FROM `" . static::$table . "`" . $where;
        $sql .= static::buildOrder($order);
        if ($limit > 0) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $conditions = [], string $globalSearch = ''): int
    {
        [$where, $params] = static::buildWhere($conditions, $globalSearch);
        $stmt = static::db()->prepare(
            "SELECT COUNT(*) FROM `" . static::$table . "`" . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ���� Write ����������������������������������������������������������������������������������������������������������������������������������

    public static function create(array $data): int
    {
        $data   = static::filterFillable($data);
        $fields = array_keys($data);
        $ph     = implode(', ', array_fill(0, count($fields), '?'));
        $cols   = '`' . implode('`, `', $fields) . '`';
        $stmt   = static::db()->prepare(
            "INSERT INTO `" . static::$table . "` ({$cols}) VALUES ({$ph})"
        );
        $stmt->execute(array_values($data));
        return (int) static::db()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $data = static::filterFillable($data);
        if (empty($data)) return false;
        $sets = implode(', ', array_map(fn($f) => "`{$f}` = ?", array_keys($data)));
        $stmt = static::db()->prepare(
            "UPDATE `" . static::$table . "` SET {$sets}, `updated_at` = NOW() WHERE id = ?"
        );
        $values   = array_values($data);
        $values[] = $id;
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id): bool
    {
        $stmt = static::db()->prepare("DELETE FROM `" . static::$table . "` WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function truncate(): bool
    {
        $stmt = static::db()->prepare("TRUNCATE TABLE `" . static::$table . "`");
        return $stmt->execute();
    }

    // ���� Helpers ������������������������������������������������������������������������������������������������������������������������������

    /** Keep only fillable keys; trim string values */
    protected static function filterFillable(array $data): array
    {
        $data = array_intersect_key($data, array_flip(static::$fillable));
        return array_map(fn($v) => is_string($v) ? trim($v) : $v, $data);
    }

    /**
     * Build a safe WHERE clause.
     * Only fields in $fillable are allowed (whitelist).
     */
    protected static function buildWhere(array $conditions, string $globalSearch = ''): array
    {
        $clauses = [];
        $params  = [];
        foreach ($conditions as $field => $value) {
            if (!in_array($field, static::$fillable, true)) continue;
            $value = trim((string) $value);
            if ($value === '') continue;
            if ($field === 'category_id' || str_ends_with($field, '_id')) {
                $clauses[] = "`{$field}` = ?";
                $params[]  = $value;
            } else {
                $clauses[] = "`{$field}` LIKE ?";
                $params[]  = '%' . $value . '%';
            }
        }
        
        $globalSearch = trim($globalSearch);
        if ($globalSearch !== '') {
            $globalClauses = [];
            foreach (static::$fillable as $field) {
                if (in_array($field, static::$globalSearchSkip, true)) {
                    continue;
                }
                $globalClauses[] = "`{$field}` LIKE ?";
                $params[] = '%' . $globalSearch . '%';
            }
            if (!empty($globalClauses)) {
                $clauses[] = '(' . implode(' OR ', $globalClauses) . ')';
            }
        }

        return empty($clauses)
            ? ['', []]
            : [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    protected static function buildOrder(array $order): string
    {
        if (empty($order)) return '';
        $allowed = array_merge(static::$fillable, ['id', 'created_at', 'updated_at']);
        $parts = [];
        foreach ($order as $col => $dir) {
            if (!in_array((string) $col, $allowed, true)) {
                continue;
            }
            $dir     = strtoupper((string) $dir) === 'DESC' ? 'DESC' : 'ASC';
            $parts[] = "`{$col}` {$dir}";
        }
        return empty($parts) ? '' : ' ORDER BY ' . implode(', ', $parts);
    }
}
