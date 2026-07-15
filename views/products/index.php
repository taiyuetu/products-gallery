<?php
$exportUrl = '?' . http_build_query(array_merge(
    ['c' => 'product', 'a' => 'export'],
    empty($filters) ? [] : ['f' => $filters],
    empty($q) ? [] : ['q' => $q]
));

$listCols = array_values(array_filter($columns, fn($c) => $c['list']));
$filterCols = array_values(array_filter($columns, fn($c) => $c['filterable']));
$activeFilters = count(array_filter($filters ?? [], fn($v) => trim((string)$v) !== ''));
$imgBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

$sort = $sort ?? 'updated_at';
$order = $order ?? 'DESC';

$getSortUrl = function($field) use ($filters, $q, $sort, $order) {
    $newOrder = ($sort === $field && $order === 'ASC') ? 'DESC' : 'ASC';
    return '?' . http_build_query(array_merge(
        ['c' => 'product', 'a' => 'index', 'sort' => $field, 'order' => $newOrder],
        !empty($filters) ? ['f' => $filters] : [],
        !empty($q) ? ['q' => $q] : []
    ));
};

$getSortIcon = function($field) use ($sort, $order) {
    if ($sort !== $field) return '<i class="bi bi-arrow-down-up ms-1 opacity-25 small"></i>';
    return $order === 'ASC' 
        ? '<i class="bi bi-sort-up ms-1 text-primary"></i>' 
        : '<i class="bi bi-sort-down ms-1 text-primary"></i>';
};
?>

<!-- ── Page header ──────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-semibold">
    <i class="bi bi-box-seam text-primary me-1"></i>产品列表
    <span class="badge bg-secondary ms-1"><?= number_format($total) ?></span>
  </h5>
  <div class="d-flex gap-2">
    <form method="POST" action="?c=product&a=deleteAll" class="m-0">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('确定要清空【您账户下】的所有产品数据吗？此操作不可恢复！');">
        <i class="bi bi-trash me-1"></i>清空我的数据
      </button>
    </form>
    <a href="<?= e($exportUrl) ?>" class="btn btn-outline-success btn-sm">
      <i class="bi bi-download me-1"></i>导出 CSV<?= $activeFilters ? '（筛选结果）' : '（全部）' ?>
    </a>
    <a href="?c=product&a=create" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>新增产品
    </a>
  </div>
</div>

<?php if (empty($columns)): ?>
<div class="alert alert-info">
  <i class="bi bi-info-circle me-1"></i>
  您的产品字段尚未定义。请先
  <a href="?c=import&a=index" class="alert-link">导入 CSV</a>
  ，系统将根据表头自动创建您的字段结构。
</div>
<?php endif; ?>

