<?php
$host = '127.0.0.1';
$dbname = 'db_pos';
$user = 'root';
$pass = '';

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  $pdo = null;
}

// Total revenue (this month)
$stmt = $pdo->query("SELECT COALESCE(SUM(total),0) as revenue FROM transactions WHERE MONTH(tanggal)=MONTH(NOW()) AND YEAR(tanggal)=YEAR(NOW())");
$revenue = $stmt->fetchColumn();

// Total transactions (this month)
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions WHERE MONTH(tanggal)=MONTH(NOW()) AND YEAR(tanggal)=YEAR(NOW())");
$totalTrx = $stmt->fetchColumn();

// Total products
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
$totalProducts = $stmt->fetchColumn();

// Low stock products
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stok < 10");
$lowStock = $stmt->fetchColumn();

// Revenue last 7 days (for sparkline)
$stmt = $pdo->query("SELECT DATE(tanggal) as d, SUM(total) as t FROM transactions WHERE tanggal >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(tanggal) ORDER BY d");
$revenueChart = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent transactions
$stmt = $pdo->query("SELECT t.id_transaction, t.tanggal, t.total, t.bayar, t.kembalian, u.username as kasir FROM transactions t LEFT JOIN users u ON t.id_user = u.id ORDER BY t.tanggal DESC LIMIT 5");
$recentTrx = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top products
$stmt = $pdo->query("SELECT p.nama_product, SUM(td.qty) as total_qty, SUM(td.subtotal) as total_rev FROM transaction_detail td JOIN products p ON td.id_product = p.id_product GROUP BY p.id_product ORDER BY total_qty DESC LIMIT 5");
$topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Category distribution
$stmt = $pdo->query("SELECT c.nama_category, COUNT(p.id_product) as jumlah FROM categories c LEFT JOIN products p ON p.id_category = c.id_category GROUP BY c.id_category");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prev month revenue (for comparison)
$stmt = $pdo->query("SELECT COALESCE(SUM(total),0) FROM transactions WHERE MONTH(tanggal)=MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(tanggal)=YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))");
$prevRevenue = $stmt->fetchColumn();
$revenueGrowth = $prevRevenue > 0 ? round((($revenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;

function rupiahShort($n)
{
  if ($n >= 1000000) return 'Rp ' . number_format($n / 1000000, 1, '.', ',') . 'Jt';
  if ($n >= 1000) return 'Rp ' . number_format($n / 1000, 0, '.', ',') . 'Rb';
  return 'Rp ' . number_format($n, 0, '.', ',');
}
function rupiah($n)
{
  return 'Rp ' . number_format($n, 0, '.', ',');
}

// Chart data JSON
$chartDates = json_encode(array_column($revenueChart, 'd'));
$chartValues = json_encode(array_column($revenueChart, 't'));

// Category chart
$catLabels = json_encode(array_column($categories, 'nama_category'));
$catValues = json_encode(array_column($categories, 'jumlah'));

$today = date('l, d F Y');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<script src="asset/js/chart.js"></script>
<link rel="stylesheet" href="asset/css/dashboard.css?v=<?= time(); ?>">
</head>

<div class="aurora-bg">
  <div class="aurora-orb orb1"></div>
  <div class="aurora-orb orb2"></div>
  <div class="aurora-orb orb3"></div>
  <div class="aurora-orb orb4"></div>
</div>

<div class="dashboard">

  <!-- ===== TOP BAR ===== -->
  <div class="topbar fade-in">
    <div class="topbar-left">
      <div class="badge-live"><span class="dot"></span> LIVE</div>
    </div>
    <div class="topbar-right">
      <a href="index.php?page=cashier" class="btn-new-order fade-in delay-2">
        <div class="icon-box">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <span>New Order</span>
      </a>
    </div>
  </div>

  <!-- ===== STAT CARDS ===== -->
  <div class="stats-row">
    <div class="stat-card accent fade-in delay-1" onclick="window.location.href='index.php?page=sales-report&periode=month'" style="cursor:pointer;">
      <div class="stat-label">Pendapatan Bulan Ini</div>
      <div class="stat-value"><?= rupiahShort($revenue) ?></div>
      <div class="stat-meta">
        <span class="<?= $revenueGrowth >= 0 ? 'up' : 'down' ?>">
          <?= $revenueGrowth >= 0 ? '↑' : '↓' ?> <?= abs($revenueGrowth) ?>%
        </span>
        <span class="neutral">vs bulan lalu</span>
      </div>
      <div class="stat-icon"><img src="asset/img/money.png" alt=""></div>
    </div>
    <div class="stat-card fade-in delay-2" onclick="window.location.href='index.php?page=history&periode=month'" style="cursor:pointer;">
      <div class="stat-label">Transaksi</div>
      <div class="stat-value"><?= number_format($totalTrx) ?></div>
      <div class="stat-meta">
        <span class="neutral">Total bulan ini</span>
      </div>
      <div class="stat-icon"><img src="asset/img/transaction.png" alt=""></div>
    </div>
    <div class="stat-card fade-in delay-3" onclick="window.location.href='index.php?page=data-product'" style="cursor:pointer;">
      <div class="stat-label">Total Produk</div>
      <div class="stat-value"><?= number_format($totalProducts) ?></div>
      <div class="stat-meta">
        <span class="neutral">Item terdaftar</span>
      </div>
      <div class="stat-icon"><img src="asset/img/shirt.png" alt=""></div>
    </div>
    <div class="stat-card fade-in delay-4" onclick="window.location.href='index.php?page=restock'" style="cursor:pointer;">
      <div class="stat-label">Stok Menipis</div>
      <div class="stat-value"><?= $lowStock ?></div>
      <div class="stat-meta">
        <span class="<?= $lowStock > 0 ? 'down' : 'up' ?>" style="font-family: 'Jakarta', sans-serif;">
          <?= $lowStock > 0 ? '⚠ Perlu restock' : '✓ Aman' ?>
        </span>
      </div>
      <div class="stat-icon"><img src="asset/img/goods.png" alt=""></div>
    </div>
  </div>

  <!-- ===== MAIN GRID ===== -->
  <div class="main-grid fade-in delay-5">

    <!-- Revenue Chart -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Grafik Pendapatan</div>
          <div class="card-sub">7 hari terakhir</div>
        </div>
        <div class="chip">AREA CHART</div>
      </div>
      <div class="chart-wrap">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>

    <!-- Donut Chart -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Kategori Produk</div>
          <div class="card-sub">Distribusi stok</div>
        </div>
      </div>
      <div class="donut-wrap">
        <canvas id="categoryChart"></canvas>
      </div>
      <div class="legend-list" id="categoryLegend"></div>
    </div>
  </div>

  <!-- ===== BOTTOM GRID ===== -->
  <div class="bottom-grid fade-in delay-6">

    <!-- Recent Transactions -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Transaksi Terbaru</div>
          <div class="card-sub">5 transaksi terakhir</div>
        </div>
        <div class="chip">REAL-TIME</div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Waktu</th>
            <th>Total</th>
            <th>Kasir</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentTrx as $t): ?>
            <tr>
              <td class="trx-id">#<?= $t['id_transaction'] ?></td>
              <td><?= date('H:i', strtotime($t['tanggal'])) ?></td>
              <td class="amount-cell"><?= rupiahShort($t['total']) ?></td>
              <td>
                <?= $t['kasir'] ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Top Products -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Produk Terlaris</div>
          <div class="card-sub">Berdasarkan kuantitas terjual</div>
        </div>
      </div>
      <?php
      // Cek dulu apakah ada data
      if (!empty($topProducts)):
        $maxQty = max(array_column($topProducts, 'total_qty'));
        foreach ($topProducts as $i => $p):
          $pct = $maxQty > 0 ? round($p['total_qty'] / $maxQty * 100) : 0;
      ?>
          <div class="product-item">
            <div class="product-rank <?= $i === 0 ? 'top' : '' ?>"><?= $i + 1 ?></div>
            <div class="product-name"><?= htmlspecialchars($p['nama_product']) ?></div>
            <div class="product-bar-wrap">
              <div class="product-bar" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="product-qty"><?= $p['total_qty'] ?></div>
          </div>
        <?php
        endforeach;
      else:
        ?>
        <div style="text-align:center; padding:24px 0; font-family:var(--mono); font-size:11px; color:var(--muted);">
          Belum ada data produk terjual
        </div>
      <?php endif; ?>
    </div>
    </div><!-- /.dashboard -->
<footer style="text-align: center;">
  <p class="footer-dashboard">© 2026 M4HZTRO. All rights reserved.</p>
</footer>
  

    <script>
      // =============================================
      // CHART.JS DEFAULTS
      // =============================================
      Chart.defaults.color = '#6b6b6b';
      Chart.defaults.font.family = "'DM Mono', monospace";
      Chart.defaults.font.size = 10;

      // =============================================
      // REVENUE AREA CHART
      // =============================================
      const rCtx = document.getElementById('revenueChart').getContext('2d');
      const revenueGrad = rCtx.createLinearGradient(0, 0, 0, 200);
      revenueGrad.addColorStop(0, 'rgba(217,32,42,0.4)');
      revenueGrad.addColorStop(1, 'rgba(217,32,42,0)');

      new Chart(rCtx, {
        type: 'line',
        data: {
          labels: <?= $chartDates ?>,
          datasets: [{
            label: 'Revenue',
            data: <?= $chartValues ?>,
            borderColor: '#d9202a',
            borderWidth: 2,
            backgroundColor: revenueGrad,
            pointBackgroundColor: '#d9202a',
            pointBorderColor: '#0a0a0a',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.4,
            fill: true,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: '#181818',
              borderColor: 'rgba(217,32,42,0.3)',
              borderWidth: 1,
              titleColor: '#6b6b6b',
              bodyColor: '#ffffff',
              callbacks: {
                label: ctx => ' Rp ' + ctx.parsed.y.toLocaleString('id-ID')
              }
            }
          },
          scales: {
            x: {
              grid: {
                color: 'rgba(255,255,255,0.04)'
              },
              ticks: {
                maxRotation: 0,
                font: {
                  size: 9
                }
              }
            },
            y: {
              grid: {
                color: 'rgba(255,255,255,0.04)'
              },
              ticks: {
                callback: v => v >= 1000000 ? (v / 1000000).toFixed(1) + 'Jt' : (v / 1000) + 'Rb',
                font: {
                  size: 9
                }
              }
            }
          }
        }
      });

      // =============================================
      // DONUT CHART
      // =============================================
      const catLabels = <?= $catLabels ?>;
      const catVals = <?= $catValues ?>;
      const catColors = ['#d9202a', '#ff3b46', '#8b1111', '#660f0f', '#ff6b6b', 
        '#d72437', '#7a0909', '#ff1c1c', '#5c0808', '#470000'
      ];

      const dCtx = document.getElementById('categoryChart').getContext('2d');
      new Chart(dCtx, {
        type: 'doughnut',
        data: {
          labels: catLabels,
          datasets: [{
            data: catVals,
            backgroundColor: catColors,
            borderColor: '#111111',
            borderWidth: 3,
            hoverBorderWidth: 0,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '72%',
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: '#181818',
              borderColor: 'rgba(217,32,42,0.3)',
              borderWidth: 1,
              titleColor: '#6b6b6b',
              bodyColor: '#ffffff',
            }
          }
        }
      });

      // Build legend
      const legContainer = document.getElementById('categoryLegend');
      const total = catVals.reduce((a, b) => a + b, 0);
      catLabels.forEach((label, i) => {
        const pct = total > 0 ? Math.round(catVals[i] / total * 100) : 0;
        legContainer.innerHTML += `
    <div class="legend-item">
      <span class="legend-label">
        <span class="legend-dot" style="background:${catColors[i]}"></span>
        ${label}
      </span>
      <span class="legend-val">${catVals[i]} <span style="color:#6b6b6b">(${pct}%)</span></span>
    </div>`;
      });

      // =============================================
      // ANIMATE PRODUCT BARS ON LOAD
      // =============================================
      document.querySelectorAll('.product-bar').forEach(bar => {
        const w = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => bar.style.width = w, 400);
      });
    </script>