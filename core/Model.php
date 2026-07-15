<?php
/**
 * Model – generic active-record base with optional user scoping.
 */
abstract class Model
{
    protected static string $table    = '';
    protected static array  $fillable = [];
    protected static array  $globalSearchSkip = [];
    /** When true, all()/count()/findForUser require user_id scoping */
    protected static bool   $tenantScoped = false;

    protected static function db(): PDO
    {
        return Database::getInstance();
    }

    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare("SELECT * FROM `" . static::$table . "` WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findForUser(int $userId, int $id): ?array
    {
        $stmt = static::db()->prepare(
            "SELECT * FROM `" . static::$table . "` WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function all(
        array $conditions = [],
        array $order      = ['id' => 'ASC'],
        int   $limit      = 0,
        int   $offset     = 0,
        string $globalSearch = '',
        ?int  $userId = null
    ): array {
        [$where, $params] = static::buildWhere($conditions, $globalSearch, $userId);
        $sql = "SELECT * FROM `" . static::$table . "`" . $where;
        $sql .= static::buildOrder($order);
        if ($limit > 0) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $conditions = [], string $globalSearch = '', ?int $userId = null): int
    {
        [$where, $params] = static::buildWhere($conditions, $globalSearch, $userId);
        $stmt = static::db()->prepare(
            "SELECT COUNT(*) FROM `" . static::$table . "`" . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function create(array $data): int
    {
        $data   = static::filterFillable($data);
        $fields = array_keys($data);
        $ph     = implode(', ', array_fill(0, count($fields), '?'));
        $cols   = '`' . implode('`, `', $fields) . '`';
        $stmt   = static::db()->prepare(
            "INSERT INTO `" . static::$table . "` ({$cols}) VALUES ({$ph})"
        );
        $values = array_map([static::class, 'normalizeValue'], array_values($data));
        $stmt->execute($values);
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
        $values   = array_map([static::class, 'normalizeValue'], array_values($data));
        $values[] = $id;
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    public static function updateForUser(int $userId, int $id, array $data): bool
    {
        $data = static::filterFillable($data);
        if (empty($data)) return false;
        $sets = implode(', ', array_map(fn($f) => "`{$f}` = ?", array_keys($data)));
        $stmt = static::db()->prepare(
            "UPDATE `" . static::$table . "` SET {$sets}, `updated_at` = NOW() WHERE id = ? AND user_id = ?"
        );
        $values   = array_map([static::class, 'normalizeValue'], array_values($data));
        $values[] = $id;
        $values[] = $userId;
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id): bool
    {
        $stmt = static::db()->prepare("DELETE FROM `" . static::$table . "` WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function deleteForUser(int $userId, int $id): bool
    {
        $stmt = static::db()->prepare(
            "DELETE FROM `" . static::$table . "` WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function truncateForUser(int $userId): bool
    {
        $stmt = static::db()->prepare("DELETE FROM `" . static::$table . "` WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }

    protected static function normalizeValue(mixed $v): mixed
    {
        if (is_array($v)) {
            return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $v;
    }

    protected static function filterFillable(array $data): array
    {
        $data = array_intersect_key($data, array_flip(static::$fillable));
        return array_map(fn($v) => is_string($v) ? trim($v) : $v, $data);
    }

    protected static function buildWhere(array $conditions, string $globalSearch = '', ?int $userId = null): array
    {
        $clauses = [];
        $params  = [];

        if ($userId !== null) {
            $clauses[] = '`user_id` = ?';
            $params[]  = $userId;
        } elseif (static::$tenantScoped) {
            throw new RuntimeException(static::$table . ' queries require user_id scope');
        }

        foreach ($conditions as $field => $value) {
            if (!in_array($field, static::$fillable, true) && $field !== 'id') {
                continue;
            }
            $value = trim((string) $value);
            if ($value === '') continue;
            if ($field === 'category_id' || $field === 'user_id' || str_ends_with($field, '_id')) {
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
                if ($field === 'user_id' || $field === 'category_id' || $field === 'attrs' || $field === 'gallery') {
                    continue;
                }
                $globalClauses[] = "`{$field}` LIKE ?";
                $params[] = '%' . $globalSearch . '%';
            }
            // Also search JSON attrs blob
            if (in_array('attrs', static::$fillable, true)) {
                $globalClauses[] = 'CAST(`attrs` AS CHAR) LIKE ?';
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
        $allowed = array_merge(static::$fillable, ['id', 'created_at', 'updated_at', 'primary_value', 'oem_value']);
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
