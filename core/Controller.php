<?php
/**
 * Controller – base class for all controllers.
 */
abstract class Controller
{
    protected function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = ROOT . '/views/' . $view . '.php';
        if (!file_exists($viewFile)) {
            throw new RuntimeException("View not found: {$view}");
        }
        require ROOT . '/views/layout/header.php';
        require $viewFile;
        require ROOT . '/views/layout/footer.php';
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    protected function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function url(array $params): string
    {
        return '?' . http_build_query($params);
    }

    protected function verifyCsrf(): void
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $requestToken = $_POST['_token'] ?? '';
        if ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
            http_response_code(403);
            exit('请求令牌无效，请刷新页面后重试。');
        }
    }

    protected function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('权限不足：此操作需要管理员权限。');
        }
    }

    protected function isAdmin(): bool
    {
        return Auth::isAdmin();
    }

    protected function userId(): int
    {
        return Auth::requireUser();
    }

    /** Load this user's active field defs shaped for views/import/export. */
    protected function userColumns(bool $activeOnly = true): array
    {
        return UserField::forUser($this->userId(), $activeOnly);
    }
}