<!-- ── Global Search ──────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" action="" class="m-0">
      <input type="hidden" name="c" value="product">
      <input type="hidden" name="a" value="index">
      <?php foreach ($filters as $k => $v): if (trim((string)$v) !== ''): ?>
        <input type="hidden" name="f[<?= e($k) ?>]" value="<?= e($v) ?>">
      <?php endif; endforeach; ?>
      <input type="hidden" name="sort" value="<?= e($sort) ?>">
      <input type="hidden" name="order" value="<?= e($order) ?>">
      <div class="input-group">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="全局搜索 (可搜索任何字段)..." value="<?= e($q ?? '') ?>">
        <button class="btn btn-primary px-4" type="submit">搜索</button>
        <?php if (!empty($q)): ?>
          <a href="?<?= http_build_query(array_merge(['c'=>'product','a'=>'index'], !empty($filters) ? ['f'=>$filters] : [])) ?>" class="btn btn-outline-secondary">清除</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- ── Filter panel ─────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white d-flex align-items-center justify-content-between py-2"
       role="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
    <span class="fw-medium text-primary">
      <i class="bi bi-funnel me-1"></i>筛选条件
      <?php if ($activeFilters): ?>
        <span class="badge bg-primary ms-1"><?= $activeFilters ?></span>
      <?php endif; ?>
    </span>
    <i class="bi bi-chevron-down text-muted small"></i>
  </div>
  <div id="filterPanel" class="collapse <?= $activeFilters ? 'show' : '' ?>">
    <div class="card-body py-3">
      <form method="GET" action="">
        <input type="hidden" name="c" value="product">
        <input type="hidden" name="a" value="index">
        <?php if (!empty($q)): ?>
        <input type="hidden" name="q" value="<?= e($q) ?>">
        <?php endif; ?>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <input type="hidden" name="order" value="<?= e($order) ?>">
        <div class="row g-2">
          <div class="col-md-3 col-sm-4 col-6">
            <label class="form-label form-label-sm mb-1">所属分类</label>
            <select name="f[category_id]" class="form-select form-select-sm">
              <option value="">-- 全部 --</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($filters['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                  <?= e($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php foreach ($filterCols as $col): ?>
          <div class="col-md-3 col-sm-4 col-6">
            <label class="form-label form-label-sm mb-1"><?= e($col['label']) ?></label>
            <input type="text" class="form-control form-control-sm"
                   name="f[<?= e($col['field']) ?>]"
                   value="<?= e($filters[$col['field']] ?? '') ?>"
                   placeholder="搜索…">
          </div>
          <?php endforeach; ?>
        </div>
        <div class="d-flex gap-2 mt-3">
          <button type="submit" class="btn btn-primary btn-sm px-3">
            <i class="bi bi-search me-1"></i>搜索
          </button>
          <a href="?c=product&a=index" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-circle me-1"></i>清除
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Data table ───────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-3">#</th>
          <th>图片</th>
          <th>分类</th>
          <?php foreach ($listCols as $col): ?>
            <?php
              $isSortable = !empty($col['is_primary']);
            ?>
            <th>
              <?php if ($isSortable): ?>
                <a href="<?= $getSortUrl('primary_value') ?>" class="text-decoration-none text-dark d-flex align-items-center">
                  <?= e($col['label']) ?> <?= $getSortIcon('primary_value') ?>
                </a>
              <?php else: ?>
                <?= e($col['label']) ?>
              <?php endif; ?>
            </th>
          <?php endforeach; ?>
          <th>
            <a href="<?= $getSortUrl('updated_at') ?>" class="text-decoration-none text-dark d-flex align-items-center">
              更新时间 <?= $getSortIcon('updated_at') ?>
            </a>
          </th>
          <th class="text-end pe-3">操作</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr>
          <td colspan="<?= count($listCols) + 4 ?>" class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>暂无数据
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($rows as $row): ?>
        <?php
          $gallery = Product::parseGallery($row['gallery'] ?? null);
          $thumbUrl = '';
          $galleryCount = count($gallery);
          if ($galleryCount > 0) {
              $thumbUrl = $imgBase . '/' . ltrim($gallery[0], '/');
          }
          $galleryUrls = array_map(fn($p) => $imgBase . '/' . ltrim($p, '/'), $gallery);
        ?>
        <tr>
          <td class="ps-3 text-muted small"><?= e($row['id']) ?></td>
          <td>
            <?php if ($thumbUrl !== ''): ?>
              <div class="position-relative d-inline-block">
                <img src="<?= e($thumbUrl) ?>"
                     alt="<?= e($row['primary_value'] ?? '') ?>"
                     class="product-thumb rounded border lightbox-trigger"
                     style="width:48px;height:48px;object-fit:cover;cursor:zoom-in;"
                     data-gallery='<?= e(json_encode($galleryUrls, JSON_UNESCAPED_SLASHES)) ?>'
                     data-gallery-index="0"
                     data-caption="<?= e($row['primary_value'] ?? '') ?>"
                     title="点击查看大图">
                <?php if ($galleryCount > 1): ?>
                  <span class="gallery-count-badge"><?= $galleryCount ?></span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <label class="quick-upload-trigger d-inline-flex align-items-center justify-content-center rounded border bg-light"
                     style="width:48px;height:48px;cursor:pointer;margin:0;"
                     title="点击上传图片"
                     data-id="<?= (int)$row['id'] ?>"
                     data-caption="<?= e($row['primary_value'] ?? '') ?>">
                <i class="bi bi-image opacity-50"></i>
                <input type="file" accept="image/*" multiple class="d-none quick-upload-input">
              </label>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($row['category_name'])): ?>
              <span class="badge bg-info text-dark"><?= e($row['category_name']) ?></span>
            <?php else: ?>
              <span class="text-muted small">无</span>
            <?php endif; ?>
          </td>
          <?php foreach ($listCols as $col): ?>
          <td><?php
            $val = $row[$col['field']] ?? '';
            if ($val !== '' && mb_strlen((string)$val) > 80) {
                echo '<span class="long-text-cell" title="' . e($val) . '">' . e($val) . '</span>';
            } else {
                echo e($val);
            }
          ?></td>
          <?php endforeach; ?>
          <td class="text-muted small"><?= date('Y-m-d H:i', strtotime($row['updated_at'])) ?></td>
          <td class="text-end pe-3 text-nowrap">
            <a href="?c=product&a=show&id=<?= $row['id'] ?>" class="btn btn-xs btn-outline-secondary" title="查看">
              <i class="bi bi-eye"></i>
            </a>
            <a href="?c=product&a=edit&id=<?= $row['id'] ?>" class="btn btn-xs btn-outline-primary ms-1" title="编辑">
              <i class="bi bi-pencil"></i>
            </a>
            <form method="POST" action="?c=product&a=delete" class="d-inline ms-1">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $row['id'] ?>">
              <button type="submit" class="btn btn-xs btn-outline-danger"
                      data-confirm="确定要删除「<?= e($row['primary_value'] ?? '') ?>」吗？" title="删除">
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

  <!-- ── Pagination ─────────────────────────────────────────────── -->
  <?php if ($totalPages > 1): ?>
  <div class="card-footer bg-white border-top-0 d-flex align-items-center justify-content-between py-2 flex-wrap gap-2">
    <small class="text-muted">
      共 <?= number_format($total) ?> 条，第 <?= $page ?>/<?= $totalPages ?> 页
    </small>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <?php
        $baseParams = ['c' => 'product', 'a' => 'index'];
        if (!empty($filters)) $baseParams['f'] = $filters;
        if (!empty($q)) $baseParams['q'] = $q;
        $baseParams['sort'] = $sort;
        $baseParams['order'] = $order;
        $prevDisabled = $page <= 1;
        $nextDisabled = $page >= $totalPages;
        ?>
        <li class="page-item <?= $prevDisabled ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($baseParams, ['page' => $page - 1])) ?>">‹</a>
        </li>
        <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        if ($start > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif;
        for ($p = $start; $p <= $end; $p++):
        ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($baseParams, ['page' => $p])) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <li class="page-item <?= $nextDisabled ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($baseParams, ['page' => $page + 1])) ?>">›</a>
        </li>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>

<!-- ── Lightbox overlay with gallery navigation ─────────────── -->
<div id="imgLightbox" class="lightbox-overlay" aria-hidden="true">
  <div class="lightbox-toolbar">
    <span id="lightboxCounter" class="lightbox-counter"></span>
    <a id="lightboxDownload" class="lightbox-btn" href="#" download title="下载图片">
      <i class="bi bi-download"></i>
    </a>
    <button type="button" class="lightbox-btn lightbox-close" aria-label="关闭" title="关闭">&times;</button>
  </div>
  <button type="button" class="lightbox-nav lightbox-prev" aria-label="上一张" title="上一张">
    <i class="bi bi-chevron-left"></i>
  </button>
  <div class="lightbox-body">
    <img id="lightboxImg" class="lightbox-img" src="" alt="">
    <div id="lightboxCaption" class="lightbox-caption"></div>
  </div>
  <button type="button" class="lightbox-nav lightbox-next" aria-label="下一张" title="下一张">
    <i class="bi bi-chevron-right"></i>
  </button>
</div>

<script>
(function(){
  var overlay  = document.getElementById('imgLightbox');
  var lbImg    = document.getElementById('lightboxImg');
  var lbCap    = document.getElementById('lightboxCaption');
  var dlBtn    = document.getElementById('lightboxDownload');
  var counter  = document.getElementById('lightboxCounter');
  var closeBtn = overlay.querySelector('.lightbox-close');
  var prevBtn  = overlay.querySelector('.lightbox-prev');
  var nextBtn  = overlay.querySelector('.lightbox-next');

  var currentGallery = [];
  var currentIndex   = 0;
  var currentCaption = '';

  function showImage(index) {
    if (index < 0 || index >= currentGallery.length) return;
    currentIndex = index;
    var url = currentGallery[index];
    lbImg.src = url;
    dlBtn.href = url;
    dlBtn.download = url.split('/').pop() || 'product-image';
    lbCap.textContent = currentCaption;
    if (currentGallery.length > 1) {
      counter.textContent = (index + 1) + ' / ' + currentGallery.length;
      prevBtn.style.display = index > 0 ? '' : 'none';
      nextBtn.style.display = index < currentGallery.length - 1 ? '' : 'none';
    } else {
      counter.textContent = '';
      prevBtn.style.display = 'none';
      nextBtn.style.display = 'none';
    }
  }

  function openLightbox(gallery, index, caption) {
    currentGallery = gallery;
    currentCaption = caption;
    showImage(index || 0);
    overlay.classList.add('active');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    overlay.classList.remove('active');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    setTimeout(function(){ lbImg.src = ''; }, 300);
  }

  document.querySelectorAll('.lightbox-trigger').forEach(function(thumb) {
    thumb.addEventListener('click', function() {
      var galleryData = thumb.dataset.gallery;
      var gallery = galleryData ? JSON.parse(galleryData) : [thumb.dataset.full || thumb.src];
      var index = parseInt(thumb.dataset.galleryIndex || '0', 10);
      openLightbox(gallery, index, thumb.dataset.caption || '');
    });
  });

  prevBtn.addEventListener('click', function() { showImage(currentIndex - 1); });
  nextBtn.addEventListener('click', function() { showImage(currentIndex + 1); });

  dlBtn.addEventListener('click', function(e) {
    e.preventDefault();
    var url = dlBtn.href;
    var filename = dlBtn.download || 'product-image';
    fetch(url).then(function(r){ return r.blob(); }).then(function(blob){
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(a.href);
    });
  });

  closeBtn.addEventListener('click', closeLightbox);
  overlay.addEventListener('click', function(e) {
    if (e.target === overlay || e.target.classList.contains('lightbox-body')) closeLightbox();
  });
  document.addEventListener('keydown', function(e) {
    if (!overlay.classList.contains('active')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft')  showImage(currentIndex - 1);
    if (e.key === 'ArrowRight') showImage(currentIndex + 1);
  });

  window._openLightbox = openLightbox;
})();
</script>

<script>
/* ── Quick upload from product list ─────────────────────────── */
(function(){
  document.querySelectorAll('.quick-upload-input').forEach(function(input){
    input.addEventListener('change', function(){
      if (!input.files || !input.files.length) return;

      var label   = input.closest('.quick-upload-trigger');
      var id      = label.dataset.id;
      var caption = label.dataset.caption || '';
      var td      = label.parentElement;

      var icon = label.querySelector('i');
      icon.className = 'bi bi-arrow-repeat spin-icon';
      icon.style.opacity = '1';
      label.style.pointerEvents = 'none';

      var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
      var fd = new FormData();
      fd.append('id', id);
      fd.append('_token', csrfToken);
      for (var i = 0; i < input.files.length; i++) {
        fd.append('images[]', input.files[i]);
      }

      fetch('?c=product&a=uploadImage', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (data.ok) {
            var wrap = document.createElement('div');
            wrap.className = 'position-relative d-inline-block';
            var img = document.createElement('img');
            img.src = data.url;
            img.alt = caption;
            img.className = 'product-thumb rounded border lightbox-trigger';
            img.style.cssText = 'width:48px;height:48px;object-fit:cover;cursor:zoom-in;';
            img.dataset.gallery = JSON.stringify(data.gallery || [data.url]);
            img.dataset.galleryIndex = '0';
            img.dataset.caption = caption;
            img.title = '点击查看大图';
            wrap.appendChild(img);
            if (data.gallery && data.gallery.length > 1) {
              var badge = document.createElement('span');
              badge.className = 'gallery-count-badge';
              badge.textContent = data.gallery.length;
              wrap.appendChild(badge);
            }
            td.replaceChild(wrap, label);
            img.addEventListener('click', function(){
              var g = JSON.parse(img.dataset.gallery);
              window._openLightbox(g, 0, caption);
            });
          } else {
            alert(data.error || '上传失败');
            icon.className = 'bi bi-image opacity-50';
            label.style.pointerEvents = '';
          }
        })
        .catch(function(){
          alert('上传请求失败，请重试');
          icon.className = 'bi bi-image opacity-50';
          label.style.pointerEvents = '';
        });
    });
  });
})();
</script>
