<?php
require_once ROOT . '/core/Database.php';
require_once ROOT . '/core/Controller.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/ImageHelper.php';
require_once ROOT . '/models/Product.php';

class ImportController extends Controller
{
    private array $columns;

    public function __construct()
    {
        $this->columns = require ROOT . '/config/columns.php';
    }

    // ── Upload form ───────────────────────────────────────────────

    public function index(): void
    {
        $this->render('import/index', ['columns' => $this->columns]);
    }

    // ── Process upload ────────────────────────────────────────────

    public function upload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url(['c' => 'import', 'a' => 'index']));
        }
        $this->verifyCsrf();

        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->renderWithError('请选择一个有效的 CSV 文件。PHP 限制: ' . ini_get('upload_max_filesize') . '。');
            return;
        }

        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
            $this->renderWithError('仅支持 .csv 格式文件');
            return;
        }

        $encoding = $_POST['encoding'] ?? 'auto';
        $content  = file_get_contents($file['tmp_name']);
        $content  = $this->toUtf8($content, $encoding);
        $content  = ltrim($content, "\xEF\xBB\xBF");

        $tmp = tempnam(sys_get_temp_dir(), 'pdb_');
        file_put_contents($tmp, $content);

        // Pre-process bundled product images.
        // The map is keyed by TQB code (case-insensitive) -> array of saved relative paths.
        [$imageMap, $imageErrors, $imageSavedCount, $imageReport]
            = $this->processImages($_FILES['images'] ?? null);

        [$imported, $skipped, $errors, $successRows, $imageMatched, $imageMissing]
            = $this->importCsv($tmp, $imageMap);

        @unlink($tmp);

        // Clean up images that did NOT match any TQB code in the CSV
        $imageUnmatched = [];
        foreach ($imageMap as $key => $paths) {
            if (!isset($imageMatched[$key])) {
                foreach ($paths as $rel) {
                    ImageHelper::delete($rel);
                }
                $imageUnmatched[] = $key;
            }
        }

        foreach ($imageReport as &$row) {
            if (!empty($row['saved']) && isset($row['key'])) {
                $row['matched'] = isset($imageMatched[$row['key']]);
            }
        }
        unset($row);

        $this->render('import/index', [
            'columns'         => $this->columns,
            'imported'        => $imported,
            'skipped'         => $skipped,
            'errors'          => array_merge($errors, $imageErrors),
            'successRows'     => $successRows,
            'imageSavedCount' => $imageSavedCount,
            'imageMatchCount' => count($imageMatched),
            'imageMissing'    => $imageMissing,
            'imageUnmatched'  => $imageUnmatched,
            'imageReport'     => $imageReport,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────

    private function toUtf8(string $content, string $encoding): string
    {
        if ($encoding === 'auto') {
            $detected = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'Big5'], true);
            $from = ($detected && $detected !== 'UTF-8') ? $detected : null;
        } elseif (in_array(strtoupper($encoding), ['GBK', 'GB2312'], true)) {
            $from = 'GBK';
        } else {
            $from = null;
        }
        return $from ? mb_convert_encoding($content, 'UTF-8', $from) : $content;
    }

    /**
     * Save uploaded images (multi-file input "images[]") to the products
     * upload directory. Returns:
     *   [map, errors, savedCount, perFileReport]
     * where `map` is keyed by the normalized TQB code derived from the
     * filename basename → array of saved relative paths.
     * Multiple images with the same TQB prefix (e.g. TQB0-001(1).jpg,
     * TQB0-001(2).jpg) are ALL collected into the same gallery array.
     */
    private function processImages(?array $filesField): array
    {
        if (!$filesField) return [[], [], 0, []];

        $files = ImageHelper::normalizeMulti($filesField);
        if (empty($files)) return [[], [], 0, []];

        $map     = [];  // key => [path1, path2, ...]
        $errors  = [];
        $saved   = 0;
        $report  = [];

        foreach ($files as $f) {
            $name = (string)($f['name'] ?? '');
            $base = basename(str_replace('\\', '/', $name));
            $stem = trim(pathinfo($base, PATHINFO_FILENAME));
            $key  = self::normalizeTqbKey($stem);

            $entry = [
                'name'   => $base,
                'stem'   => $stem,
                'key'    => $key,
                'saved'  => false,
                'error'  => null,
            ];

            if ($key === '') {
                $entry['error'] = '无法从文件名解析 TQB 编码';
                $errors[] = '跳过图片 ' . $base . '：' . $entry['error'];
                $report[] = $entry;
                continue;
            }

            $err = ImageHelper::validate($f);
            if ($err !== null) {
                $entry['error'] = $err;
                $errors[] = '图片 ' . $base . ' 跳过: ' . $err;
                $report[] = $entry;
                continue;
            }

            try {
                $rel = ImageHelper::save($f, $key, true);
                if (!isset($map[$key])) {
                    $map[$key] = [];
                }
                $map[$key][]    = $rel;
                $entry['saved'] = true;
                $entry['path']  = $rel;
                $saved++;
            } catch (Throwable $e) {
                $entry['error'] = $e->getMessage();
                $errors[] = '图片 ' . $base . ' 保存失败: ' . $e->getMessage();
            }
            $report[] = $entry;
        }

        return [$map, $errors, $saved, $report];
    }

    /**
     * Normalize a string into the key used to match an image to a TQB code.
     *
     * Lower-cases, trims, and strips common OS-added markers so the resulting
     * key is stable across e.g.:
     *   "TQB3-0001.webp"                -> "tqb3-0001"
     *   "TQB3-0001 (3).webp"            -> "tqb3-0001"
     *   "TQB3-0001(3).webp"             -> "tqb3-0001"
     *   "TQB3-0001 - Copy.webp"         -> "tqb3-0001"
     *   "TQB3-0001 - Copy (2).webp"     -> "tqb3-0001"
     *   "TQB3-0001 - 副本.webp"         -> "tqb3-0001"
     *   "images/TQB3-0001.webp"         -> "tqb3-0001"  (caller strips dir)
     */
    public static function normalizeTqbKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') return '';

        $prev = null;
        while ($prev !== $value) {
            $prev = $value;
            $value = preg_replace('/\s*\(\s*\d+\s*\)\s*$/u', '', $value) ?? $value;
            $value = preg_replace(
                '/\s*[-_]?\s*(?:copy|副本|複本|拷贝)\s*\d*\s*$/iu',
                '',
                $value
            ) ?? $value;
            $value = preg_replace('/^[\s\-_]+|[\s\-_]+$/u', '', $value) ?? $value;
        }
        return $value;
    }

    /**
     * Import CSV rows. If $imageMap is non-empty, each row whose TQB code
     * matches a key in the map gets its `gallery` set to the matched image
     * paths (merged with any existing gallery). The set of matched keys is
     * returned so unmatched images can be cleaned up.
     */
    private function importCsv(string $filePath, array $imageMap = []): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) return [0, 0, ['无法读取文件'], [], [], []];

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [0, 0, ['CSV 文件似乎是空的'], [], [], []];
        }
        $headers = array_map('trim', $headers);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $successRows  = [];
        $imageMatched = [];
        $imageMissing = [];

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            set_time_limit(300);

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), '');
                }
                $csvRow = array_combine($headers, array_slice($row, 0, count($headers)));
                $data   = Product::fromCsvRow($csvRow, $this->columns);

                if (empty(array_filter($data, fn($v) => $v !== ''))) {
                    $skipped++;
                    continue;
                }

                $tqbCode = $data['tqb_code'] ?? '';
                $newOem  = $data['oem_number'] ?? '';

                $imgKey    = self::normalizeTqbKey($tqbCode);
                $matchPaths = ($imgKey !== '' && isset($imageMap[$imgKey])) ? $imageMap[$imgKey] : null;

                if ($tqbCode !== '') {
                    $existing = Product::findByTqbCode($tqbCode);
                    if ($existing) {
                        $existingOem = $existing['oem_number'] ?? '';

                        $oemParts    = array_filter(array_map('trim', explode('/', $existingOem)), fn($v) => $v !== '');
                        $newOemParts = array_filter(array_map('trim', explode('/', $newOem)),     fn($v) => $v !== '');

                        $isSubset = empty(array_diff($newOemParts, $oemParts));

                        if ($isSubset && $matchPaths === null) {
                            $skipped++;
                            if (!empty($imageMap) && $imgKey !== '' && !isset($imageMap[$imgKey])) {
                                $imageMissing[$tqbCode] = true;
                            }
                            continue;
                        }

                        if (!$isSubset) {
                            $mergedOem = array_unique(array_merge($oemParts, $newOemParts));
                            $data['oem_number'] = implode('/', $mergedOem);
                        } else {
                            $data['oem_number'] = $existingOem;
                        }

                        if ($matchPaths !== null) {
                            // Merge new images into the existing gallery
                            $existingGallery = Product::parseGallery($existing['gallery'] ?? null);
                            $mergedGallery = array_merge($existingGallery, $matchPaths);
                            $data['gallery'] = Product::galleryJson($mergedGallery);
                            $imageMatched[$imgKey] = true;
                        }

                        $updated = Product::update($existing['id'], $data);
                        if ($updated) {
                            $successRows[] = $data + ['id' => $existing['id']];
                            $imported++;
                        } else {
                            $skipped++;
                        }

                        if (!empty($imageMap) && $imgKey !== '' && $matchPaths === null) {
                            $imageMissing[$tqbCode] = true;
                        }
                        continue;
                    }
                }

                // New product
                if ($matchPaths !== null) {
                    $data['gallery'] = Product::galleryJson($matchPaths);
                    $imageMatched[$imgKey] = true;
                } elseif (!empty($imageMap) && $tqbCode !== '') {
                    $imageMissing[$tqbCode] = true;
                }

                $newId = Product::create($data);
                if ($newId) {
                    $data['id'] = $newId;
                    $successRows[] = $data;
                    $imported++;
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $errors[] = '导入时出错: ' . $e->getMessage();
        }

        fclose($handle);
        return [
            $imported,
            $skipped,
            $errors,
            array_slice($successRows, -20),
            $imageMatched,
            array_keys($imageMissing),
        ];
    }

    private function renderWithError(string $message): void
    {
        $this->render('import/index', [
            'columns' => $this->columns,
            'error'   => $message,
        ]);
    }
}
