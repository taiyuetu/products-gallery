<?php
require_once ROOT . '/core/Database.php';
require_once ROOT . '/core/Controller.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/models/Category.php';

class CategoryController extends Controller
{
    public function index(): void
    {
        $filters = $_GET['f'] ?? [];
        $q       = trim($_GET['q'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $result  = Category::search($filters, $page, 50, $q);

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
        Category::create($_POST['category'] ?? []);
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
        $id = (int) ($_POST['id'] ?? 0);
        Category::update($id, $_POST['category'] ?? []);
        $this->redirect($this->url(['c' => 'category', 'a' => 'index', 'msg' => 'updated']));
    }

    public function delete(): void
    {
        $this->requirePost();
        $this->requireAdmin();
        $id = (int) ($_POST['id'] ?? 0);
        // Nullify the FK on every product that belongs to this category
        // so no product is left pointing at a non-existent category.
        $db = Database::getInstance();
        $db->prepare('UPDATE products SET category_id = NULL WHERE category_id = ?')
           ->execute([$id]);
        Category::delete($id);
        $this->redirect($this->url(['c' => 'category', 'a' => 'index', 'msg' => 'deleted']));
    }

    private function findOrRedirect(): array
    {
        $id       = (int) ($_GET['id'] ?? 0);
        $category = Category::find($id);
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
