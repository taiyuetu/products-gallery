<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>注册 – 产品图库 SaaS</title>
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

  <div class="text-center mb-4">
    <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow"
         style="width:64px;height:64px;">
      <i class="bi bi-person-plus-fill brand-icon"></i>
    </div>
    <h4 class="text-white fw-bold mb-0">创建账户</h4>
    <p class="text-white-50 small mt-1">注册后拥有独立的产品库与字段结构</p>
  </div>

  <div class="card shadow-lg login-card border-0">
    <div class="card-body p-4">

      <?php if (!empty($error)): ?>
      <div class="alert alert-danger py-2 small mb-3">
        <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="?c=auth&a=register" autocomplete="on">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <div class="mb-3">
          <label class="form-label fw-medium" for="display_name">显示名称</label>
          <input type="text" id="display_name" name="display_name" class="form-control"
                 value="<?= htmlspecialchars($_POST['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="可选">
        </div>

        <div class="mb-3">
          <label class="form-label fw-medium" for="username">用户名 <span class="text-danger">*</span></label>
          <input type="text" id="username" name="username" class="form-control" required
                 placeholder="至少 3 个字符，字母/数字/下划线"
                 value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 autocomplete="username">
        </div>

        <div class="mb-3">
          <label class="form-label fw-medium" for="password">密码 <span class="text-danger">*</span></label>
          <input type="password" id="password" name="password" class="form-control" required
                 placeholder="至少 8 个字符" autocomplete="new-password">
        </div>

        <div class="mb-4">
          <label class="form-label fw-medium" for="confirm_password">确认密码 <span class="text-danger">*</span></label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                 autocomplete="new-password">
        </div>

        <div class="d-grid">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-person-plus me-1"></i>注 册
          </button>
        </div>

        <p class="text-center small text-muted mt-3 mb-0">
          已有账户？ <a href="?c=auth&a=login">返回登录</a>
        </p>
      </form>

    </div>
  </div>

</div>
</body>
</html>
