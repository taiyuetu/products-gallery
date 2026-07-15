<?php
require_once ROOT . '/core/Database.php';
require_once ROOT . '/core/Controller.php';
require_once ROOT . '/models/User.php';

class AuthController extends Controller
{
    public function __construct()
    {
        // Auto-create the users table if it was never migrated
        User::ensureTable();
    }

    // ── Login ──────────────────────────────────────────────────────────────────

    public function login(): void
    {
        // First-run: no users yet → redirect to setup wizard
        if (User::isEmpty()) {
            $this->redirect($this->url(['c' => 'auth', 'a' => 'setup']));
        }

        // Already logged in → go home
        if (!empty($_SESSION['user_id'])) {
            $this->redirect($this->url(['c' => 'product', 'a' => 'index']));
        }

        $error      = null;
        $setupDone  = isset($_GET['msg']) && $_GET['msg'] === 'setup_done';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            $user = User::findByUsername($username);

            if ($user && User::verifyPassword($password, $user['password_hash'])) {
                // Regenerate session id to prevent fixation
                session_regenerate_id(true);
                $_SESSION['user_id']      = (int) $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['display_name'] = $user['display_name'] ?: $user['username'];
                $_SESSION['role']         = $user['role'];
                $this->redirect($this->url(['c' => 'product', 'a' => 'index']));
            }

            $error = '用户名或密码错误，请重试';
        }

        $this->renderAuth('auth/login', compact('error', 'setupDone'));
    }

    // ── Logout ─────────────────────────────────────────────────────────────────

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        $this->redirect($this->url(['c' => 'auth', 'a' => 'login']));
    }

    // ── First-run setup ────────────────────────────────────────────────────────

    public function setup(): void
    {
        // Only accessible when no users exist
        if (!User::isEmpty()) {
            $this->redirect($this->url(['c' => 'auth', 'a' => 'login']));
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $username    = trim($_POST['username'] ?? '');
            $password    = $_POST['password'] ?? '';
            $confirm     = $_POST['confirm_password'] ?? '';
            $displayName = trim($_POST['display_name'] ?? '');

            if (strlen($username) < 3) {
                $error = '用户名至少需要 3 个字符';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $error = '用户名只能包含字母、数字和下划线';
            } elseif (strlen($password) < 8) {
                $error = '密码至少需要 8 个字符';
            } elseif ($password !== $confirm) {
                $error = '两次输入的密码不一致';
            } else {
                User::createAdmin($username, $password, $displayName);
                $this->redirect($this->url(['c' => 'auth', 'a' => 'login', 'msg' => 'setup_done']));
            }
        }

        $this->renderAuth('auth/setup', compact('error'));
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    /**
     * Render an auth view with its own standalone HTML shell
     * (no main nav layout – user is not logged in yet).
     */
    private function renderAuth(string $view, array $data = []): never
    {
        extract($data, EXTR_SKIP);
        require ROOT . '/views/' . $view . '.php';
        exit;
    }
}
