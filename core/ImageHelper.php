<?php
/**
 * ImageHelper – centralized product-image upload / save / delete logic.
 *
 * Files are stored under `public/uploads/products/` and the DB stores
 * a path RELATIVE to the project root (e.g. "public/uploads/products/abc.jpg")
 * so that the existing `asset()` helper or a direct `<img src>` can resolve it.
 */
class ImageHelper
{
    public const UPLOAD_DIR     = 'public/uploads/products';
    public const MAX_BYTES      = 8 * 1024 * 1024; // 8 MB
    public const ALLOWED_EXTS   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    public const ALLOWED_MIMES  = [
        'image/jpeg', 'image/pjpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /** Absolute filesystem path of the upload directory. */
    public static function uploadDir(): string
    {
        return ROOT . '/' . self::UPLOAD_DIR;
    }

    /** Make sure the upload directory exists and is writable. */
    public static function ensureDir(): void
    {
        $dir = self::uploadDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    /**
     * Validate a single uploaded file (from $_FILES or a synthesized array).
     * Expected keys: name, tmp_name, size, error.
     *
     * Returns null on success, or an error message string on failure.
     */
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

        // MIME sniff to defend against renamed files.
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

    /**
     * Save an uploaded file to the products upload directory.
     *
     * @param array  $file       uploaded file array (name, tmp_name, size, error)
     * @param string $namePrefix prefix used for the saved filename
     *                           (will be sanitized; e.g. tqb_code or "product_42")
     * @param bool   $isHttpUpload whether to use move_uploaded_file (true) or rename (false).
     *                             For PHP HTTP uploads use true. For server-side
     *                             synthesized arrays (e.g. extracted-from-CSV image
     *                             that was uploaded as part of a multi-upload), set
     *                             to true if tmp_name came from a real upload.
     *
     * @return string relative path saved (e.g. "public/uploads/products/foo.jpg")
     * @throws RuntimeException on failure
     */
    public static function save(array $file, string $namePrefix, bool $isHttpUpload = true): string
    {
        self::ensureDir();

        $ext  = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $safe = self::sanitizeName($namePrefix);
        // Random suffix to bust cache & avoid collisions on overwrites.
        $rand = bin2hex(random_bytes(4));
        $fname = $safe . '_' . date('YmdHis') . '_' . $rand . '.' . $ext;
        $abs   = self::uploadDir() . '/' . $fname;

        $ok = $isHttpUpload
            ? @move_uploaded_file($file['tmp_name'], $abs)
            : @rename($file['tmp_name'], $abs);

        if (!$ok) {
            // Fallback: try copy + unlink (handy on some Windows / temp-dir setups).
            if (@copy($file['tmp_name'], $abs)) {
                @unlink($file['tmp_name']);
                $ok = true;
            }
        }
        if (!$ok) {
            throw new RuntimeException('无法保存图片到: ' . $abs);
        }

        return self::UPLOAD_DIR . '/' . $fname;
    }

    /**
     * Delete a previously-saved image file (if it exists).
     * Accepts the relative path stored in DB.
     */
    public static function delete(?string $relativePath): void
    {
        if (!$relativePath) return;
        $rel = ltrim(str_replace('\\', '/', $relativePath), '/');
        // Safety: only allow deletion inside our upload dir.
        if (!str_starts_with($rel, self::UPLOAD_DIR . '/')) return;
        $abs = ROOT . '/' . $rel;
        if (is_file($abs)) @unlink($abs);
    }

    /**
     * Public URL for a stored relative path (web-accessible).
     * The project is served from the project root, so the relative path is
     * already the correct URL once the script-dir prefix is added.
     */
    public static function url(string $relativePath): string
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        return $base . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    /**
     * Make a string safe to use as a filename.
     * Keeps letters, numbers, dash, underscore and dot; replaces others with _.
     */
    public static function sanitizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') $name = 'image';
        $name = preg_replace('/[^A-Za-z0-9._-]+/u', '_', $name) ?? 'image';
        return substr($name, 0, 80);
    }

    /**
     * Extract uploaded files for a $_FILES multi-input (name="images[]").
     * Returns a list of single-file arrays each shaped like:
     *   ['name'=>..., 'tmp_name'=>..., 'size'=>..., 'error'=>...]
     */
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
