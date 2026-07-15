<?php
require_once ROOT . '/core/Database.php';
require_once ROOT . '/core/Controller.php';
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/models/Product.php';
require_once ROOT . '/models/Category.php';
require_once ROOT . '/models/UserField.php';

class CategoryController extends Controller
{
    public function index(): void
    {
        $userId  = $this->userId();
        $filters = $_GET['f'] ?? [];
        $q       = trim($_GET['q'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $result  = Category::search($userId, $filters, $page, 50, $q);

        $this->render('categories/index', [
            'filters'    => $filters,
            'q'          => $q,
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $result['page'],
            'perPage'    => $result['perPage'],
            'totalPages' => $result['totalPages'],
        ]);
    }

    public function create(): void
    {
        $this->render('categories/form', [
            'category' => [],
            'isEdit'   => false,
            'title'    => '新增分类',
        ]);
    }

    public function store(): void
    {
        $this->requirePost();
        $userId = $this->userId();
        $data = $_POST['category'] ?? [];
        Category::create([
            'user_id'     => $userId,
            'name'        => trim((string)($data['name'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
        ]);
        $this->redirect($this->url(['c' => 'category', 'a' => 'index', 'msg' => 'created']));
    }

    public function edit(): void
    {
        $category = $this->findOrRedirect();
        $this->render('categories/form', [
            'category' => $category,
            'isEdit'   => true,
            'title'    => '编辑分类',
        ]);
    }

    public function update(): void
    {
        $this->requirePost();
        $userId = $this->userId();
        $id = (int) ($_POST['id'] ?? 0);
        $data = $_POST['category'] ?? [];
        Category::updateForUser($userId, $id, [
            'name'        => trim((string)($data['name'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
        ]);
        $this->redirect($this->url(['c' => 'category', 'a' => 'index', 'msg' => 'updated']));
    }

    public function delete(): void
    {
        $this->requirePost();
        $userId = $this->userId();
        $id = (int) ($_POST['id'] ?? 0);
        $db = Database::getInstance();
        $db->prepare('UPDATE products SET category_id = NULL WHERE user_id = ? AND category_id = ?')
           ->execute([$userId, $id]);
        Category::deleteForUser($userId, $id);
        $this->redirect($this->url(['c' => 'category', 'a' => 'index', 'msg' => 'deleted']));
    }

    private function findOrRedirect(): array
    {
        $userId   = $this->userId();
        $id       = (int) ($_GET['id'] ?? 0);
        $category = Category::findForUser($userId, $id);
        if (!$category) {
            $this->redirect($this->url(['c' => 'category', 'a' => 'index']));
        }
        return $category;
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url(['c' => 'category', 'a' => 'index']));
        }
        $this->verifyCsrf();
    }
}
