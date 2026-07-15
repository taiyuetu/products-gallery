<?php
$msg = $msg ?? null;
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-semibold">
    <i class="bi bi-sliders text-primary me-1"></i>字段管理
  </h5>
  <a href="?c=import&a=index" class="btn btn-outline-primary btn-sm">
    <i class="bi bi-upload me-1"></i>通过 CSV 导入新增字段
  </a>
</div>

<div class="alert alert-light border small">
  <i class="bi bi-info-circle me-1"></i>
  字段由您上传的 CSV 表头自动创建（每位用户独立）。可在此调整列表显示、筛选、主键（导入去重/图片匹配）与 OEM 匹配字段。
  停用字段不会删除已有数据。
</div>

<?php if (empty($fields)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center text-muted py-5">
    <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
    暂无字段。请先 <a href="?c=import&a=index">导入 CSV</a>。
  </div>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($fields as $f): ?>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100 <?= !(int)$f['active'] ? 'opacity-75' : '' ?>">
      <div class="card-body">
        <form method="POST" action="?c=field&a=update">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <code class="small text-muted"><?= e($f['field_key']) ?></code>
              <?php if ((int)$f['is_primary']): ?>
                <span class="badge bg-primary ms-1">主键</span>
              <?php endif; ?>
              <?php if ((int)$f['is_oem']): ?>
                <span class="badge bg-success ms-1">OEM</span>
              <?php endif; ?>
            </div>
            <input type="number" name="sort_order" class="form-control form-control-sm" style="width:80px"
                   value="<?= (int)$f['sort_order'] ?>" title="排序">
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">标签</label>
            <input type="text" name="label" class="form-control form-control-sm" value="<?= e($f['label']) ?>">
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small mb-1">类型</label>
              <select name="type" class="form-select form-select-sm">
                <option value="text" <?= $f['type']==='text'?'selected':'' ?>>text</option>
                <option value="number" <?= $f['type']==='number'?'selected':'' ?>>number</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small mb-1">分组</label>
              <input type="text" name="tab" class="form-control form-control-sm" value="<?= e($f['tab']) ?>">
            </div>
          </div>
          <div class="d-flex flex-wrap gap-3 mb-3 small">
            <label class="form-check-label">
              <input type="checkbox" name="list" value="1" class="form-check-input" <?= (int)$f['list'] ? 'checked' : '' ?>> 列表显示
            </label>
            <label class="form-check-label">
              <input type="checkbox" name="filterable" value="1" class="form-check-input" <?= (int)$f['filterable'] ? 'checked' : '' ?>> 可筛选
            </label>
            <label class="form-check-label">
              <input type="checkbox" name="active" value="1" class="form-check-input" <?= (int)$f['active'] ? 'checked' : '' ?>> 启用
            </label>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-sm btn-primary">保存</button>
            <?php if (!(int)$f['is_primary']): ?>
            <button type="submit" name="make_primary" value="1" class="btn btn-sm btn-outline-primary"
                    onclick="return confirm('将此字段设为主键？用于导入去重与图片文件名匹配。')">设为主键</button>
            <?php endif; ?>
            <?php if (!(int)$f['is_oem']): ?>
            <button type="submit" name="make_oem" value="1" class="btn btn-sm btn-outline-success">设为 OEM</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
