<?php
require_once ROOT . '/core/Database.php';
require_once ROOT . '/core/Controller.php';
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/models/UserField.php';
require_once ROOT . '/models/Product.php';

class FieldController extends Controller
{
    public function __construct()
    {
        UserField::ensureTable();
    }

    public function index(): void
    {
        $userId = $this->userId();
        $fields = UserField::rawForUser($userId);
        $this->render('fields/index', [
            'fields' => $fields,
            'msg'    => $_GET['msg'] ?? null,
        ]);
    }

    public function update(): void
    {
        $this->requirePost();
        $userId  = $this->userId();
        $fieldId = (int) ($_POST['id'] ?? 0);
        $field   = UserField::findForUser($userId, $fieldId);
        if (!$field) {
            $this->redirect($this->url(['c' => 'field', 'a' => 'index']));
        }

        UserField::updateFlags($userId, $fieldId, [
            'label'      => trim((string)($_POST['label'] ?? $field['label'])),
            'type'       => in_array($_POST['type'] ?? '', ['text', 'number'], true) ? $_POST['type'] : 'text',
            'tab'        => trim((string)($_POST['tab'] ?? '字段')) ?: '字段',
            'sort_order' => (int) ($_POST['sort_order'] ?? $field['sort_order']),
            'filterable' => !empty($_POST['filterable']),
            'list'       => !empty($_POST['list']),
            'active'     => !empty($_POST['active']),
        ]);

        if (!empty($_POST['make_primary'])) {
            UserField::setPrimary($userId, $fieldId);
            $this->refreshPrimaryValues($userId);
        }
        if (!empty($_POST['make_oem'])) {
            UserField::setOem($userId, $fieldId);
            $this->refreshOemValues($userId);
        }

        $this->redirect($this->url(['c' => 'field', 'a' => 'index', 'msg' => 'updated']));
    }

    public function setPrimary(): void
    {
        $this->requirePost();
        $userId  = $this->userId();
        $fieldId = (int) ($_POST['id'] ?? 0);
        if (UserField::findForUser($userId, $fieldId)) {
            UserField::setPrimary($userId, $fieldId);
            $this->refreshPrimaryValues($userId);
        }
        $this->redirect($this->url(['c' => 'field', 'a' => 'index', 'msg' => 'primary']));
    }

    public function setOem(): void
    {
        $this->requirePost();
        $userId  = $this->userId();
        $fieldId = (int) ($_POST['id'] ?? 0);
        if (UserField::findForUser($userId, $fieldId)) {
            UserField::setOem($userId, $fieldId);
            $this->refreshOemValues($userId);
        }
        $this->redirect($this->url(['c' => 'field', 'a' => 'index', 'msg' => 'oem']));
    }

    private function refreshPrimaryValues(int $userId): void
    {
        $col = UserField::primaryField($userId);
        if (!$col) return;
        $key = $col['field'];
        $rows = Product::all([], ['id' => 'ASC'], 0, 0, '', $userId);
        foreach ($rows as $row) {
            $attrs = Product::parseAttrs($row['attrs'] ?? null);
            $val = (string)($attrs[$key] ?? '');
            Product::updateAttrs($userId, (int)$row['id'], ['primary_value' => $val]);
        }
    }

    private function refreshOemValues(int $userId): void
    {
        $col = UserField::oemField($userId);
        if (!$col) return;
        $key = $col['field'];
        $rows = Product::all([], ['id' => 'ASC'], 0, 0, '', $userId);
        foreach ($rows as $row) {
            $attrs = Product::parseAttrs($row['attrs'] ?? null);
            $val = (string)($attrs[$key] ?? '');
            Product::updateAttrs($userId, (int)$row['id'], ['oem_value' => $val]);
        }
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url(['c' => 'field', 'a' => 'index']));
        }
        $this->verifyCsrf();
    }
}
