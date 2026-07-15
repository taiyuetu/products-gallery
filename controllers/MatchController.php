<?php
require_once ROOT . '/core/Database.php';
require_once ROOT . '/core/Controller.php';
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/models/Product.php';
require_once ROOT . '/models/UserField.php';

class MatchController extends Controller
{
    public function index(): void
    {
        $userId  = $this->userId();
        $columns = UserField::forUser($userId, true);
        $oemField = UserField::oemField($userId);
        $this->render('match/index', [
            'columns'  => $columns,
            'oemField' => $oemField,
        ]);
    }

    public function upload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url(['c' => 'match', 'a' => 'index']));
        }
        $this->verifyCsrf();
        $userId = $this->userId();

        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->render('match/index', [
                'error'    => '请选择有效的 CSV 文件（PHP 限制最大 ' . ini_get('upload_max_filesize') . '）',
                'columns'  => UserField::forUser($userId, true),
                'oemField' => UserField::oemField($userId),
            ]);
            return;
        }

        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
            $this->render('match/index', [
                'error'    => '仅支持 .csv 格式文件',
                'columns'  => UserField::forUser($userId, true),
                'oemField' => UserField::oemField($userId),
            ]);
            return;
        }

        $encoding = $_POST['encoding'] ?? 'auto';
        $content  = file_get_contents($file['tmp_name']);
        $content  = $this->toUtf8($content, $encoding);
        $content  = ltrim($content, "\xEF\xBB\xBF");

        $tmp = tempnam(sys_get_temp_dir(), 'match_');
        file_put_contents($tmp, $content);

        [$matchedRows, $oemList, $errors] = $this->matchCsv($userId, $tmp);
        @unlink($tmp);

        $this->render('match/index', [
            'matchedRows' => $matchedRows,
            'oemList'     => $oemList,
            'errors'      => $errors,
            'columns'     => UserField::forUser($userId, true),
            'oemField'    => UserField::oemField($userId),
        ]);
    }

    public function download(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url(['c' => 'match', 'a' => 'index']));
        }
        $this->verifyCsrf();
        $userId  = $this->userId();
        $columns = UserField::forUser($userId, true);

        $oemList = array_filter(array_map('trim', (array)($_POST['oem'] ?? [])));
        if (empty($oemList)) {
            $this->redirect($this->url(['c' => 'match', 'a' => 'index']));
        }

        $matchedRows = $this->queryByOemList($userId, $oemList);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="oem_match_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_column($columns, 'label'));

        foreach ($matchedRows as $row) {
            $row = Product::hydrate($row);
            $line = [];
            foreach ($columns as $col) {
                $line[] = $row[$col['field']] ?? '';
            }
            fputcsv($out, $line);
        }

        fclose($out);
        exit;
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

    private function matchCsv(int $userId, string $filePath): array
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

        $oemColIdx = null;
        foreach ($headers as $idx => $h) {
            if (strtolower($h) === 'oem' || UserField::looksLikeOem($h)) {
                $oemColIdx = $idx;
                break;
            }
        }

        if ($oemColIdx === null) {
            fclose($handle);
            return [[], [], ['CSV 文件中未找到 OEM 相关列，请确保第一行包含名为 oem / OEM号码 的列']];
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
            return [[], [], ['CSV 的 OEM 列没有有效数据']];
        }

        if (!UserField::oemField($userId)) {
            return [[], [], ['请先在「字段管理」中标记一个 OEM 字段，或导入含 OEM 列的产品 CSV']];
        }

        $matchedRows = $this->queryByOemList($userId, $oemValues);
        $matchedRows = array_map([Product::class, 'hydrate'], $matchedRows);

        return [$matchedRows, $oemValues, []];
    }

    private function queryByOemList(int $userId, array $oemList): array
    {
        if (empty($oemList)) {
            return [];
        }

        $db = Database::getInstance();
        $normalised = "CONCAT(',', REPLACE(REPLACE(REPLACE(COALESCE(oem_value,''), ' ', ''), '-', ''), '/', ','), ',')";

        $clauses = implode(
            ' OR ',
            array_fill(0, count($oemList), "{$normalised} LIKE ?")
        );

        $params = array_map(
            fn($v) => '%,' . str_replace([' ', '-'], '', $v) . ',%',
            $oemList
        );
        array_unshift($params, $userId);

        $stmt = $db->prepare(
            "SELECT * FROM products WHERE user_id = ? AND ({$clauses}) ORDER BY id ASC"
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
