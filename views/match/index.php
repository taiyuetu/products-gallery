<?php
// Columns shown in the results preview table (list=true subset)
$listCols = array_filter($columns ?? [], fn($c) => $c['list'] ?? false);
?>

<!-- ── Page header ──────────────────────────────────────────────── -->
<div class="d-flex align-items-center mb-3">
  <h5 class="mb-0 fw-semibold">
    <i class="bi bi-search text-success me-1"></i>OEM 匹配查询
  </h5>
  <?php if (!empty($matchedRows)): ?>
  <span class="badge bg-success ms-2"><?= count($matchedRows) ?> 条匹配</span>
  <?php endif; ?>
</div>

<div class="row g-3">

  <!-- ── Upload form ─────────────────────────────────────────────── -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-medium py-2">
        <i class="bi bi-upload me-1 text-muted"></i>上传匹配 CSV
      </div>
      <div class="card-body">

        <?php if (isset($error)): ?>
        <div class="alert alert-danger py-2 small">
          <i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-warning py-2 small">
          <?php foreach ($errors as $err): ?>
          <div><i class="bi bi-exclamation-circle me-1"></i><?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($matchedRows) && empty($errors)): ?>
        <div class="alert alert-<?= count($matchedRows) > 0 ? 'success' : 'info' ?> py-2 small">
          <i class="bi bi-<?= count($matchedRows) > 0 ? 'check-circle' : 'info-circle' ?> me-1"></i>
          共查询 <strong><?= count($oemList ?? []) ?></strong> 个 OEM 值，
          匹配到 <strong><?= count($matchedRows) ?></strong> 条产品记录
        </div>
        <?php endif; ?>

        <form method="POST" action="?c=match&a=upload" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label fw-medium">
              选择匹配 CSV 文件 <span class="text-danger">*</span>
            </label>
            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
            <div class="form-text">
              CSV 文件须包含名为 <code>oem</code> 的列（不区分大小写）
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-medium">文件编码</label>
            <select class="form-select form-select-sm" name="encoding">
              <option value="auto" selected>自动检测（推荐）</option>
              <option value="gbk">GBK / GB2312（Excel 默认保存格式）</option>
              <option value="utf8">UTF-8</option>
            </select>
          </div>

          <button type="submit" class="btn btn-success w-100">
            <i class="bi bi-search me-1"></i>开始匹配
          </button>
        </form>
      </div>
    </div>

    <!-- ── Tips card ────────────────────────────────────────────── -->
    <div class="card border-0 border-start border-4 border-info bg-info bg-opacity-10 shadow-sm mt-3">
      <div class="card-body py-2 small">
        <strong>使用说明：</strong>
        <ul class="mb-0 mt-1 ps-3">
          <li>上传的 CSV 文件须包含 <strong>oem</strong> 列（列名不区分大小写）</li>
          <li>系统会将每个 OEM 值与数据库 <strong>OEM号码</strong> 字段进行模糊匹配</li>
          <li>数据库中 OEM号码 可能包含多个以逗号分隔的值（如：<code>42410-55330, 523340, we1890223</code>），只要上传的 OEM 存在于其中即视为匹配</li>
          <li>匹配完成后可点击 <strong>"下载结果 CSV"</strong> 导出全部匹配产品</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- ── OEM list summary ─────────────────────────────────────────── -->
  <?php if (!empty($oemList)): ?>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-medium py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-check me-1 text-muted"></i>已提取的 OEM 值</span>
        <span class="badge bg-secondary"><?= count($oemList) ?> 个</span>
      </div>
      <div class="card-body p-0">
        <div class="p-3" style="max-height:260px;overflow-y:auto;">
          <div class="d-flex flex-wrap gap-1">
            <?php foreach ($oemList as $oem): ?>
            <span class="badge bg-light text-dark border font-monospace" style="font-size:.8em;"><?= e($oem) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ── Results table ───────────────────────────────────────────────── -->
<?php if (isset($matchedRows)): ?>
<div class="card border-0 shadow-sm mt-3 <?= count($matchedRows) > 0 ? 'border-top border-3 border-success' : '' ?>">
  <div class="card-header bg-white fw-medium py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>
      <i class="bi bi-table me-1 text-success"></i>匹配结果
      <span class="badge bg-success ms-1"><?= count($matchedRows) ?> 条</span>
    </span>

    <?php if (count($matchedRows) > 0): ?>
    <form method="POST" action="?c=match&a=download" class="d-inline">
      <?= csrf_field() ?>
      <?php foreach (($oemList ?? []) as $oem): ?>
      <input type="hidden" name="oem[]" value="<?= e($oem) ?>">
      <?php endforeach; ?>
      <button type="submit" class="btn btn-sm btn-outline-success">
        <i class="bi bi-download me-1"></i>下载结果 CSV
      </button>
    </form>
    <?php endif; ?>
  </div>

  <?php if (count($matchedRows) === 0): ?>
  <div class="card-body text-center text-muted py-5">
    <i class="bi bi-search fs-1 d-block mb-2 opacity-25"></i>
    未在数据库中找到与所上传 OEM 匹配的产品，请确认 OEM 值是否正确
  </div>
  <?php else: ?>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped table-hover mb-0 small align-middle">
        <thead class="table-light sticky-top">
          <tr>
            <th class="text-muted" style="width:50px;">#</th>
            <?php foreach ($listCols as $col): ?>
            <th><?= e($col['label']) ?></th>
            <?php endforeach; ?>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($matchedRows as $i => $row): ?>
          <tr>
            <td class="text-muted"><?= $i + 1 ?></td>
            <?php foreach ($listCols as $col): ?>
            <td class="<?= $col['field'] === 'oem_number' ? 'text-wrap' : '' ?>"
                <?= $col['field'] === 'oem_number' ? 'style="max-width:280px;"' : '' ?>>
              <?php if ($col['field'] === 'tqb_code'): ?>
                <span class="font-monospace text-primary"><?= e($row[$col['field']] ?? '') ?></span>
              <?php elseif ($col['field'] === 'oem_number'): ?>
                <?php
                // Highlight matched OEM segments
                $oemStr = e($row['oem_number'] ?? '');
                foreach (($oemList ?? []) as $needle) {
                    $safe = e($needle);
                    $oemStr = str_ireplace(
                        $safe,
                        '<mark class="px-0">' . $safe . '</mark>',
                        $oemStr
                    );
                }
                echo $oemStr;
                ?>
              <?php else: ?>
                <?= e($row[$col['field']] ?? '') ?>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td>
              <a href="?c=product&a=show&id=<?= (int)$row['id'] ?>"
                 class="btn btn-xs btn-outline-secondary" style="font-size:.75rem;padding:.1rem .4rem;"
                 title="查看详情" target="_blank">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center small text-muted">
    <span>共 <strong><?= count($matchedRows) ?></strong> 条匹配产品</span>
    <form method="POST" action="?c=match&a=download">
      <?= csrf_field() ?>
      <?php foreach (($oemList ?? []) as $oem): ?>
      <input type="hidden" name="oem[]" value="<?= e($oem) ?>">
      <?php endforeach; ?>
      <button type="submit" class="btn btn-sm btn-success">
        <i class="bi bi-download me-1"></i>下载结果 CSV（<?= count($matchedRows) ?> 条）
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
