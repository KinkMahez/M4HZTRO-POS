<?php
require_once 'config/database.php';


// ── FILTER PARAMS ──
$periode = $_GET['periode'] ?? 'week';
$dari    = $_GET['dari']    ?? '';
$sampai  = $_GET['sampai']  ?? '';

switch ($periode) {
  case 'today':
    $dari   = date('Y-m-d');
    $sampai = date('Y-m-d');
    break;
  case 'week':
    $dari   = date('Y-m-d', strtotime('monday this week'));
    $sampai = date('Y-m-d', strtotime('sunday this week'));
    break;
  case 'month':
    $dari   = date('Y-m-01');
    $sampai = date('Y-m-t');
    break;
  case 'custom':
    $dari   = $dari   ?: date('Y-m-01');
    $sampai = $sampai ?: date('Y-m-d');
    break;
}

$dariLabel   = date('d/m/Y', strtotime($dari));
$sampaiLabel = date('d/m/Y', strtotime($sampai));

// ── PERIODE SEBELUMNYA (untuk perbandingan %) ──
$diffDays   = (strtotime($sampai) - strtotime($dari)) / 86400 + 1;
$prevDari   = date('Y-m-d', strtotime($dari . " -$diffDays days"));
$prevSampai = date('Y-m-d', strtotime($dari . " -1 day"));

