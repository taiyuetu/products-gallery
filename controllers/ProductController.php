<?php
require_once ROOT . '/core/Database.php';
require_once ROOT . '/core/Controller.php';
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/ImageHelper.php';
require_once ROOT . '/models/Product.php';
require_once ROOT . '/models/Category.php';
require_once ROOT . '/models/UserField.php';

class ProductController extends Controller
{
    public function index(): void
    {
        $userId  = $this->userId();
        $columns = $this->userColumns();
        $filters = $_GET['f'] ?? [];
        $q       = trim($_GET['q'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $allowedSort = ['id', 'created_at', 'updated_at', 'primary_value'];
        $sort = (string) ($_GET['sort'] ?? 'updated_at');
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'updated_at';
        }
        $orderDir = strtoupper((string) ($_GET['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $order = [$sort => $orderDir];
        if ($sort !== 'id') {
            $order['id'] = 'DESC';
        }

        $result = Product::search($userId, $filters, $page, 50, $q, $order, $columns);

        $this->render('products/index', [
            'columns'    => $columns,
            'categories' => Category::allForUser($userId),
            'filters'    => $filters,
            'q'          => $q,
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $result['page'],
            'perPage'    => $result['perPage'],
            'totalPages' => $result['totalPages'],
            'sort'       => $sort,
            'order'      => $orderDir,
        ]);
    }

    public function show(): void
    {
        $product = $this->findOrRedirect();
        $this->render('products/show', [
            'product' => $product,
            'columns' => $this->userColumns(),
        ]);
    }

    public function create(): void
    {
        $userId  = $this->userId();
        $columns = $this->userColumns();
        if (empty($columns)) {
            $this->redirect($this->url(['c' => 'import', 'a' => 'index', 'msg' => 'need_schema']));
        }
        $this->render('products/form', [
            'product'    => [],
            'columns'    => $columns,
            'categories' => Category::allForUser($userId),
            'isEdit'     => false,
            'title'      => '新增',
        ]);
    }

    public function store(): void
    {
        $this->requirePost();
        $userId  = $this->userId();
        $columns = $this->userColumns();
        $posted  = $_POST['product'] ?? [];
        [$attrs, $primary, $oem] = Product::attrsFromForm($posted, $columns);
        $categoryId = $this->resolveCategory($posted, $_POST['new_category_name'] ?? '', $userId);

        $newId = Product::createForUser($userId, [
            'category_id'   => $categoryId,
            'primary_value' => $primary,
            'oem_value'     => $oem,
            'attrs'         => $attrs,
            'gallery'       => null,
        ]);

        if ($newId && !empty($_FILES['images'])) {
            $files = ImageHelper::normalizeMulti($_FILES['images']);
            $saved = [];
            $prefix = $primary !== '' ? $primary : ('product_' . $newId);
            foreach ($files as $f) {
                if (ImageHelper::validate($f) !== null) continue;
                try {
                    $saved[] = ImageHelper::save($f, $prefix, $userId, true);
                } catch (Throwable $e) { /* skip */ }
            }
            if (!empty($saved)) {
                Product::updateAttrs($userId, $newId, ['gallery' => Product::galleryJson($saved)]);
            }
        }

        $this->redirect($this->url(['c' => 'product', 'a' => 'index', 'msg' => 'created']));
    }

    public function edit(): void
    {
        $product = $this->findOrRedirect();
        $userId  = $this->userId();
        $this->render('products/form', [
            'product'    => $product,
            'columns'    => $this->userColumns(),
            'categories' => Category::allForUser($userId),
            'isEdit'     => true,
            'title'      => '编辑',
        ]);
    }

    public function update(): void
    {
        $this->requirePost();
        $userId = $this->userId();
        $id     = (int) ($_POST['id'] ?? 0);
        $existing = Product::findForUser($userId, $id);
        if (!$existing) {
            $this->redirect($this->url(['c' => 'product', 'a' => 'index']));
        }

        $columns = $this->userColumns();
        $posted  = $_POST['product'] ?? [];
        [$attrs, $primary, $oem] = Product::attrsFromForm($posted, $columns);
        $categoryId = $this->resolveCategory($posted, $_POST['new_category_name'] ?? '', $userId);

        $gallery = Product::parseGallery($existing['gallery'] ?? null);

        $removeList = $_POST['remove_gallery'] ?? [];
        if (!empty($removeList) && is_array($removeList)) {
            foreach ($removeList as $relPath) {
                ImageHelper::delete($relPath);
                $gallery = array_values(array_filter($gallery, fn($p) => $p !== $relPath));
            }
        }
        if (!empty($_POST['remove_all_images'])) {
            foreach ($gallery as $p) {
                ImageHelper::delete($p);
            }
            $gallery = [];
        }

        if (!empty($_FILES['images'])) {
            $files  = ImageHelper::normalizeMulti($_FILES['images']);
            $prefix = $primary !== '' ? $primary : ('product_' . $id);
            foreach ($files as $f) {
                if (ImageHelper::validate($f) !== null) continue;
                try {
                    $gallery[] = ImageHelper::save($f, $prefix, $userId, true);
                } catch (Throwable $e) { /* skip */ }
            }
        }

        Product::updateAttrs($userId, $id, [
            'category_id'   => $categoryId,
            'primary_value' => $primary,
            'oem_value'     => $oem,
            'attrs'         => $attrs,
            'gallery'       => Product::galleryJson($gallery),
        ]);

        $this->redirect($this->url(['c' => 'product', 'a' => 'show', 'id' => $id, 'msg' => 'updated']));
    }

    public function delete(): void
    {
        $this->requirePost();
        $userId = $this->userId();
        $id = (int) ($_POST['id'] ?? 0);
        $existing = Product::findForUser($userId, $id);
        if ($existing) {
            foreach (Product::parseGallery($existing['gallery'] ?? null) as $p) {
                ImageHelper::delete($p);
            }
            Product::deleteForUser($userId, $id);
        }
        $this->redirect($this->url(['c' => 'product', 'a' => 'index', 'msg' => 'deleted']));
    }

    public function deleteAll(): void
    {
        $this->requirePost();
        $userId = $this->userId();
        $rows = Product::all([], ['id' => 'ASC'], 0, 0, '', $userId);
        foreach ($rows as $row) {
            foreach (Product::parseGallery($row['gallery'] ?? null) as $p) {
                ImageHelper::delete($p);
            }
        }
        Product::truncateForUser($userId);
        $this->redirect($this->url(['c' => 'product', 'a' => 'index', 'msg' => 'deleted_all']));
    }

    public function deleteImage(): void
    {
        $this->requirePost();
        $userId = $this->userId();
        $id = (int) ($_POST['id'] ?? 0);
        $existing = Product::findForUser($userId, $id);
        if ($existing) {
            foreach (Product::parseGallery($existing['gallery'] ?? null) as $p) {
                ImageHelper::delete($p);
            }
            Product::updateAttrs($userId, $id, ['gallery' => null]);
        }
        $this->redirect($this->url(['c' => 'product', 'a' => 'edit', 'id' => $id, 'msg' => 'updated']));
    }

    public function removeGalleryImage(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Invalid method'], 405);
        }
        $this->verifyCsrfJson();
        $userId = Auth::userId();
        $id   = (int) ($_POST['id'] ?? 0);
        $path = $_POST['path'] ?? '';
        $product = Product::findForUser($userId, $id);
        if (!$product) {
            $this->json(['ok' => false, 'error' => '产品不存在'], 404);
        }
        $gallery = Product::parseGallery($product['gallery'] ?? null);
        ImageHelper::delete($path);
        $gallery = array_values(array_filter($gallery, fn($p) => $p !== $path));
        Product::updateAttrs($userId, $id, ['gallery' => Product::galleryJson($gallery)]);
        $this->json(['ok' => true, 'gallery' => $gallery]);
    }

    public function uploadImage(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Invalid method'], 405);
        }
        $this->verifyCsrfJson();
        $userId = Auth::userId();
        $id = (int) ($_POST['id'] ?? 0);
        $product = Product::findForUser($userId, $id);
        if (!$product) {
            $this->json(['ok' => false, 'error' => '产品不存在'], 404);
        }

        $files = [];
        if (!empty($_FILES['images'])) {
            $files = ImageHelper::normalizeMulti($_FILES['images']);
        } elseif (!empty($_FILES['image']['name'])) {
            $files = [$_FILES['image']];
        }
        if (empty($files)) {
            $this->json(['ok' => false, 'error' => '未选择文件']);
        }

        $gallery = Product::parseGallery($product['gallery'] ?? null);
        $prefix  = ($product['primary_value'] ?? '') !== '' ? $product['primary_value'] : ('product_' . $id);
        $newUrls = [];
        $base    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

        foreach ($files as $f) {
            $err = ImageHelper::validate($f);
            if ($err !== null) continue;
            try {
                $rel = ImageHelper::save($f, $prefix, $userId, true);
                $gallery[] = $rel;
                $newUrls[] = $base . '/' . ltrim($rel, '/');
            } catch (Throwable $e) { /* skip */ }
        }

        if (empty($newUrls)) {
            $this->json(['ok' => false, 'error' => '没有有效的图片文件']);
        }

        Product::updateAttrs($userId, $id, ['gallery' => Product::galleryJson($gallery)]);

        $this->json([
            'ok'      => true,
            'url'     => $newUrls[0],
            'urls'    => $newUrls,
            'gallery' => array_map(fn($p) => $base . '/' . ltrim($p, '/'), $gallery),
        ]);
    }

    public function export(): void
    {
        $userId  = $this->userId();
        $columns = $this->userColumns();
        $filters = $_GET['f'] ?? [];
        $q       = trim($_GET['q'] ?? '');
        $result  = Product::search($userId, $filters, 1, 0, $q, ['id' => 'ASC'], $columns);
        $rows    = $result['rows'];

        $filename = 'products_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_column($columns, 'label'));

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $line[] = $row[$col['field']] ?? '';
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    private function findOrRedirect(): array
    {
        $userId  = $this->userId();
        $id      = (int) ($_GET['id'] ?? 0);
        $product = Product::findForUser($userId, $id);
        if (!$product) {
            $this->redirect($this->url(['c' => 'product', 'a' => 'index']));
        }
        return Product::hydrate($product);
    }

    private function resolveCategory(array $data, string $newName, int $userId): ?int
    {
        $catId = $data['category_id'] ?? '';
        if ($catId === 'NEW' && trim($newName) !== '') {
            return Category::create([
                'user_id' => $userId,
                'name'    => trim($newName),
            ]);
        }
        if (is_numeric($catId) && (int)$catId > 0) {
            $cat = Category::findForUser($userId, (int)$catId);
            return $cat ? (int)$catId : null;
        }
        return null;
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url(['c' => 'product', 'a' => 'index']));
        }
        $this->verifyCsrf();
    }

    private function verifyCsrfJson(): void
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $requestToken = $_POST['_token'] ?? '';
        if ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
            $this->json(['ok' => false, 'error' => '请求令牌无效，请刷新页面后重试。'], 403);
        }
    }
}
