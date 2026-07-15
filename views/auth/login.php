<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录 – 产品数据库</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); min-height: 100vh; }
  .login-card { border-radius: 1rem; }
  .brand-icon { font-size: 2.5rem; color: #0d6efd; }
</style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">

<div class="container" style="max-width: 420px;">

  <!-- Brand -->
  <div class="text-center mb-4">
    <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow"
         style="width:64px;height:64px;">
      <i class="bi bi-database-fill brand-icon"></i>
    </div>
    <h4 class="text-white fw-bold mb-0">产品数据库</h4>
    <p class="text-white-50 small mt-1">请登录以继续</p>
  </div>

  <!-- Card -->
  <div class="card shadow-lg login-card border-0">
    <div class="card-body p-4">

      <?php if (!empty($setupDone)): ?>
      <div class="alert alert-success py-2 small mb-3">
        <i class="bi bi-check-circle me-1"></i>管理员账户已创建，请登录
      </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
      <div class="alert alert-danger py-2 small mb-3">
        <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="?c=auth&a=login" autocomplete="on">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <div class="mb-3">
          <label class="form-label fw-medium" for="username">
            <i class="bi bi-person me-1 text-muted"></i>用户名
          </label>
          <input
            type="text"
            id="username"
            name="username"
            class="form-control form-control-lg"
            placeholder="请输入用户名"
            value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            autocomplete="username"
            required
            autofocus
          >
        </div>

        <div class="mb-4">
          <label class="form-label fw-medium" for="password">
            <i class="bi bi-lock me-1 text-muted"></i>密码
          </label>
          <div class="input-group">
            <input
              type="password"
              id="password"
              name="password"
              class="form-control form-control-lg"
              placeholder="请输入密码"
              autocomplete="current-password"
              required
            >
            <button type="button" class="btn btn-outline-secondary" id="togglePwd" tabindex="-1" title="显示/隐藏密码">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <div class="d-grid">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-box-arrow-in-right me-1"></i>登 录
          </button>
        </div>

      </form>

    </div>
  </div>

  <p class="text-center text-white-50 small mt-3">
    &copy; <?= date('Y') ?> 产品数据库管理系统
  </p>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePwd').addEventListener('click', function () {
  const pwd  = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (pwd.type === 'password') {
    pwd.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    pwd.type = 'password';
    icon.className = 'bi bi-eye';
  }
});
</script>
</body>
</html>
