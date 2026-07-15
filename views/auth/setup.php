<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>初始化设置 – 产品数据库</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: linear-gradient(135deg, #198754 0%, #146c43 100%); min-height: 100vh; }
  .setup-card { border-radius: 1rem; }
</style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">

<div class="container" style="max-width: 460px;">

  <!-- Brand -->
  <div class="text-center mb-4">
    <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow"
         style="width:64px;height:64px;">
      <i class="bi bi-shield-lock-fill text-success" style="font-size:2.2rem;"></i>
    </div>
    <h4 class="text-white fw-bold mb-0">初始化管理员账户</h4>
    <p class="text-white-50 small mt-1">首次使用时需要创建管理员账户</p>
  </div>

  <!-- Card -->
  <div class="card shadow-lg setup-card border-0">
    <div class="card-body p-4">

      <div class="alert alert-info py-2 small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        此页面仅在没有任何用户时可访问。创建完成后将自动关闭。
      </div>

      <?php if (!empty($error)): ?>
      <div class="alert alert-danger py-2 small mb-3">
        <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="?c=auth&a=setup" autocomplete="off">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <div class="mb-3">
          <label class="form-label fw-medium" for="display_name">
            <i class="bi bi-person-badge me-1 text-muted"></i>显示名称
            <span class="text-muted fw-normal">(可选)</span>
          </label>
          <input
            type="text"
            id="display_name"
            name="display_name"
            class="form-control"
            placeholder="例如：管理员"
            value="<?= htmlspecialchars($_POST['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            autofocus
          >
        </div>

        <div class="mb-3">
          <label class="form-label fw-medium" for="username">
            <i class="bi bi-person me-1 text-muted"></i>用户名 <span class="text-danger">*</span>
          </label>
          <input
            type="text"
            id="username"
            name="username"
            class="form-control"
            placeholder="至少 3 个字符，仅限字母/数字/下划线"
            value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
          >
        </div>

        <div class="mb-3">
          <label class="form-label fw-medium" for="password">
            <i class="bi bi-lock me-1 text-muted"></i>密码 <span class="text-danger">*</span>
          </label>
          <div class="input-group">
            <input
              type="password"
              id="password"
              name="password"
              class="form-control"
              placeholder="至少 8 个字符"
              required
            >
            <button type="button" class="btn btn-outline-secondary" id="togglePwd" tabindex="-1">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-medium" for="confirm_password">
            <i class="bi bi-lock-fill me-1 text-muted"></i>确认密码 <span class="text-danger">*</span>
          </label>
          <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            class="form-control"
            placeholder="再次输入密码"
            required
          >
        </div>

        <div class="d-grid">
          <button type="submit" class="btn btn-success btn-lg">
            <i class="bi bi-check-circle me-1"></i>创建管理员账户
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
