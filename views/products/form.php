<?php
$action = $isEdit
    ? '?c=product&a=update'
    : '?c=product&a=store';

$tabs = [];
foreach ($columns as $col) {
    $tabs[$col['tab']][] = $col;
}
$tabKeys  = array_keys($tabs);
$firstTab = $tabKeys[0] ?? '';

$gallery  = Product::parseGallery($product['gallery'] ?? null);
$base     = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
?>

<div class="d-flex align-items-center mb-3">
  <a href="<?= $isEdit ? '?c=product&a=show&id=' . (int)$product['id'] : '?c=product&a=index' ?>"
     class="btn btn-sm btn-outline-secondary me-2">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-semibold">
    <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?> text-primary me-1"></i>
    <?= e($title) ?>
    <?php if ($isEdit): ?>
      <small class="text-muted fw-normal ms-2"># <?= e($product['primary_value'] ?? '') ?></small>
    <?php endif; ?>
  </h5>
</div>

<form method="POST" action="<?= e($action) ?>" autocomplete="off" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?>
  <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
  <?php endif; ?>

  <!-- Product Gallery -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <label class="form-label fw-medium small mb-2">
        <i class="bi bi-images text-primary me-1"></i>产品图片
        <?php if (!empty($gallery)): ?>
          <span class="badge bg-secondary ms-1"><?= count($gallery) ?></span>
        <?php endif; ?>
      </label>

      <?php if (!empty($gallery)): ?>
      <div class="gallery-grid mb-3" id="currentGallery">
        <?php foreach ($gallery as $i => $rel): ?>
        <div class="gallery-item" data-path="<?= e($rel) ?>">
          <img src="<?= e($base . '/' . ltrim($rel, '/')) ?>" alt="图片 <?= $i + 1 ?>"
               class="gallery-thumb">
          <div class="gallery-item-actions">
            <label class="gallery-remove-check" title="选中以删除">
              <input type="checkbox" name="remove_gallery[]" value="<?= e($rel) ?>">
              <i class="bi bi-trash"></i>
            </label>
          </div>
          <span class="gallery-index"><?= $i + 1 ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ($isEdit): ?>
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="removeAllImages" name="remove_all_images" value="1">
        <label class="form-check-label small text-danger" for="removeAllImages">
          <i class="bi bi-trash me-1"></i>删除全部现有图片
        </label>
      </div>
      <?php endif; ?>
      <?php else: ?>
      <div id="galleryEmpty" class="border rounded d-flex align-items-center justify-content-center bg-light mb-3"
           style="height:120px;">
        <span class="text-muted small">
          <i class="bi bi-images fs-3 d-block text-center opacity-50"></i>暂无图片
        </span>
      </div>
      <?php endif; ?>

      <!-- New uploads preview -->
      <div class="gallery-grid mb-2" id="newPreviewGrid" style="display:none;"></div>

      <label class="form-label fw-medium small mb-1">上传新图片</label>
      <input type="file" name="images[]" id="imageInput"
             accept="image/*" multiple
             class="form-control form-control-sm">
      <div class="form-text small">
        支持 jpg / png / gif / webp，最大 8MB / 张，可同时选择多张图片。
      </div>
    </div>
  </div>

  <!-- Category Selection -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-medium small mb-1">所属分类</label>
          <select name="product[category_id]" class="form-select form-select-sm" id="categorySelect" onchange="toggleNewCategory(this)">
            <option value="">-- 无分类 --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                <?= e($cat['name']) ?>
              </option>
            <?php endforeach; ?>
            <option value="NEW" class="fw-bold text-primary">+ 添加新分类...</option>
          </select>
        </div>
        <div class="col-md-4" id="newCategoryWrapper" style="display:none;">
          <label class="form-label fw-medium small mb-1 text-primary">新分类名称 <span class="text-danger">*</span></label>
          <input type="text" name="new_category_name" class="form-control form-control-sm border-primary" placeholder="输入新分类名称...">
        </div>
      </div>
    </div>
  </div>

  <!-- Tab navigation -->
  <ul class="nav nav-tabs mb-0" id="formTabs" role="tablist">
    <?php foreach ($tabKeys as $i => $tab): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
              id="tab-<?= $i ?>-btn"
              data-bs-toggle="tab" data-bs-target="#tab-<?= $i ?>"
              type="button" role="tab">
        <?= e($tab) ?>
      </button>
    </li>
    <?php endforeach; ?>
  </ul>

  <div class="tab-content border border-top-0 rounded-bottom bg-white p-3 mb-3 shadow-sm">
    <?php foreach ($tabKeys as $i => $tab): ?>
    <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>"
         id="tab-<?= $i ?>" role="tabpanel">
      <div class="row g-3 pt-1">
        <?php foreach ($tabs[$tab] as $col): ?>
        <div class="col-md-4 col-sm-6">
          <label class="form-label fw-medium small mb-1"><?= e($col['label']) ?></label>
          <input
            type="<?= $col['type'] === 'number' ? 'text' : 'text' ?>"
            class="form-control form-control-sm"
            name="product[<?= e($col['field']) ?>]"
            value="<?= e($product[$col['field']] ?? '') ?>"
            placeholder="<?= e($col['label']) ?>">
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary px-4">
      <i class="bi bi-check-lg me-1"></i><?= $isEdit ? '保存修改' : '创建产品' ?>
    </button>
    <a href="<?= $isEdit ? '?c=product&a=show&id=' . (int)$product['id'] : '?c=product&a=index' ?>"
       class="btn btn-outline-secondary">取消</a>
  </div>
</form>

<script>
function toggleNewCategory(select) {
  var wrapper = document.getElementById('newCategoryWrapper');
  var input = wrapper.querySelector('input');
  if (select.value === 'NEW') {
    wrapper.style.display = 'block';
    input.required = true;
    input.focus();
  } else {
    wrapper.style.display = 'none';
    input.required = false;
  }
}

document.addEventListener('DOMContentLoaded', function() {
  toggleNewCategory(document.getElementById('categorySelect'));

  var input = document.getElementById('imageInput');
  var grid  = document.getElementById('newPreviewGrid');
  if (input && grid) {
    input.addEventListener('change', function() {
      grid.innerHTML = '';
      var files = input.files || [];
      if (files.length === 0) { grid.style.display = 'none'; return; }
      grid.style.display = 'grid';
      for (var i = 0; i < files.length; i++) {
        if (!files[i].type.startsWith('image/')) continue;
        var div = document.createElement('div');
        div.className = 'gallery-item gallery-item-new';
        var img = document.createElement('img');
        img.src = URL.createObjectURL(files[i]);
        img.className = 'gallery-thumb';
        img.alt = files[i].name;
        var badge = document.createElement('span');
        badge.className = 'gallery-index bg-success';
        badge.textContent = '新';
        div.appendChild(img);
        div.appendChild(badge);
        grid.appendChild(div);
      }
      var empty = document.getElementById('galleryEmpty');
      if (empty) empty.style.display = 'none';
    });
  }
});
</script>
