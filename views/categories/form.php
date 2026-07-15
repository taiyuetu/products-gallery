<!-- ── Page header ──────────────────────────────────────────────── -->
<div class="d-flex align-items-center mb-3">
  <h5 class="mb-0 fw-semibold">
    <i class="bi bi-tags text-primary me-1"></i><?= e($title) ?>
  </h5>
  <div class="ms-auto">
    <a href="?c=category&a=index" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>返回列表
    </a>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <form method="POST" action="?c=category&a=<?= $isEdit ? 'update' : 'store' ?>">
          <?= csrf_field() ?>
          <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= e($category['id']) ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label fw-medium">分类名称 <span class="text-danger">*</span></label>
            <input
              type="text"
              name="category[name]"
              class="form-control"
              placeholder="输入分类名称..."
              value="<?= e($category['name'] ?? '') ?>"
              required
              autofocus
            >
          </div>

          <div class="mb-4">
            <label class="form-label fw-medium">描述</label>
            <textarea
              name="category[description]"
              class="form-control"
              rows="4"
              placeholder="输入分类描述（可选）..."
            ><?= e($category['description'] ?? '') ?></textarea>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-check-lg me-1"></i><?= $isEdit ? '保存修改' : '创建分类' ?>
            </button>
            <a href="?c=category&a=index" class="btn btn-outline-secondary">取消</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
