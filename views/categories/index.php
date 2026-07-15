<div class="d-flex align-items-center mb-3">
  <h5 class="mb-0 fw-semibold">
    <i class="bi bi-tags text-primary me-1"></i>分类管理
    <span class="badge bg-secondary ms-1"><?= number_format($total) ?></span>
  </h5>
  <div class="d-flex gap-2 ms-auto">
    <a href="?c=category&a=create" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>新增分类
    </a>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" action="" class="m-0">
      <input type="hidden" name="c" value="category">
      <input type="hidden" name="a" value="index">
      <div class="input-group">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="搜索分类..." value="<?= e($q ?? '') ?>">
        <button class="btn btn-primary px-4" type="submit">搜索</button>
        <?php if (!empty($q)): ?>
          <a href="?c=category&a=index" class="btn btn-outline-secondary">清除</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-striped mb-0 align-middle">
        <thead class="table-light text-nowrap">
          <tr>
            <th>ID</th>
            <th>名称</th>
            <th>描述</th>
            <th>商品数</th>
            <th>创建时间</th>
            <th class="text-end">操作</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
          <tr>
            <td colspan="5" class="text-center py-5 text-muted">
              <i class="bi bi-inbox fs-2 d-block mb-2 text-black-50"></i>
              暂无分类数据
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($rows as $row): ?>
          <tr>
            <td class="text-muted"><?= e($row['id']) ?></td>
            <td class="fw-medium text-dark"><?= e($row['name']) ?></td>
            <td class="text-truncate" style="max-width:300px;"><?= e($row['description'] ?? '') ?></td>
            <td><span class="badge bg-info text-dark rounded-pill"><?= e($row['product_count'] ?? 0) ?></span></td>
            <td class="text-muted small"><?= e($row['created_at']) ?></td>
            <td class="text-end text-nowrap">
              <a href="?c=category&a=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="编辑">
                <i class="bi bi-pencil-square"></i>
              </a>
              <form method="POST" action="?c=category&a=delete" class="d-inline-block m-0" onsubmit="return confirm('确定要删除分类 [<?= e($row['name']) ?>] 吗？此操作不可恢复！');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="删除">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="card-footer bg-white py-3 border-top-0">
    <div class="d-flex justify-content-between align-items-center">
      <div class="small text-muted">
        显示 <?= min($total, ($page - 1) * $perPage + 1) ?> - <?= min($total, $page * $perPage) ?> 条，共 <?= $total ?> 条
      </div>
      <div class="d-flex align-items-center gap-3">
        <?php
        $baseParams = ['c' => 'category', 'a' => 'index'];
        if (!empty($q)) $baseParams['q'] = $q;
        $prevDisabled = $page <= 1;
        $nextDisabled = $page >= $totalPages;
        ?>
        <a href="?<?= http_build_query(array_merge($baseParams, ['page' => $page - 1])) ?>"
           class="btn btn-outline-secondary btn-sm px-3 <?= $prevDisabled ? 'disabled' : '' ?>">
           <i class="bi bi-chevron-left me-1"></i>上一页
        </a>
        <span class="text-muted small">
          第 <strong class="text-dark"><?= $page ?></strong> / <?= $totalPages ?> 页
        </span>
        <a href="?<?= http_build_query(array_merge($baseParams, ['page' => $page + 1])) ?>"
           class="btn btn-outline-secondary btn-sm px-3 <?= $nextDisabled ? 'disabled' : '' ?>">
           下一页<i class="bi bi-chevron-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
