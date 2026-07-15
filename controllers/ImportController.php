<?php
require_once ROOT . '/core/Database.php';
require_once ROOT . '/core/Controller.php';
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/ImageHelper.php';
require_once ROOT . '/models/Product.php';
require_once ROOT . '/models/UserField.php';

class ImportController extends Controller
{
    public function __construct()
    {
        UserField::ensureTable();
    }

    public function index(): void
    {
        $userId  = $this->userId();
        $columns = UserField::forUser($userId, true);
        $needSchema = isset($_GET['msg']) && $_GET['msg'] === 'need_schema';
        $this->render('import/index', [
            'columns'    => $columns,
            'needSchema' => $needSchema,
        ]);
    }

    public function upload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url(['c' => 'import', 'a' => 'index']));
        }
        $this->verifyCsrf();
        $userId = $this->userId();

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

        [$imageMap, $imageErrors, $imageSavedCount, $imageReport]
            = $this->processImages($userId, $_FILES['images'] ?? null);

        [$imported, $skipped, $errors, $successRows, $imageMatched, $imageMissing, $columns]
            = $this->importCsv($userId, $tmp, $imageMap);

        @unlink($tmp);

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
            'columns'         => $columns,
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

    private function processImages(int $userId, ?array $filesField): array
    {
        if (!$filesField) return [[], [], 0, []];

        $files = ImageHelper::normalizeMulti($filesField);
        if (empty($files)) return [[], [], 0, []];

        $map     = [];
        $errors  = [];
        $saved   = 0;
        $report  = [];

        foreach ($files as $f) {
            $name = (string)($f['name'] ?? '');
            $base = basename(str_replace('\\', '/', $name));
            $stem = trim(pathinfo($base, PATHINFO_FILENAME));
            $key  = self::normalizeMatchKey($stem);

            $entry = [
                'name'   => $base,
                'stem'   => $stem,
                'key'    => $key,
                'saved'  => false,
                'error'  => null,
            ];

            if ($key === '') {
                $entry['error'] = '无法从文件名解析主键';
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
                $rel = ImageHelper::save($f, $key, $userId, true);
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

    public static function normalizeMatchKey(string $value): string
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

    private function importCsv(int $userId, string $filePath, array $imageMap = []): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) return [0, 0, ['无法读取文件'], [], [], [], []];

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [0, 0, ['CSV 文件似乎是空的'], [], [], [], []];
        }
        $headers = array_map('trim', $headers);

        $columns = UserField::syncFromCsvHeaders($userId, $headers);
        if (empty($columns)) {
            fclose($handle);
            return [0, 0, ['未能从 CSV 表头创建字段'], [], [], [], []];
        }

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
                if ($csvRow === false) {
                    $skipped++;
                    continue;
                }

                [$attrs, $primary, $oem] = UserField::rowToAttrs($csvRow, $columns);

                if (empty(array_filter($attrs, fn($v) => $v !== ''))) {
                    $skipped++;
                    continue;
                }

                $imgKey     = self::normalizeMatchKey($primary);
                $matchPaths = ($imgKey !== '' && isset($imageMap[$imgKey])) ? $imageMap[$imgKey] : null;

                if ($primary !== '') {
                    $existing = Product::findByPrimary($userId, $primary);
                    if ($existing) {
                        $existingOem = $existing['oem_value'] ?? '';
                        $oemParts    = array_filter(array_map('trim', explode('/', (string)$existingOem)), fn($v) => $v !== '');
                        $newOemParts = array_filter(array_map('trim', explode('/', $oem)), fn($v) => $v !== '');
                        $isSubset = empty(array_diff($newOemParts, $oemParts));

                        if ($isSubset && $matchPaths === null) {
                            $skipped++;
                            if (!empty($imageMap) && $imgKey !== '' && !isset($imageMap[$imgKey])) {
                                $imageMissing[$primary] = true;
                            }
                            continue;
                        }

                        if (!$isSubset) {
                            $mergedOem = array_unique(array_merge($oemParts, $newOemParts));
                            $oem = implode('/', $mergedOem);
                            // also update attrs oem key
                            $oemCol = UserField::oemField($userId);
                            if ($oemCol) {
                                $attrs[$oemCol['field']] = $oem;
                            }
                        } else {
                            $oem = $existingOem;
                        }

                        $gallery = Product::parseGallery($existing['gallery'] ?? null);
                        if ($matchPaths !== null) {
                            $gallery = array_merge($gallery, $matchPaths);
                            $imageMatched[$imgKey] = true;
                        }

                        // merge attrs
                        $mergedAttrs = array_merge(Product::parseAttrs($existing['attrs'] ?? null), $attrs);

                        $updated = Product::updateAttrs($userId, (int)$existing['id'], [
                            'primary_value' => $primary,
                            'oem_value'     => $oem,
                            'attrs'         => $mergedAttrs,
                            'gallery'       => Product::galleryJson($gallery),
                        ]);
                        if ($updated) {
                            $successRows[] = $mergedAttrs + ['id' => $existing['id'], 'primary_value' => $primary];
                            $imported++;
                        } else {
                            $skipped++;
                        }

                        if (!empty($imageMap) && $imgKey !== '' && $matchPaths === null) {
                            $imageMissing[$primary] = true;
                        }
                        continue;
                    }
                }

                $gallery = null;
                if ($matchPaths !== null) {
                    $gallery = Product::galleryJson($matchPaths);
                    $imageMatched[$imgKey] = true;
                } elseif (!empty($imageMap) && $primary !== '') {
                    $imageMissing[$primary] = true;
                }

                $newId = Product::createForUser($userId, [
                    'primary_value' => $primary,
                    'oem_value'     => $oem,
                    'attrs'         => $attrs,
                    'gallery'       => $gallery,
                ]);
                if ($newId) {
                    $successRows[] = $attrs + ['id' => $newId, 'primary_value' => $primary];
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
            $columns,
        ];
    }

    private function renderWithError(string $message): void
    {
        $this->render('import/index', [
            'columns' => UserField::forUser($this->userId(), true),
            'error'   => $message,
        ]);
    }
}
