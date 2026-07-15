<?php
$sections = [];
foreach ($columns as $col) {
    $sections[$col['tab']][] = $col;
}

$gallery = Product::parseGallery($product['gallery'] ?? null);
$base    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$galleryUrls = array_map(fn($p) => $base . '/' . ltrim($p, '/'), $gallery);
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center">
    <a href="?c=product&a=index" class="btn btn-sm btn-outline-secondary me-2">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div>
      <h5 class="mb-0 fw-semibold">
        <i class="bi bi-box-seam text-primary me-1"></i><?= e($product['name'] ?? '') ?>
      </h5>
      <small class="text-muted"><?= e($product['tqb_code'] ?? '') ?></small>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="?c=product&a=edit&id=<?= (int)$product['id'] ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-pencil me-1"></i>编辑
    </a>
    <form method="POST" action="?c=product&a=delete" class="d-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
      <button type="submit" class="btn btn-outline-danger btn-sm"
              data-confirm="确定删除「<?= e($product['tqb_code'] ?? $product['name'] ?? '') ?>」吗？">
        <i class="bi bi-trash me-1"></i>删除
      </button>
    </form>
  </div>
</div>

<?php if (!empty($gallery)): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-light py-2 fw-medium small">
    <i class="bi bi-images text-primary me-1"></i>产品图片
    <span class="badge bg-secondary ms-1"><?= count($gallery) ?></span>
  </div>
  <div class="card-body py-3">
    <div class="show-gallery-grid">
      <?php foreach ($galleryUrls as $i => $url): ?>
      <div class="show-gallery-item lightbox-trigger"
           data-gallery='<?= e(json_encode($galleryUrls, JSON_UNESCAPED_SLASHES)) ?>'
           data-gallery-index="<?= $i ?>"
           data-caption="<?= e(($product['tqb_code'] ?? '') . ' – ' . ($product['name'] ?? '')) ?>">
        <img src="<?= e($url) ?>" alt="产品图片 <?= $i + 1 ?>"
             class="img-fluid rounded border"
             style="max-height:200px;object-fit:contain;cursor:zoom-in;">
        <?php if (count($gallery) > 1): ?>
        <span class="show-gallery-index"><?= $i + 1 ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php foreach ($sections as $sectionName => $cols): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-light py-2 fw-medium small">
    <?= e($sectionName) ?>
  </div>
  <div class="card-body py-2">
    <div class="row g-2">
      <?php foreach ($cols as $col): ?>
      <?php $val = $product[$col['field']] ?? ''; ?>
      <div class="col-md-3 col-sm-4 col-6">
        <div class="text-muted small"><?= e($col['label']) ?></div>
        <div class="fw-medium">
          <?php if ($val === ''): ?>
            <span class="text-muted">—</span>
          <?php elseif ($col['field'] === 'stock_status'): ?>
            <?php $cls = match($val) { '有货' => 'success', '缺货' => 'danger', '预订' => 'warning', default => 'secondary' }; ?>
            <span class="badge bg-<?= $cls ?>"><?= e($val) ?></span>
          <?php elseif ($col['field'] === 'warehouse_a'): ?>
            <span class="badge bg-<?= $val === '可出' ? 'success' : 'secondary' ?>"><?= e($val) ?></span>
          <?php else: ?>
            <?= e($val) ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<div class="text-muted small mt-2">
  创建时间: <?= e($product['created_at'] ?? '') ?>
  &nbsp;|&nbsp;
  更新时间: <?= e($product['updated_at'] ?? '') ?>
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

  document.querySelectorAll('.lightbox-trigger').forEach(function(el) {
    el.addEventListener('click', function() {
      var galleryData = el.dataset.gallery;
      var gallery = galleryData ? JSON.parse(galleryData) : [el.querySelector('img').src];
      var index = parseInt(el.dataset.galleryIndex || '0', 10);
      openLightbox(gallery, index, el.dataset.caption || '');
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
})();
</script>
