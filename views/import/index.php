<!-- ── Page header ──────────────────────────────────────────────── -->
<div class="d-flex align-items-center mb-3">
  <h5 class="mb-0 fw-semibold">
    <i class="bi bi-upload text-primary me-1"></i>导入 CSV
  </h5>
</div>

<div class="row g-3">

  <!-- Upload form -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-medium py-2">上传文件</div>
      <div class="card-body">

        <?php if (isset($error)): ?>
        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (isset($imported)): ?>
        <div class="alert alert-success py-2 small">
          <i class="bi bi-check-circle me-1"></i>
          导入完成：<strong><?= (int)$imported ?></strong> 条成功，<strong><?= (int)$skipped ?></strong> 条跳过
        </div>
        <?php if (isset($imageSavedCount) && ((int)$imageSavedCount > 0 || !empty($imageReport))): ?>
        <div class="alert alert-info py-2 small">
          <i class="bi bi-images me-1"></i>
          图片处理：上传 <strong><?= (int)$imageSavedCount ?></strong> 张，成功匹配并关联到产品 <strong><?= (int)($imageMatchCount ?? 0) ?></strong> 个产品的图片库
          <?php if (!empty($imageUnmatched)): ?>
            <div class="text-danger mt-1">
              <i class="bi bi-exclamation-triangle me-1"></i>
              <strong><?= count($imageUnmatched) ?></strong> 个 TQB 编码的图片已上传但未在 CSV 中找到对应产品（已自动清理）：
              <code><?= e(implode(', ', array_slice($imageUnmatched, 0, 30))) ?><?= count($imageUnmatched) > 30 ? ' …' : '' ?></code>
            </div>
          <?php endif; ?>
          <?php if (!empty($imageReport)): ?>
            <details class="mt-1">
              <summary class="text-muted">查看本次上传图片明细（<?= count($imageReport) ?>）</summary>
              <div class="mt-1 small" style="max-height:200px;overflow:auto;">
                <table class="table table-sm mb-0">
                  <thead class="table-light">
                    <tr><th style="width:42%">文件名</th><th>解析得到的 TQB 编码</th><th>结果</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($imageReport as $r): ?>
                    <tr>
                      <td class="text-truncate" style="max-width:240px;" title="<?= e($r['name'] ?? '') ?>"><?= e($r['name'] ?? '') ?></td>
                      <td class="font-monospace text-primary"><?= e($r['key'] ?? '') ?></td>
                      <td>
                        <?php if (!empty($r['error'])): ?>
                          <span class="text-danger">跳过：<?= e($r['error']) ?></span>
                        <?php elseif (!empty($r['matched'])): ?>
                          <span class="text-success"><i class="bi bi-check-circle me-1"></i>已关联到图片库</span>
                        <?php elseif (!empty($r['saved'])): ?>
                          <span class="text-warning">已上传但 CSV 无此 TQB 编码（已清理）</span>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </details>
          <?php endif; ?>
          <?php if (!empty($imageMissing)): ?>
            <details class="mt-1">
              <summary class="text-muted">CSV 中存在但未上传对应图片的 TQB编码（<?= count($imageMissing) ?>）</summary>
              <div class="mt-1 text-muted small" style="max-height:120px;overflow:auto;">
                <?= e(implode(', ', array_slice($imageMissing, 0, 200))) ?>
                <?= count($imageMissing) > 200 ? ' …' : '' ?>
              </div>
            </details>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
        <div class="alert alert-warning py-2 small">
          <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="?c=import&a=upload" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label fw-medium">选择 CSV 文件 <span class="text-danger">*</span></label>
            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
            <div class="form-text">支持最大 <?= ini_get('upload_max_filesize') ?> 的文件</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-medium">文件编码</label>
            <select class="form-select form-select-sm" name="encoding">
              <option value="auto" selected>自动检测（推荐）</option>
              <option value="gbk">GBK / GB2312（Excel 默认保存格式）</option>
              <option value="utf8">UTF-8</option>
            </select>
          </div>

          <div class="mb-3 border-top pt-3">
            <label class="form-label fw-medium">
              <i class="bi bi-images text-primary me-1"></i>产品图片（可选，支持多图）
            </label>
            <input type="file" class="form-control" name="images[]" id="imagesInput"
                   accept="image/*" multiple>
            <div class="form-text small">
              选择图片文件，文件名需以 <strong>TQB编码</strong> 开头。
              同一个 TQB编码 的多张图片（如 <code>TQB0-001(1).jpg</code>、<code>TQB0-001(2).jpg</code>、<code>TQB0-001(3).jpg</code>）
              会自动合并到该产品的<strong>图片库</strong>中。后缀支持 jpg / png / gif / webp。
            </div>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" id="useFolderPicker">
              <label class="form-check-label small text-muted" for="useFolderPicker">
                改为「选择整个文件夹」（Chrome / Edge 支持）
              </label>
            </div>
            <div id="imagesSummary" class="form-text mt-1"></div>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="bi bi-upload me-1"></i>开始导入
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Column mapping reference -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-medium py-2">
        CSV 列映射参考
        <span class="badge bg-secondary ms-1"><?= count($columns) ?> 列</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
          <table class="table table-sm table-striped mb-0 small">
            <thead class="table-light sticky-top">
              <tr><th>CSV 表头</th><th>数据库字段</th></tr>
            </thead>
            <tbody>
              <?php foreach ($columns as $col): ?>
              <tr>
                <td><?= e($col['label']) ?></td>
                <td class="text-muted font-monospace"><?= e($col['field']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Tips -->
<div class="card border-0 border-start border-4 border-info bg-info bg-opacity-10 shadow-sm mt-3">
  <div class="card-body py-2 small">
    <strong>注意事项：</strong>
    <ul class="mb-0 mt-1 ps-3">
      <li>CSV 第一行必须是表头（列名需与上方表格中的"CSV 表头"完全一致）</li>
      <li>如果 <strong>TQB编码</strong> 和 <strong>OEM号码</strong> 均与数据库中完全一致，且未上传同名图片，将自动跳过此行</li>
      <li>如果存在相同的 TQB编码 但 OEM号码 不同，将自动合并新的 OEM号码，并用新数据覆盖该产品的其他字段</li>
      <li>Excel 默认保存的 .csv 通常为 GBK 编码，选"自动检测"或"GBK"即可</li>
      <li><strong>产品图片（多图支持）：</strong>图片文件名需以该产品的
        <strong>TQB编码</strong> 开头（不区分大小写）。同一个 TQB编码 的多张图片会<strong>全部添加到产品的图片库</strong>中，
        例如 <code>TQB0-001.jpg</code>、<code>TQB0-001(1).jpg</code>、<code>TQB0-001(2).jpg</code> 三张图片
        都会被关联到 TQB编码为 <code>TQB0-001</code> 的产品。
        浏览器/Windows 自动追加的 <code>(1)</code>、<code>(2)</code>、<code> - Copy</code>、<code> - 副本</code>
        等后缀会被自动剥离后用于匹配。未匹配到任何 TQB 的图片会被丢弃，并在导入结果中列出。</li>
      <li>若需彻底清空所有数据，请在"产品列表"页点击右上角的"清空数据"按钮</li>
    </ul>
  </div>
</div>

<script>
(function() {
  var picker = document.getElementById('useFolderPicker');
  var input  = document.getElementById('imagesInput');
  var summary = document.getElementById('imagesSummary');
  if (!input) return;

  if (picker) {
    picker.addEventListener('change', function() {
      if (this.checked) {
        input.setAttribute('webkitdirectory', '');
        input.setAttribute('directory', '');
      } else {
        input.removeAttribute('webkitdirectory');
        input.removeAttribute('directory');
      }
      input.value = '';
      if (summary) summary.textContent = '';
    });
  }

  input.addEventListener('change', function() {
    if (!summary) return;
    var files = input.files || [];
    var imgs = 0;
    for (var i = 0; i < files.length; i++) {
      if (/\.(jpe?g|png|gif|webp)$/i.test(files[i].name)) imgs++;
    }
    summary.textContent = '已选择 ' + files.length + ' 个文件（其中 ' + imgs + ' 张为图片）';
  });
})();
</script>

<?php if (!empty($successRows)): ?>
<?php $imgBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'); ?>
<div class="card border-0 shadow-sm mt-3 border-success">
  <div class="card-header bg-success text-white fw-medium py-2 d-flex justify-content-between align-items-center">
    <span><i class="bi bi-check-all me-1"></i>最近成功导入/更新的记录</span>
    <span class="badge bg-light text-success">最多显示 20 条</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped table-hover mb-0 small">
        <thead class="table-light">
          <tr>
            <th>图片</th>
            <th>TQB编码</th>
            <th>OEM号码</th>
            <th>车型</th>
            <th>商品名称</th>
            <th>BCA</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_reverse($successRows) as $sRow): ?>
          <?php
            $sGallery = Product::parseGallery($sRow['gallery'] ?? null);
            $sThumb = !empty($sGallery) ? ($imgBase . '/' . ltrim($sGallery[0], '/')) : '';
          ?>
          <tr>
            <td>
              <?php if ($sThumb !== ''): ?>
                <div class="position-relative d-inline-block">
                  <img src="<?= e($sThumb) ?>"
                       alt="" class="rounded border"
                       style="width:36px;height:36px;object-fit:cover;">
                  <?php if (count($sGallery) > 1): ?>
                    <span class="gallery-count-badge" style="font-size:.6rem;width:16px;height:16px;"><?= count($sGallery) ?></span>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="font-monospace text-primary"><?= e($sRow['tqb_code'] ?? '-') ?></td>
            <td class="text-wrap" style="max-width:300px;"><?= e($sRow['oem_number'] ?? '-') ?></td>
            <td><?= e($sRow['car_model'] ?? '-') ?></td>
            <td><?= e($sRow['name'] ?? '-') ?></td>
            <td><?= e($sRow['bca'] ?? '-') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
