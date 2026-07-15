<?php
/**
 * Controller �C base class for all controllers.
 * Provides view rendering, redirects and JSON responses.
 */
abstract class Controller
{
    /**
     * Render a view wrapped in layout/header + layout/footer.
     * Variables in $data are extracted into the view scope.
     */
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

    /** Build a query-string URL from an array of params */
    protected function url(array $params): string
    {
        return '?' . http_build_query($params);
    }

    /**
     * Verify the CSRF token submitted with a POST request.
     * Terminates with a 403 response if the token is missing or invalid.
     */
    protected function verifyCsrf(): void
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $requestToken = $_POST['_token'] ?? '';
        if ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
            http_response_code(403);
            exit('请求令牌无效，请刷新页面后重试。');
        }
    }

    /** Require the current session user to have the admin role. */
    protected function requireAdmin(): void
    {
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('权限不足：此操作需要管理员权限。');
        }
    }

    protected function isAdmin(): bool
    {
        return ($_SESSION['role'] ?? '') === 'admin';
    }
}
