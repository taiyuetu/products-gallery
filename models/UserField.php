<?php
/**
 * UserField – per-user dynamic column definitions (CSV-driven schema).
 */
class UserField extends Model
{
    protected static string $table = 'user_fields';
    protected static bool   $tenantScoped = true;

    protected static array $fillable = [
        'user_id', 'field_key', 'label', 'type', 'filterable', 'list', 'active',
        'is_primary', 'is_oem', 'tab', 'sort_order',
    ];

    public static function ensureTable(): void
    {
        static::db()->exec("
            CREATE TABLE IF NOT EXISTS `user_fields` (
              `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
              `user_id`      INT UNSIGNED  NOT NULL,
              `field_key`    VARCHAR(64)   NOT NULL,
              `label`        VARCHAR(255)  NOT NULL,
              `type`         VARCHAR(20)   NOT NULL DEFAULT 'text',
              `filterable`   TINYINT(1)    NOT NULL DEFAULT 0,
              `list`         TINYINT(1)    NOT NULL DEFAULT 0,
              `active`       TINYINT(1)    NOT NULL DEFAULT 1,
              `is_primary`   TINYINT(1)    NOT NULL DEFAULT 0,
              `is_oem`       TINYINT(1)    NOT NULL DEFAULT 0,
              `tab`          VARCHAR(100)  NOT NULL DEFAULT '字段',
              `sort_order`   INT           NOT NULL DEFAULT 0,
              `created_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
              `updated_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_user_field_key` (`user_id`, `field_key`),
              KEY `idx_user_fields_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /** View/import shape matching the old columns.php entries. */
    public static function forUser(int $userId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM `user_fields` WHERE user_id = ?";
        $params = [$userId];
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'         => (int) $r['id'],
                'field'      => $r['field_key'],
                'label'      => $r['label'],
                'type'       => $r['type'] ?: 'text',
                'filterable' => (bool) $r['filterable'],
                'list'       => (bool) $r['list'],
                'active'     => (bool) $r['active'],
                'is_primary' => (bool) $r['is_primary'],
                'is_oem'     => (bool) $r['is_oem'],
                'tab'        => $r['tab'] ?: '字段',
                'sort_order' => (int) $r['sort_order'],
            ];
        }
        return $out;
    }

    public static function rawForUser(int $userId): array
    {
        $stmt = static::db()->prepare(
            "SELECT * FROM `user_fields` WHERE user_id = ? ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function primaryField(int $userId): ?array
    {
        foreach (self::forUser($userId, true) as $col) {
            if (!empty($col['is_primary'])) return $col;
        }
        $cols = self::forUser($userId, true);
        return $cols[0] ?? null;
    }

    public static function oemField(int $userId): ?array
    {
        foreach (self::forUser($userId, true) as $col) {
            if (!empty($col['is_oem'])) return $col;
        }
        return null;
    }

    public static function slugify(string $label): string
    {
        $key = mb_strtolower(trim($label));
        $key = preg_replace('/\s+/u', '_', $key) ?? $key;
        $key = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $key) ?? $key;
        $key = trim($key, '_');
        if ($key === '') $key = 'field';
        if (preg_match('/^\d/', $key)) $key = 'f_' . $key;
        return substr($key, 0, 64);
    }

    /**
     * Sync field definitions from CSV headers.
     * Adds new labels; never deletes existing fields.
     * On first schema: first header = primary; OEM-like header = is_oem.
     *
     * @return array columns forUser() shape after sync
     */
    public static function syncFromCsvHeaders(int $userId, array $headers): array
    {
        $headers = array_values(array_filter(array_map('trim', $headers), fn($h) => $h !== ''));
        if (empty($headers)) {
            return self::forUser($userId, false);
        }

        $existing = self::rawForUser($userId);
        $byLabel  = [];
        foreach ($existing as $row) {
            $byLabel[mb_strtolower($row['label'])] = $row;
        }
        $isFirstSchema = empty($existing);
        $maxOrder = 0;
        foreach ($existing as $row) {
            $maxOrder = max($maxOrder, (int) $row['sort_order']);
        }

        $usedKeys = array_column($existing, 'field_key');
        $oemMarked = false;
        foreach ($existing as $row) {
            if ((int) $row['is_oem'] === 1) $oemMarked = true;
        }

        foreach ($headers as $i => $label) {
            $labelKey = mb_strtolower($label);
            if (isset($byLabel[$labelKey])) {
                continue;
            }

            $base = self::slugify($label);
            $key  = $base;
            $n = 2;
            while (in_array($key, $usedKeys, true)) {
                $key = substr($base, 0, 60) . '_' . $n;
                $n++;
            }
            $usedKeys[] = $key;
            $maxOrder++;

            $isPrimary = $isFirstSchema && $i === 0;
            $isOem = false;
            if ($isFirstSchema && !$oemMarked && self::looksLikeOem($label)) {
                $isOem = true;
                $oemMarked = true;
            }

            // First few columns default to list+filterable for usability
            $list = $isFirstSchema ? ($i < 5) : false;
            $filterable = $isFirstSchema ? ($i < 5) : false;

            static::create([
                'user_id'    => $userId,
                'field_key'  => $key,
                'label'      => $label,
                'type'       => 'text',
                'filterable' => $filterable ? 1 : 0,
                'list'       => $list ? 1 : 0,
                'active'     => 1,
                'is_primary' => $isPrimary ? 1 : 0,
                'is_oem'     => $isOem ? 1 : 0,
                'tab'        => '字段',
                'sort_order' => $maxOrder,
            ]);
        }

        return self::forUser($userId, true);
    }

    public static function looksLikeOem(string $label): bool
    {
        $l = mb_strtolower($label);
        return $l === 'oem'
            || str_contains($l, 'oem')
            || str_contains($label, 'OEM号码')
            || str_contains($label, 'OEM号');
    }

    public static function setPrimary(int $userId, int $fieldId): void
    {
        $db = static::db();
        $db->prepare("UPDATE user_fields SET is_primary = 0 WHERE user_id = ?")->execute([$userId]);
        $db->prepare("UPDATE user_fields SET is_primary = 1 WHERE id = ? AND user_id = ?")
           ->execute([$fieldId, $userId]);
    }

    public static function setOem(int $userId, int $fieldId): void
    {
        $db = static::db();
        $db->prepare("UPDATE user_fields SET is_oem = 0 WHERE user_id = ?")->execute([$userId]);
        $db->prepare("UPDATE user_fields SET is_oem = 1 WHERE id = ? AND user_id = ?")
           ->execute([$fieldId, $userId]);
    }

    public static function updateFlags(int $userId, int $fieldId, array $flags): bool
    {
        $allowed = ['filterable', 'list', 'active', 'type', 'tab', 'sort_order', 'label'];
        $data = array_intersect_key($flags, array_flip($allowed));
        if (empty($data)) return false;
        foreach (['filterable', 'list', 'active'] as $b) {
            if (isset($data[$b])) $data[$b] = $data[$b] ? 1 : 0;
        }
        $data = static::filterFillable($data + ['user_id' => $userId]);
        unset($data['user_id']);
        if (empty($data)) return false;
        $sets = implode(', ', array_map(fn($f) => "`{$f}` = ?", array_keys($data)));
        $stmt = static::db()->prepare(
            "UPDATE user_fields SET {$sets}, updated_at = NOW() WHERE id = ? AND user_id = ?"
        );
        $vals = array_values($data);
        $vals[] = $fieldId;
        $vals[] = $userId;
        $stmt->execute($vals);
        return $stmt->rowCount() > 0;
    }

    /** Map a CSV row (keyed by label) into attrs + primary/oem using column defs. */
    public static function rowToAttrs(array $csvRow, array $columns): array
    {
        $attrs = [];
        $primary = '';
        $oem = '';
        foreach ($columns as $col) {
            $val = trim((string) ($csvRow[$col['label']] ?? ''));
            $attrs[$col['field']] = $val;
            if (!empty($col['is_primary'])) $primary = $val;
            if (!empty($col['is_oem'])) $oem = $val;
        }
        return [$attrs, $primary, $oem];
    }
}
