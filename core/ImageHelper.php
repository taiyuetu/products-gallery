<?php
/**
 * ImageHelper – product-image upload / save / delete.
 * Files are stored under public/uploads/products/{userId}/
 */
class ImageHelper
{
    public const UPLOAD_BASE    = 'public/uploads/products';
    public const MAX_BYTES      = 8 * 1024 * 1024;
    public const ALLOWED_EXTS   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    public const ALLOWED_MIMES  = [
        'image/jpeg', 'image/pjpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public static function uploadDir(int $userId): string
    {
        return ROOT . '/' . self::UPLOAD_BASE . '/' . $userId;
    }

    public static function relativeDir(int $userId): string
    {
        return self::UPLOAD_BASE . '/' . $userId;
    }

    public static function ensureDir(int $userId): void
    {
        $dir = self::uploadDir($userId);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $index = $dir . '/index.html';
        if (!is_file($index)) {
            @file_put_contents($index, '');
        }
    }

    public static function validate(array $file): ?string
    {
        if (!isset($file['tmp_name']) || $file['tmp_name'] === '' || !is_file($file['tmp_name'])) {
            return '未收到有效的上传文件';
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return '文件上传失败 (错误码 ' . (int)$file['error'] . ')';
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return '图片大小超过 ' . (self::MAX_BYTES / 1024 / 1024) . 'MB';
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTS, true)) {
            return '仅支持图片格式: ' . implode(', ', self::ALLOWED_EXTS);
        }

        if (function_exists('finfo_open')) {
            $f    = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $f ? finfo_file($f, $file['tmp_name']) : '';
            if ($f) finfo_close($f);
            if ($mime && !in_array($mime, self::ALLOWED_MIMES, true)) {
                return '文件内容不是有效的图片 (' . $mime . ')';
            }
        }
        return null;
    }

    public static function save(array $file, string $namePrefix, int $userId, bool $isHttpUpload = true): string
    {
        self::ensureDir($userId);

        $ext  = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $safe = self::sanitizeName($namePrefix);
        $rand = bin2hex(random_bytes(4));
        $fname = $safe . '_' . date('YmdHis') . '_' . $rand . '.' . $ext;
        $abs   = self::uploadDir($userId) . '/' . $fname;

        $ok = $isHttpUpload
            ? @move_uploaded_file($file['tmp_name'], $abs)
            : @rename($file['tmp_name'], $abs);

        if (!$ok) {
            if (@copy($file['tmp_name'], $abs)) {
                @unlink($file['tmp_name']);
                $ok = true;
            }
        }
        if (!$ok) {
            throw new RuntimeException('无法保存图片到: ' . $abs);
        }

        return self::relativeDir($userId) . '/' . $fname;
    }

    public static function delete(?string $relativePath): void
    {
        if (!$relativePath) return;
        $rel = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!str_starts_with($rel, self::UPLOAD_BASE . '/')) return;
        $abs = ROOT . '/' . $rel;
        if (is_file($abs)) @unlink($abs);
    }

    public static function url(string $relativePath): string
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        return $base . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    public static function sanitizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') $name = 'image';
        $name = preg_replace('/[^A-Za-z0-9._-]+/u', '_', $name) ?? 'image';
        return substr($name, 0, 80);
    }

    public static function normalizeMulti(array $filesField): array
    {
        if (empty($filesField['name']) || !is_array($filesField['name'])) {
            return [];
        }
        $out = [];
        foreach ($filesField['name'] as $i => $name) {
            if (($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name'     => $name,
                'tmp_name' => $filesField['tmp_name'][$i] ?? '',
                'size'     => $filesField['size'][$i] ?? 0,
                'error'    => $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'type'     => $filesField['type'][$i] ?? '',
            ];
        }
        return $out;
    }
}
