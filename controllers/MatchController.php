<?php
require_once ROOT . '/core/Database.php';
require_once ROOT . '/core/Controller.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/models/Product.php';

class MatchController extends Controller
{
    private array $columns;

    public function __construct()
    {
        $this->columns = require ROOT . '/config/columns.php';
    }

    // ── Upload form ────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->render('match/index', ['columns' => $this->columns]);
    }

    // ── Process uploaded match CSV ─────────────────────────────────────────────

    public function upload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url(['c' => 'match', 'a' => 'index']));
        }
        $this->verifyCsrf();

        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->render('match/index', [
                'error' => '请选择有效的 CSV 文件（PHP 限制最大 ' . ini_get('upload_max_filesize') . '）',
            ]);
            return;
        }

        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
            $this->render('match/index', ['error' => '仅支持 .csv 格式文件']);
            return;
        }

        $encoding = $_POST['encoding'] ?? 'auto';
        $content  = file_get_contents($file['tmp_name']);
        $content  = $this->toUtf8($content, $encoding);
        $content  = ltrim($content, "\xEF\xBB\xBF");

        $tmp = tempnam(sys_get_temp_dir(), 'match_');
        file_put_contents($tmp, $content);

        [$matchedRows, $oemList, $errors] = $this->matchCsv($tmp);
        @unlink($tmp);

        $this->render('match/index', [
            'matchedRows' => $matchedRows,
            'oemList'     => $oemList,
            'errors'      => $errors,
            'columns'     => $this->columns,
        ]);
    }

    // ── Download matched results as CSV ────────────────────────────────────────

    public function download(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url(['c' => 'match', 'a' => 'index']));
        }
        $this->verifyCsrf();

        $oemList = array_filter(array_map('trim', (array)($_POST['oem'] ?? [])));
        if (empty($oemList)) {
            $this->redirect($this->url(['c' => 'match', 'a' => 'index']));
        }

        $matchedRows = $this->queryByOemList($oemList);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="oem_match_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

        // Header row using Chinese labels
        fputcsv($out, array_column($this->columns, 'label'));

        foreach ($matchedRows as $row) {
            $line = [];
            foreach ($this->columns as $col) {
                $line[] = $row[$col['field']] ?? '';
            }
            fputcsv($out, $line);
        }

        fclose($out);
        exit;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

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
     * Parse the uploaded CSV, extract OEM values, match against products.
     *
     * @return array{0: array, 1: array, 2: array}  [matchedRows, oemList, errors]
     */
    private function matchCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [[], [], ['无法读取文件']];
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [[], [], ['CSV 文件似乎是空的']];
        }
        $headers = array_map('trim', $headers);

        // Find the "oem" column (case-insensitive)
        $oemColIdx = null;
        foreach ($headers as $idx => $h) {
            if (strtolower($h) === 'oem') {
                $oemColIdx = $idx;
                break;
            }
        }

        if ($oemColIdx === null) {
            fclose($handle);
            return [[], [], ['CSV 文件中未找到 "oem" 列，请确保 CSV 第一行包含名为 "oem" 的列（不区分大小写）']];
        }

        $oemValues = [];
        while (($row = fgetcsv($handle)) !== false) {
            $val = trim($row[$oemColIdx] ?? '');
            if ($val !== '') {
                $oemValues[] = $val;
            }
        }
        fclose($handle);

        $oemValues = array_values(array_unique($oemValues));

        if (empty($oemValues)) {
            return [[], [], ['CSV 文件的 "oem" 列没有有效数据']];
        }

        $matchedRows = $this->queryByOemList($oemValues);

        return [$matchedRows, $oemValues, []];
    }

    /**
     * Query products whose oem_number contains any of the given OEM strings as
     * whole tokens.
     *
     * Normalisation rules applied to BOTH the stored field and the search value:
     *   1. Strip all spaces
     *   2. Strip all hyphens  →  "42410-30010" == "4241030010"
     *   3. Replace '/' with ','  (unify delimiters)
     *   4. Wrap with sentinel commas so every token is bounded: ",token,"
     *      → prevents "4721010AA" matching inside "04721010AA"
     */
    private function queryByOemList(array $oemList): array
    {
        if (empty($oemList)) {
            return [];
        }

        $db = Database::getInstance();

        // SQL expression that normalises the stored oem_number column:
        //   strip spaces → strip hyphens → replace '/' with ',' → wrap with ','
        $normalised = "CONCAT(',', REPLACE(REPLACE(REPLACE(oem_number, ' ', ''), '-', ''), '/', ','), ',')";

        // One clause per search OEM
        $clauses = implode(
            ' OR ',
            array_fill(0, count($oemList), "{$normalised} LIKE ?")
        );

        // Normalise each search value the same way: strip spaces & hyphens,
        // then wrap as '%,<value>,%'
        $params = array_map(
            fn($v) => '%,' . str_replace([' ', '-'], '', $v) . ',%',
            $oemList
        );

        $stmt = $db->prepare(
            "SELECT * FROM products WHERE {$clauses} ORDER BY id ASC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $seen   = [];
        $unique = [];
        foreach ($rows as $r) {
            if (!isset($seen[$r['id']])) {
                $seen[$r['id']] = true;
                $unique[]       = $r;
            }
        }

        return $unique;
    }
}