// ── QUERY 1: Stats periode ini ──
$stmtStats = $conn->prepare("
    SELECT 
        COALESCE(SUM(t.total), 0)   AS total_pendapatan,
        COUNT(t.id_transaction)     AS total_transaksi,
        COALESCE(AVG(t.total), 0)   AS avg_transaksi
    FROM transactions t
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
");
$stmtStats->bind_param("ss", $dari, $sampai);
$stmtStats->execute();
$stats = $stmtStats->get_result()->fetch_assoc();

// ── QUERY 2: Total produk terjual periode ini ──
$stmtQty = $conn->prepare("
    SELECT COALESCE(SUM(td.qty), 0) AS total_produk
    FROM transaction_detail td
    JOIN transactions t ON t.id_transaction = td.id_transaction
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
");
$stmtQty->bind_param("ss", $dari, $sampai);
$stmtQty->execute();
$stats['total_produk'] = $stmtQty->get_result()->fetch_assoc()['total_produk'];

// ── QUERY 3: Stats periode sebelumnya ──
$stmtPrev = $conn->prepare("
    SELECT 
        COALESCE(SUM(t.total), 0)   AS total_pendapatan,
        COUNT(t.id_transaction)     AS total_transaksi,
        COALESCE(AVG(t.total), 0)   AS avg_transaksi
    FROM transactions t
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
");
$stmtPrev->bind_param("ss", $prevDari, $prevSampai);
$stmtPrev->execute();
$prev = $stmtPrev->get_result()->fetch_assoc();

// ── QUERY 4: Total produk terjual periode sebelumnya ──
$stmtPrevQty = $conn->prepare("
    SELECT COALESCE(SUM(td.qty), 0) AS total_produk
    FROM transaction_detail td
    JOIN transactions t ON t.id_transaction = td.id_transaction
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
");
$stmtPrevQty->bind_param("ss", $prevDari, $prevSampai);
$stmtPrevQty->execute();
$prev['total_produk'] = $stmtPrevQty->get_result()->fetch_assoc()['total_produk'];

// ── PERSENTASE PERUBAHAN ──
function pctChange($now, $prev)
{
  $prev = floatval($prev);
  $now  = floatval($now);
  if ($prev == 0) return ['val' => 0, 'dir' => 'neutral'];
  $pct = round((($now - $prev) / $prev) * 100);
  return ['val' => abs($pct), 'dir' => $pct >= 0 ? 'up' : 'down'];
}

$chgPendapatan = pctChange($stats['total_pendapatan'], $prev['total_pendapatan']);
$chgTrx        = pctChange($stats['total_transaksi'],  $prev['total_transaksi']);
$chgProduk     = pctChange($stats['total_produk'],     $prev['total_produk']);
$chgAvg        = pctChange($stats['avg_transaksi'],    $prev['avg_transaksi']);

// ── QUERY 5: Bar chart pendapatan harian ──
$stmtBar = $conn->prepare("
    SELECT DATE(tanggal) AS tgl, COALESCE(SUM(total), 0) AS total
    FROM transactions
    WHERE DATE(tanggal) BETWEEN ? AND ?
    GROUP BY DATE(tanggal)
    ORDER BY tgl ASC
");
$stmtBar->bind_param("ss", $dari, $sampai);
$stmtBar->execute();
$barRows = $stmtBar->get_result()->fetch_all(MYSQLI_ASSOC);

// Generate array per hari (isi 0 kalau tidak ada transaksi)
$barData = [];
$cur = strtotime($dari);
$end = strtotime($sampai);
while ($cur <= $end) {
  $d     = date('Y-m-d', $cur);
  $found = array_values(array_filter($barRows, fn($r) => $r['tgl'] === $d));
  $barData[] = [
    'tgl'   => $d,
    'label' => date('d/m', $cur),
    'total' => $found ? $found[0]['total'] : 0
  ];
  $cur = strtotime('+1 day', $cur);
}

// ── QUERY 6: Donut per kategori ──
$stmtDonut = $conn->prepare("
    SELECT c.nama_category, COALESCE(SUM(td.qty), 0) AS total_qty
    FROM transaction_detail td
    JOIN products p ON p.id_product = td.id_product
    JOIN categories c ON c.id_category = p.id_category
    JOIN transactions t ON t.id_transaction = td.id_transaction
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
    GROUP BY c.id_category, c.nama_category
    ORDER BY total_qty DESC
");
$stmtDonut->bind_param("ss", $dari, $sampai);
$stmtDonut->execute();
$donutRows  = $stmtDonut->get_result()->fetch_all(MYSQLI_ASSOC);
$donutTotal = array_sum(array_column($donutRows, 'total_qty'));

// ── QUERY 7: Produk terlaris (dengan pagination) ──
$page    = max(1, intval($_GET['pg'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$stmtTop = $conn->prepare("
    SELECT p.nama_product, c.nama_category,
           COALESCE(SUM(td.qty), 0)      AS terjual,
           COALESCE(SUM(td.subtotal), 0) AS pendapatan,
           p.stok
    FROM transaction_detail td
    JOIN products p ON p.id_product = td.id_product
    JOIN categories c ON c.id_category = p.id_category
    JOIN transactions t ON t.id_transaction = td.id_transaction
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
    GROUP BY p.id_product, p.nama_product, c.nama_category, p.stok
    ORDER BY terjual DESC
    LIMIT ? OFFSET ?
");
$stmtTop->bind_param("ssii", $dari, $sampai, $perPage, $offset);
$stmtTop->execute();
$prodRows = $stmtTop->get_result()->fetch_all(MYSQLI_ASSOC);

// ALL PRODUCTS (tanpa pagination, untuk export)
// Query khusus print — semua produk tanpa pagination
$stmtPrint = $conn->prepare("
    SELECT p.nama_product, c.nama_category,
           COALESCE(SUM(td.qty), 0)      AS terjual,
           COALESCE(SUM(td.subtotal), 0) AS pendapatan,
           p.stok
    FROM transaction_detail td
    JOIN products p ON p.id_product = td.id_product
    JOIN categories c ON c.id_category = p.id_category
    JOIN transactions t ON t.id_transaction = td.id_transaction
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
    GROUP BY p.id_product, p.nama_product, c.nama_category, p.stok
    ORDER BY terjual DESC
");
$stmtPrint->bind_param("ss", $dari, $sampai);
$stmtPrint->execute();
$prodRowsAll = $stmtPrint->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPrint->close();

// ── QUERY 8: Total produk untuk pagination ──
$stmtCount = $conn->prepare("
    SELECT COUNT(DISTINCT p.id_product) AS total
    FROM transaction_detail td
    JOIN products p ON p.id_product = td.id_product
    JOIN transactions t ON t.id_transaction = td.id_transaction
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
");
$stmtCount->bind_param("ss", $dari, $sampai);
$stmtCount->execute();
$totalProd  = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalProd / $perPage);

// ── HELPERS ──
function formatRp($n)
{
  if ($n >= 1000000) return 'Rp ' . number_format($n / 1000000, 2, '.', '') . 'jt';
  if ($n >= 1000)    return 'Rp ' . number_format($n / 1000, 0) . 'rb';
  return 'Rp ' . number_format($n, 0);
}
function formatFull($n)
{
  return 'Rp ' . number_format($n, 0, ',', '.');
}
function arrowSvg($dir)
{
  $pts = $dir === 'up' ? '18 15 12 9 6 15' : '6 9 12 15 18 9';
  $col = $dir === 'up' ? '#4ade80' : ($dir === 'down' ? '#e02020' : '#444');
  return "<svg width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='$col' stroke-width='2.5'><polyline points='$pts'/></svg>";
}

$donutColors = ['#d72437', '#4a9eff', '#4ade80', '#cc66ff', '#ffaa00'];
?>
<link rel="stylesheet" href="asset/css/report.css?v=<?= time(); ?>">

<div class="topbar">
  <div>
    <div class="page-sub"><?= $dariLabel ?> — <?= $sampaiLabel ?></div>
  </div>
  <div class="topbar-right no-print">
    <button class="btn-outline" onclick="exportExcel()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <polyline points="14 2 14 8 20 8" />
      </svg>
      Export Excel
    </button>
    <button class="btn-red" onclick="exportReport()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="6 9 6 2 18 2 18 9" />
        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
        <rect x="6" y="14" width="12" height="8" />
      </svg>
      Print
    </button>
  </div>
</div>

<!-- Filter -->
<form method="GET" class="no-print">
  <input type="hidden" name="page" value="sales-report">
  <div class="filter-bar">
    <div class="filter-group">
      <span class="filter-label">PERIODE</span>
      <select class="fs" name="periode" onchange="this.form.submit()">
        <option value="today" <?= $periode === 'today'  ? 'selected' : '' ?>>Hari Ini</option>
        <option value="week" <?= $periode === 'week'   ? 'selected' : '' ?>>Minggu Ini</option>
        <option value="month" <?= $periode === 'month'  ? 'selected' : '' ?>>Bulan Ini</option>
        <option value="custom" <?= $periode === 'custom' ? 'selected' : '' ?>>Custom</option>
      </select>
    </div>
    <?php if ($periode === 'custom'): ?>
      <div class="filter-divider"></div>
      <div class="filter-group">
        <span class="filter-label">DARI</span>
        <input class="fs" type="date" name="dari" value="<?= $dari ?>">
      </div>
      <div class="filter-group">
        <span class="filter-label">SAMPAI</span>
        <input class="fs" type="date" name="sampai" value="<?= $sampai ?>">
      </div>
    <?php endif; ?>
    <div class="filter-right">
      <a href="index.php?page=sales-report" class="btn-outline">Reset</a>
      <button type="submit" class="btn-red">Terapkan</button>
    </div>
  </div>
</form>

<!-- Stats -->
<div class="stats">
  <?php
  $statItems = [
    ['label' => 'TOTAL PENDAPATAN', 'val' => formatRp($stats['total_pendapatan']), 'class' => 'red', 'chg' => $chgPendapatan],
    ['label' => 'TOTAL TRANSAKSI', 'val' => number_format($stats['total_transaksi']), 'class' => '', 'chg' => $chgTrx],
    ['label' => 'PRODUK TERJUAL', 'val' => number_format($stats['total_produk']), 'class' => '', 'chg' => $chgProduk],
    ['label' => 'RATA-RATA / TRANSAKSI', 'val' => formatRp($stats['avg_transaksi']), 'class' => 'green', 'chg' => $chgAvg],
  ];
  foreach ($statItems as $s): ?>
    <div class="stat">
      <div class="stat-label"><?= $s['label'] ?></div>
      <div class="stat-val <?= $s['class'] ?>"><?= $s['val'] ?></div>
      <div class="stat-sub <?= $s['chg']['dir'] ?>">
        <?= arrowSvg($s['chg']['dir']) ?>
        <?= $s['chg']['val'] ?>% vs periode lalu
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Chart + Donut -->
<div class="row2">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Pendapatan Harian</div>
      <span class="badge badge-gray"><?= $dariLabel ?> — <?= $sampaiLabel ?></span>
    </div>
    <?php
    $maxBar = max(array_column($barData, 'total') ?: [1]);
    ?>
    <div class="chart-wrap">
      <?php foreach ($barData as $i => $b):
        $pct = $maxBar > 0 ? round(($b['total'] / $maxBar) * 100) : 0;
        $isToday = $b['tgl'] === date('Y-m-d');
        $color = $isToday ? '#d72437' : '#2a0a0a';
      ?>
        <div class="bar-col">
          <div style="font-size:9px;color:<?= $isToday ? '#d72437' : '#666565' ?>;margin-bottom:2px;text-align:center">
            <?= $b['total'] > 0 ? formatRp($b['total']) : '' ?>
          </div>
          <div class="bar-fill" style="height:<?= max($pct, 3) ?>%;background:<?= $color ?>"></div>
          <div class="bar-lbl"><?= $b['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">Per Kategori</div>
      <span class="badge badge-gray"><?= $dariLabel ?></span>
    </div>
    <?php if (empty($donutRows)): ?>
      <div class="empty">Belum ada data.</div>
    <?php else:
      $offset_d = 25;
      $segments = [];
      foreach ($donutRows as $i => $d) {
        $pct = $donutTotal > 0 ? round(($d['total_qty'] / $donutTotal) * 100) : 0;
        $segments[] = ['pct' => $pct, 'color' => $donutColors[$i % count($donutColors)], 'nama' => $d['nama_category'], 'offset' => $offset_d];
        $offset_d -= $pct;
      }
    ?>
      <div class="donut-wrap">
        <svg class="donut-svg" viewBox="0 0 36 36">
          <circle cx="18" cy="18" r="15.9" fill="none" stroke="#1e1e1e" stroke-width="3.8" />
          <?php foreach ($segments as $seg): ?>
            <circle cx="18" cy="18" r="15.9" fill="none"
              stroke="<?= $seg['color'] ?>" stroke-width="3.8"
              stroke-dasharray="<?= $seg['pct'] ?> <?= 100 - $seg['pct'] ?>"
              stroke-dashoffset="<?= $seg['offset'] ?>"
              transform="rotate(-90 18 18)" />
          <?php endforeach; ?>
          <text x="18" y="19.5" text-anchor="middle" font-size="5" fill="#fff" font-weight="600"><?= $stats['total_produk'] ?></text>
          <text x="18" y="23.5" text-anchor="middle" font-size="3" fill="#555">terjual</text>
        </svg>
        <div style="width:100%">
          <?php foreach ($donutRows as $i => $d):
            $pct = $donutTotal > 0 ? round(($d['total_qty'] / $donutTotal) * 100) : 0;
          ?>
            <div class="legend-item">
              <div class="legend-left">
                <div class="legend-dot" style="background:<?= $donutColors[$i % count($donutColors)] ?>"></div>
                <?= htmlspecialchars($d['nama_category']) ?>
              </div>
              <div class="legend-right"><?= $pct ?>%</div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Produk Terlaris -->
<div class="card" style="margin-bottom: 1rem;">
  <div class="card-header">
    <div class="card-title">Produk Terlaris</div>
    <span class="badge badge-gray"><?= $totalProd ?> produk</span>
  </div>

  <?php if (empty($prodRows)): ?>
    <div class="empty">Belum ada data transaksi pada periode ini.</div>
  <?php else: ?>
    <table class="rank-product">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>PRODUK</th>
          <th style="width:100px">KATEGORI</th>
          <th style="width:70px;text-align:center">TERJUAL</th>
          <th style="width:140px">PENDAPATAN</th>
          <th style="width:60px;text-align:center">STOK</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($prodRows as $i => $p):
          $rank      = $offset + $i + 1;
          $rankClass = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
          $stokBg    = $p['stok'] < 10 ? 'rgba(225, 29, 72, 0.2)' : 'rgba(16, 185, 129, 0.2)';
          $stokColor = $p['stok'] < 10 ? '#ff4d6d' : '#34d399';
          $stokBorder = $p['stok'] < 10 ? 'rgba(225, 29, 72, 0.4)' : 'rgba(16, 185, 129, 0.4)';
        ?>
          <tr>
            <td><span class="rank-badge <?= $rankClass ?>"><?= $rank ?></span></td>
            <td class="td-white"><?= htmlspecialchars($p['nama_product']) ?></td>
            <td><span style="font-size:10px;padding:2px 8px;border-radius:20px;background:#2e2e2e;color:#ff"><?= htmlspecialchars($p['nama_category']) ?></span></td>
            <td style="text-align:center;font-weight:500"><?= $p['terjual'] ?></td>
            <td class="td-green"><?= formatFull($p['pendapatan']) ?></td>
            <td style="text-align:center"><span class="stok-pill" style="background:<?= $stokBg ?>;color:<?= $stokColor ?>;border:1px solid <?= $stokBorder ?>"> <?= $p['stok'] ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
      <span>Menampilkan <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalProd) ?> dari <?= $totalProd ?> produk</span>
      <div class="page-btns">
        <?php
        $qBase = http_build_query(array_merge($_GET, ['pg' => null]));
        $prev  = $page > 1 ? $page - 1 : null;
        $next  = $page < $totalPages ? $page + 1 : null;
        ?>
        <a href="?<?= $qBase ?>&pg=<?= $prev ?>" class="pg-btn <?= !$prev ? 'disabled' : '' ?>">←</a>
        <?php for ($p2 = max(1, $page - 2); $p2 <= min($totalPages, $page + 2); $p2++): ?>
          <a href="?<?= $qBase ?>&pg=<?= $p2 ?>" class="pg-btn <?= $p2 === $page ? 'active' : '' ?>"><?= $p2 ?></a>
        <?php endfor; ?>
        <a href="?<?= $qBase ?>&pg=<?= $next ?>" class="pg-btn <?= !$next ? 'disabled' : '' ?>">→</a>
      </div>
    </div>
  <?php endif; ?>
</div>

</div>
<!-- Konten Laporan -->
<div id="sales-report-container" style="width: 100%; position: absolute; left: -1000px;">
  <div class="report-header">
    <h1>SALES REPORT</h1>
    <p>Periode: <?= $dariLabel ?> - <?= $sampaiLabel ?></p>
  </div>

  <div class="total">
    <h2>Total Income: <?= formatFull($stats['total_pendapatan']) ?></h2>
    <h2>Total Transactions: <?= $stats['total_transaksi'] ?></h2>
    <h2>Total Sold Products: <?= $stats['total_produk'] ?></h2>
  </div>

  <table class="report-table">
    <thead>
      <tr>
        <th>Product</th>
        <th>Category</th>
        <th style="text-align: center;">Sold</th>
        <th>Income</th>
        <th style="text-align: center;">Current Stock</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($prodRowsAll as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['nama_product']) ?></td>
          <td><?= htmlspecialchars($p['nama_category']) ?></td>
          <td style="text-align: center;"><?= $p['terjual'] ?></td>
          <td><?= formatFull($p['pendapatan']) ?></td>
          <td style="text-align: center;"><span class="badge-report"><?= $p['stok'] ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>


<!-- Export Excel (CSV) -->
<script>
  function exportExcel() {
    const rows = <?= json_encode(array_map(fn($p) => [
                    $p['nama_product'],
                    $p['nama_category'],
                    $p['terjual'],
                    $p['pendapatan'],
                    $p['stok']
                  ], $prodRowsAll)) ?>;

    const header = ['Produk', 'Kategori', 'Terjual', 'Pendapatan', 'Stok Saat Ini'];
    const meta = [
      ['Laporan Penjualan'],
      ['Periode', '<?= $dariLabel ?> - <?= $sampaiLabel ?>'],
      ['Total Pendapatan', '<?= formatFull($stats['total_pendapatan']) ?>'],
      ['Total Transaksi', '<?= $stats['total_transaksi'] ?>'],
      ['Total Produk Terjual', '<?= $stats['total_produk'] ?>'],
      [],
      header,
      ...rows
    ];

    const csv = meta.map(r => r.join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
    a.download = 'laporan_penjualan_<?= $dari ?>_<?= $sampai ?>.csv';
    a.click();
  }

  function exportReport() {
    const element = document.getElementById('sales-report-container');

    // Gunakan html2canvas untuk menangkap elemen
    html2canvas(element, {
      scale: 3, // Meningkatkan resolusi gambar (biar tidak pecah)
      backgroundColor: "#ffffff",
      logging: false,
      useCORS: true // Penting jika ada gambar dari domain luar
    }).then(canvas => {
      // Ubah canvas menjadi URL gambar
      const imageData = canvas.toDataURL("image/png");

      // Buat elemen link download sementara
      const link = document.createElement('a');
      link.download = 'Sales-Report.png';
      link.href = imageData;

      // Trigger download
      link.click();

      loading.style.display = 'none';
    }).catch(err => {
      console.error("Gagal membuat gambar:", err);
      loading.innerText = "Terjadi kesalahan saat mengekspor.";
    });
  }
</script>