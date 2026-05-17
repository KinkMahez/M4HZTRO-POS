<?php
require_once __DIR__ . '/config/database.php';
$conn->query("SET time_zone = '+07:00'");

// ── PERIODE FILTER ──
$periode = $_GET['periode'] ?? 'month';
switch ($periode) {
    case 'today':
        $dari   = date('Y-m-d');
        $sampai = date('Y-m-d');
        break;
    case 'week':
        $dari   = date('Y-m-d', strtotime('monday this week'));
        $sampai = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'year':
        $dari   = date('Y-01-01');
        $sampai = date('Y-12-31');
        break;
    default: // month
        $dari   = date('Y-m-01');
        $sampai = date('Y-m-t');
}

$dariLabel   = date('d M Y', strtotime($dari));
$sampaiLabel = date('d M Y', strtotime($sampai));

// Periode sebelumnya (untuk % perubahan)
$diffDays   = (strtotime($sampai) - strtotime($dari)) / 86400 + 1;
$prevDari   = date('Y-m-d', strtotime("$dari -$diffDays days"));
$prevSampai = date('Y-m-d', strtotime("$dari -1 day"));

// ── QUERY: Revenue & Diskon periode ini ──
$q = $conn->prepare("
    SELECT
        COALESCE(SUM(t.total), 0)           AS revenue,
        COALESCE(SUM(t.diskon_nominal), 0)  AS total_diskon,
        COUNT(t.id_transaction)             AS total_trx,
        COALESCE(AVG(t.total), 0)           AS avg_trx
    FROM transactions t
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
");
$q->bind_param("ss", $dari, $sampai);
$q->execute();
$row = $q->get_result()->fetch_assoc();
$q->close();

$revenue      = (int)$row['revenue'];
$total_diskon = (int)$row['total_diskon'];
$total_trx    = (int)$row['total_trx'];
$avg_trx      = (float)$row['avg_trx'];

// ── QUERY: COGS (harga_beli_now × qty) periode ini ──
$qCogs = $conn->prepare("
    SELECT COALESCE(SUM(td.harga_beli_now * td.qty), 0) AS cogs
    FROM transaction_detail td
    JOIN transactions t ON t.id_transaction = td.id_transaction
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
");
$qCogs->bind_param("ss", $dari, $sampai);
$qCogs->execute();
$cogs = (int)$qCogs->get_result()->fetch_assoc()['cogs'];
$qCogs->close();

// ── KALKULASI PROFIT ──
$gross_profit  = $revenue - $cogs;
$profit_margin = $revenue > 0 ? round(($gross_profit / $revenue) * 100, 1) : 0;

// ── PERIODE SEBELUMNYA ──
$qPrev = $conn->prepare("
    SELECT
        COALESCE(SUM(t.total), 0) AS revenue,
        COUNT(t.id_transaction)   AS total_trx
    FROM transactions t WHERE DATE(t.tanggal) BETWEEN ? AND ?
");
$qPrev->bind_param("ss", $prevDari, $prevSampai);
$qPrev->execute();
$prevRow = $qPrev->get_result()->fetch_assoc();
$qPrev->close();

$qPrevCogs = $conn->prepare("
    SELECT COALESCE(SUM(td.harga_beli_now * td.qty), 0) AS cogs
    FROM transaction_detail td
    JOIN transactions t ON t.id_transaction = td.id_transaction
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
");
$qPrevCogs->bind_param("ss", $prevDari, $prevSampai);
$qPrevCogs->execute();
$prevCogs   = (int)$qPrevCogs->get_result()->fetch_assoc()['cogs'];
$qPrevCogs->close();

$prevRevenue = (int)$prevRow['revenue'];
$prevProfit  = $prevRevenue - $prevCogs;

function pct($now, $prev) {
    if ($prev == 0) return ['v'=>0,'d'=>'neutral'];
    $p = round((($now - $prev) / $prev) * 100, 1);
    return ['v'=>abs($p),'d'=>$p>=0?'up':'down'];
}
$chgRevenue = pct($revenue, $prevRevenue);
$chgProfit  = pct($gross_profit, $prevProfit);
$chgTrx     = pct($total_trx, $prevRow['total_trx']);

// ── QUERY: Chart pendapatan harian ──
$qChart = $conn->prepare("
    SELECT DATE(tanggal) AS d, SUM(total) AS rev
    FROM transactions WHERE DATE(tanggal) BETWEEN ? AND ?
    GROUP BY DATE(tanggal) ORDER BY d
");
$qChart->bind_param("ss", $dari, $sampai);
$qChart->execute();
$chartRaw = $qChart->get_result()->fetch_all(MYSQLI_ASSOC);
$qChart->close();

// Profit harian
$qPChart = $conn->prepare("
    SELECT DATE(t.tanggal) AS d,
           SUM(t.total) AS rev,
           SUM(td.harga_beli_now * td.qty) AS cost
    FROM transactions t
    JOIN transaction_detail td ON td.id_transaction = t.id_transaction
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
    GROUP BY DATE(t.tanggal) ORDER BY d
");
$qPChart->bind_param("ss", $dari, $sampai);
$qPChart->execute();
$profitChartRaw = $qPChart->get_result()->fetch_all(MYSQLI_ASSOC);
$qPChart->close();

// Build daily array
$chartDates  = [];
$chartRev    = [];
$chartProfit = [];
$cur = strtotime($dari);
$end = strtotime($sampai);
while ($cur <= $end) {
    $d = date('Y-m-d', $cur);
    $lbl = date('d/m', $cur);
    $chartDates[] = $lbl;

    $revFound  = array_values(array_filter($chartRaw, fn($r) => $r['d'] === $d));
    $pFound    = array_values(array_filter($profitChartRaw, fn($r) => $r['d'] === $d));

    $r = $revFound  ? (int)$revFound[0]['rev']  : 0;
    $c = $pFound    ? (int)$pFound[0]['cost']    : 0;

    $chartRev[]    = $r;
    $chartProfit[] = $r - $c;
    $cur = strtotime('+1 day', $cur);
}

// ── QUERY: Top produk by profit ──
$qTop = $conn->prepare("
    SELECT p.nama_product,
           SUM(td.qty)                                AS terjual,
           SUM((td.harga_jual - td.harga_beli_now) * td.qty) AS profit,
           SUM(td.subtotal)                           AS revenue
    FROM transaction_detail td
    JOIN products p ON p.id_product = td.id_product
    JOIN transactions t ON t.id_transaction = td.id_transaction
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
    GROUP BY p.id_product ORDER BY profit DESC LIMIT 5
");
$qTop->bind_param("ss", $dari, $sampai);
$qTop->execute();
$topProds = $qTop->get_result()->fetch_all(MYSQLI_ASSOC);
$qTop->close();

// ── QUERY: Kasir performance ──
$qKasir = $conn->prepare("
    SELECT u.username,
           COUNT(t.id_transaction) AS trx,
           SUM(t.total)            AS revenue
    FROM transactions t
    JOIN users u ON u.id = t.id_user
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
    GROUP BY t.id_user ORDER BY revenue DESC LIMIT 5
");
$qKasir->bind_param("ss", $dari, $sampai);
$qKasir->execute();
$kasirPerf = $qKasir->get_result()->fetch_all(MYSQLI_ASSOC);
$qKasir->close();

// ── HELPERS ──
function rp($n)  { return 'Rp ' . number_format($n, 0, ',', '.'); }
function rpS($n) {
    if ($n >= 1000000000) return 'Rp ' . number_format($n/1000000000,1,'.',',') . 'M';
    if ($n >= 1000000)    return 'Rp ' . number_format($n/1000000,1,'.',',') . 'Jt';
    if ($n >= 1000)       return 'Rp ' . number_format($n/1000,0,'.',',') . 'Rb';
    return 'Rp ' . number_format($n,0,'.',',');
}

$jDates    = json_encode($chartDates);
$jRev      = json_encode($chartRev);
$jProfit   = json_encode($chartProfit);
$maxTopP   = !empty($topProds) ? max(array_column($topProds,'profit')) : 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Dashboard — M4HZTRO POS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Mono:wght@300;400;500&family=Bebas+Neue&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ============================================================
   VARIABLES & RESET
   ============================================================ */
:root {
  --bg:        #080608;
  --glass:     rgba(16,10,12,0.72);
  --border:    rgba(255,255,255,0.07);
  --border-hi: rgba(255,255,255,0.11);
  --red:       #d9202a;
  --red-light: #ff3b46;
  --red-dim:   rgba(217,32,42,0.15);
  --white:     #ffffff;
  --muted:     #3a3a3a;
  --muted2:    #777;
  --green:     #4ade80;
  --amber:     #f59e0b;
  --indigo:    #818cf8;
  --font:      'Plus Jakarta Sans', sans-serif;
  --mono:      'DM Mono', monospace;
  --bebas:     'Bebas Neue', sans-serif;
  --r:         18px;
  --r-sm:      12px;
  --blur:      blur(28px) saturate(180%);
  --sh:        0 8px 32px rgba(0,0,0,0.55), 0 1px 0 rgba(255,255,255,0.05) inset;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body {
  font-family: var(--font);
  background: var(--bg);
  background-image:
    radial-gradient(ellipse 65% 50% at 80% -5%,  rgba(217,32,42,0.22) 0%,transparent 55%),
    radial-gradient(ellipse 45% 35% at 8%  90%,  rgba(180,10,20,0.13) 0%,transparent 55%),
    radial-gradient(ellipse 35% 25% at 50% 50%,  rgba(100,5,10,0.08)  0%,transparent 65%);
  color: var(--white);
  min-height: 100vh;
  overflow-x: hidden;
}
body::before {
  content:'';position:fixed;inset:0;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.025'/%3E%3C/svg%3E");
  pointer-events:none;z-index:0;
}
.orb{position:fixed;border-radius:50%;filter:blur(90px);mix-blend-mode:screen;pointer-events:none;z-index:0}
.o1{width:650px;height:650px;background:radial-gradient(circle,rgba(200,20,35,.75)0%,rgba(150,10,20,.2)55%,transparent 70%);top:-200px;right:-150px;animation:oF 13s ease-in-out infinite alternate}
.o2{width:480px;height:480px;background:radial-gradient(circle,rgba(160,0,60,.50)0%,transparent 65%);bottom:-120px;left:-80px;animation:oF 16s ease-in-out infinite alternate-reverse}
.o3{width:300px;height:300px;background:radial-gradient(circle,rgba(217,32,42,.35)0%,transparent 65%);top:42%;left:32%;animation:oF 10s ease-in-out infinite alternate}
@keyframes oF{0%{transform:translate(0,0) scale(1)}100%{transform:translate(-35px,25px) scale(1.1)}}

/* ============================================================
   ROOT LAYOUT — 2 column asymmetric
   ============================================================ */
.page {
  position:relative;z-index:1;
  display:grid;
  grid-template-columns: 1fr 340px;
  grid-template-rows: auto;
  gap:16px;
  max-width:1380px;
  margin:0 auto;
  padding:32px 36px 56px;
  align-items:start;
}

/* Full-width rows */
.row-full { grid-column: 1 / -1; }

/* ============================================================
   GLASS CARD BASE
   ============================================================ */
.card {
  background: var(--glass);
  backdrop-filter: var(--blur);
  -webkit-backdrop-filter: var(--blur);
  border: 1px solid var(--border-hi);
  border-radius: var(--r);
  box-shadow: var(--sh);
  position: relative;
  overflow: hidden;
}
.card::after {
  content:'';position:absolute;top:0;left:10%;right:10%;height:1px;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,0.09),transparent);
  pointer-events:none;
}

/* ============================================================
   TOP BAR (full width)
   ============================================================ */
.top-bar {
  display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:14px;
}
.top-bar-left { display:flex;flex-direction:column;gap:4px; }
.owner-badge {
  display:inline-flex;align-items:center;gap:7px;
  background:var(--red-dim);border:1px solid rgba(217,32,42,0.28);
  border-radius:30px;padding:5px 14px;
  font-family:var(--mono);font-size:9px;color:var(--red-light);letter-spacing:2px;
  width:fit-content;margin-bottom:6px;
}
.badge-dot{width:5px;height:5px;border-radius:50%;background:var(--red-light);box-shadow:0 0 7px var(--red-light);animation:pulse 2s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(.7)}}
.top-bar h1 {
  font-family:var(--bebas);font-size:clamp(30px,3.5vw,46px);
  letter-spacing:2px;line-height:1;
  background:linear-gradient(135deg,#fff 30%,rgba(255,255,255,.45) 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.top-bar-sub {
  font-family:var(--mono);font-size:10px;color:var(--muted2);letter-spacing:.5px;margin-top:3px;
}
.periode-pills{display:flex;gap:6px;flex-wrap:wrap}
.pill {
  padding:6px 14px;border-radius:30px;
  border:1px solid var(--border);background:rgba(255,255,255,0.04);
  color:var(--muted2);font-size:10px;font-family:var(--mono);
  cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap;letter-spacing:.5px;
}
.pill:hover,.pill.active{background:var(--red-dim);border-color:rgba(217,32,42,.35);color:var(--red-light)}

/* ============================================================
   HERO BALANCE CARD (like reference's big dark card)
   ============================================================ */
.hero-card {
  padding:30px 32px;
  background:linear-gradient(145deg,rgba(80,5,10,0.75)0%,rgba(20,4,6,0.90)100%);
  border-color:rgba(217,32,42,0.22);
  box-shadow:0 12px 48px rgba(217,32,42,0.15),0 1px 0 rgba(255,100,80,0.07) inset;
  position:relative;overflow:hidden;
}
/* Wavy mesh decoration */
.hero-card::before {
  content:'';position:absolute;inset:0;
  background:
    radial-gradient(ellipse 80% 60% at 90% 50%,rgba(217,32,42,0.18)0%,transparent 60%),
    radial-gradient(ellipse 40% 50% at 10% 20%,rgba(255,80,80,0.08)0%,transparent 55%);
  pointer-events:none;
}
.hero-card::after { display:none; }

.hero-top {
  display:flex;align-items:flex-start;justify-content:space-between;
  margin-bottom:20px;
}
.hero-label {
  font-family:var(--mono);font-size:9px;letter-spacing:2.5px;
  text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:8px;
}
.hero-profit {
  font-family:var(--bebas);font-size:clamp(36px,5vw,58px);
  letter-spacing:1px;line-height:1;color:#6bffa0;
  text-shadow:0 0 40px rgba(74,222,128,0.3);
}
.hero-sub {
  font-family:var(--mono);font-size:11px;color:rgba(255,255,255,.35);margin-top:6px;
}

/* Donut ring in hero */
.hero-ring-wrap {
  width:80px;height:80px;position:relative;flex-shrink:0;
}
.hero-ring-label {
  position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  font-family:var(--bebas);font-size:18px;color:var(--green);letter-spacing:0.5px;
}

/* Mini stats row inside hero */
.hero-stats {
  display:grid;grid-template-columns:repeat(3,1fr);gap:12px;
  margin-top:22px;padding-top:20px;
  border-top:1px solid rgba(255,255,255,0.07);
}
.hero-stat-item { display:flex;flex-direction:column;align-items:center;gap:4px; }
.hsi-label { font-family:var(--mono);font-size:9px;letter-spacing:1.5px;color:rgba(255,255,255,.3);text-transform:uppercase;margin-bottom:5px; }
.hsi-val   { font-family:var(--bebas);font-size:22px;letter-spacing:.5px;line-height:1; }
.hsi-chg   { font-family:var(--mono);font-size:10px;margin-top:3px; }
.up{color:var(--green)}.down{color:var(--red-light)}.neu{color:var(--muted2)}

/* ============================================================
   MINI BREAKDOWN CARDS (4 below hero)
   ============================================================ */
.mini-grid {
  display:grid;grid-template-columns:repeat(4,1fr);gap:10px;
}
.mini-card { padding:16px 18px; }
.mini-card-icon {
  width:32px;height:32px;border-radius:9px;
  display:flex;align-items:center;justify-content:center;margin-bottom:10px;
  font-size:14px;
}
.mini-card-label { font-family:var(--mono);font-size:9px;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted2);margin-bottom:6px; }
.mini-card-val   { font-family:var(--bebas);font-size:22px;letter-spacing:.5px;line-height:1; }
.mini-card-sub   { font-family:var(--mono);font-size:9px;color:var(--muted2);margin-top:4px; }

/* ============================================================
   CHART CARD (left, under mini cards)
   ============================================================ */
.chart-main { padding:24px 26px; }
.chart-hdr  { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px; }
.chart-title { font-size:13px;font-weight:700; }
.chart-sub   { font-family:var(--mono);font-size:10px;color:var(--muted2);margin-top:3px; }
.chip {
  background:rgba(255,255,255,0.05);border:1px solid var(--border);
  border-radius:20px;padding:3px 10px;
  font-family:var(--mono);font-size:9px;letter-spacing:1px;color:var(--muted2);
}
.chart-wrap { height:210px;position:relative; }
/* Legend row below chart */
.chart-legend {
  display:flex;gap:16px;margin-top:14px;padding-top:12px;
  border-top:1px solid rgba(255,255,255,0.05);
}
.cl-item { display:flex;align-items:center;gap:6px;font-family:var(--mono);font-size:10px;color:var(--muted2); }
.cl-dot  { width:8px;height:8px;border-radius:2px;flex-shrink:0; }

/* ============================================================
   RIGHT PANEL
   ============================================================ */
.right-panel { display:flex;flex-direction:column;gap:14px; }

/* Transaction list card */
.trx-card { padding:22px 22px; }
.panel-title {
  font-size:13px;font-weight:700;margin-bottom:4px;
}
.panel-sub { font-family:var(--mono);font-size:9px;color:var(--muted2);letter-spacing:.5px;margin-bottom:16px; }

.trx-item {
  display:flex;align-items:center;gap:12px;padding:10px 0;
  border-bottom:1px solid rgba(255,255,255,0.03);
  transition:background .15s;
}
.trx-item:last-child{border-bottom:none}
.trx-item:hover{background:rgba(255,255,255,0.02);border-radius:8px;padding-left:6px;padding-right:6px}
.trx-avatar {
  width:34px;height:34px;border-radius:10px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-size:12px;font-weight:700;color:#fff;text-transform:uppercase;
}
.trx-avatar.a { background:linear-gradient(135deg,#d9202a,#8b0a14);border:1px solid rgba(217,32,42,.3); }
.trx-avatar.b { background:linear-gradient(135deg,#1e3a5f,#0d1f35);border:1px solid rgba(74,158,255,.2); }
.trx-avatar.c { background:linear-gradient(135deg,#14532d,#052e16);border:1px solid rgba(74,222,128,.2); }
.trx-name { font-size:12px;font-weight:600;color:var(--white); }
.trx-time { font-family:var(--mono);font-size:10px;color:var(--muted2);margin-top:2px; }
.trx-amount { margin-left:auto;font-family:var(--mono);font-size:12px;font-weight:600; }
.trx-amount.pos { color:var(--green); }
.trx-amount.neg { color:var(--red-light); }

/* Donut result card */
.result-card { padding:22px 22px; }
.donut-wrap { height:150px;position:relative;margin:12px 0; }

.legend-grid { display:flex;flex-direction:column;gap:7px;margin-top:8px; }
.lg-item { display:flex;align-items:center;justify-content:space-between; }
.lg-left { display:flex;align-items:center;gap:7px;font-family:var(--mono);font-size:10px;color:var(--muted2); }
.lg-dot  { width:7px;height:7px;border-radius:2px;flex-shrink:0; }
.lg-val  { font-family:var(--mono);font-size:10px;color:var(--white); }

/* Top products (below chart in left col) */
.toprod-card { padding:22px 26px; }
.toprod-item {
  display:flex;align-items:center;gap:12px;padding:9px 0;
  border-bottom:1px solid rgba(255,255,255,0.03);
}
.toprod-item:last-child{border-bottom:none}
.toprod-rank {
  width:26px;height:26px;border-radius:7px;
  background:rgba(255,255,255,.05);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-family:var(--mono);font-size:10px;color:var(--muted2);flex-shrink:0;
}
.toprod-rank.g{background:rgba(74,222,128,.10);border-color:rgba(74,222,128,.22);color:var(--green)}
.toprod-name { flex:1;font-size:11px;font-family:var(--mono);color:var(--muted2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.toprod-bar-wrap { flex:1;height:3px;background:rgba(255,255,255,.06);border-radius:2px;overflow:hidden; }
.toprod-bar { height:100%;background:linear-gradient(90deg,var(--green),#22c55e);border-radius:2px;transition:width 1s cubic-bezier(.4,0,.2,1); }
.toprod-val { font-family:var(--mono);font-size:11px;color:var(--green);min-width:55px;text-align:right; }

/* ============================================================
   ANIMATIONS
   ============================================================ */
.fade-in{opacity:0;transform:translateY(12px);animation:fadeUp .42s ease forwards}
@keyframes fadeUp{to{opacity:1;transform:translateY(0)}}
.d1{animation-delay:.04s}.d2{animation-delay:.09s}.d3{animation-delay:.14s}
.d4{animation-delay:.19s}.d5{animation-delay:.26s}.d6{animation-delay:.33s}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media(max-width:1050px){
  .page{grid-template-columns:1fr}
  .right-panel{display:grid;grid-template-columns:1fr 1fr;gap:14px}
}
@media(max-width:680px){
  .page{padding:16px 14px 36px}
  .mini-grid{grid-template-columns:repeat(2,1fr)}
  .right-panel{grid-template-columns:1fr}
  .hero-stats{grid-template-columns:repeat(2,1fr)}
}


*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
  font-family: var(--font);
  background: var(--bg);
  background-image:
    radial-gradient(ellipse 70% 55% at 85% 0%,   rgba(217,32,42,0.20) 0%, transparent 55%),
    radial-gradient(ellipse 50% 40% at 10% 90%,   rgba(180,10,20,0.12) 0%, transparent 55%),
    radial-gradient(ellipse 40% 30% at 50% 50%,   rgba(100,5,10,0.08) 0%, transparent 65%);
  color: var(--white);
  min-height: 100vh;
  overflow-x: hidden;
}

/* Grain */
body::before {
  content:''; position:fixed; inset:0;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
  pointer-events:none; z-index:0;
}

/* Aurora orbs */
.orb { position:fixed; border-radius:50%; filter:blur(90px); mix-blend-mode:screen; pointer-events:none; z-index:0; }
.orb1 { width:700px;height:700px;background:radial-gradient(circle,rgba(200,20,35,0.75)0%,rgba(150,10,20,0.25)55%,transparent 70%);top:-200px;right:-150px;animation:oF1 13s ease-in-out infinite alternate; }
.orb2 { width:500px;height:500px;background:radial-gradient(circle,rgba(160,0,60,0.55)0%,transparent 65%);bottom:-150px;left:-100px;animation:oF2 16s ease-in-out infinite alternate; }
.orb3 { width:350px;height:350px;background:radial-gradient(circle,rgba(217,32,42,0.40)0%,transparent 65%);top:45%;left:35%;animation:oF3 10s ease-in-out infinite alternate; }
@keyframes oF1{0%{transform:translate(0,0) scale(1)}100%{transform:translate(-40px,30px) scale(1.1)}}
@keyframes oF2{0%{transform:translate(0,0) scale(1)}100%{transform:translate(50px,-30px) scale(1.12)}}
@keyframes oF3{0%{transform:translate(0,0) scale(1);opacity:.4}100%{transform:translate(20px,-20px) scale(1.2);opacity:.8}}

/* ============================================================
   LAYOUT
   ============================================================ */
.page {
  position:relative; z-index:1;
  max-width:1380px; margin:0 auto;
  padding:36px 40px 60px;
}

/* ============================================================
   PAGE HEADER
   ============================================================ */
.page-header {
  display:flex; align-items:flex-end; justify-content:space-between;
  margin-bottom:32px; flex-wrap:wrap; gap:16px;
}
.page-header-left h1 {
  font-family: var(--bebas);
  font-size: clamp(34px,4vw,52px);
  letter-spacing: 2px; line-height:1;
  background: linear-gradient(135deg,#fff 30%,rgba(255,255,255,0.5) 100%);
  -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.page-header-left p {
  font-family:var(--mono); font-size:11px; color:var(--muted2);
  margin-top:6px; letter-spacing:0.5px;
}
.owner-badge {
  display:inline-flex; align-items:center; gap:7px;
  background:rgba(217,32,42,0.12); border:1px solid rgba(217,32,42,0.28);
  border-radius:30px; padding:6px 14px;
  font-family:var(--mono); font-size:10px; color:var(--red-light);
  letter-spacing:1.5px;
}
.owner-badge-dot { width:6px;height:6px;border-radius:50%;background:var(--red-light);box-shadow:0 0 8px var(--red-light);animation:pulse 2s ease-in-out infinite; }
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.8)}}

/* Periode pills */
.periode-wrap { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.pill {
  padding:7px 16px; border-radius:30px;
  border:1px solid var(--border); background:rgba(255,255,255,0.04);
  color:var(--muted2); font-size:11px; font-family:var(--mono);
  cursor:pointer; text-decoration:none; transition:all .2s; white-space:nowrap;
  letter-spacing:0.5px;
}
.pill:hover,.pill.active {
  background:var(--red-dim); border-color:rgba(217,32,42,0.35); color:var(--red-light);
}

/* ============================================================
   GLASS CARD
   ============================================================ */
.card {
  background: var(--glass);
  backdrop-filter: var(--blur);
  -webkit-backdrop-filter: var(--blur);
  border: 1px solid var(--border-hi);
  border-radius: var(--r);
  box-shadow: var(--shadow);
  position:relative; overflow:hidden;
}
.card::after {
  content:''; position:absolute; top:0; left:10%; right:10%; height:1px;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,0.09),transparent);
  pointer-events:none;
}

/* ============================================================
   STAT CARDS
   ============================================================ */
.stats-grid {
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:14px; margin-bottom:20px;
}

.stat-card { padding:22px 24px; }

/* Profit card — special accent */
.stat-card.profit-card {
  background:linear-gradient(145deg,rgba(74,222,128,0.12)0%,rgba(20,40,20,0.70)100%);
  border-color:rgba(74,222,128,0.20);
  box-shadow:0 8px 32px rgba(74,222,128,0.08), 0 1px 0 rgba(74,222,128,0.08) inset;
}
.stat-card.revenue-card {
  background:linear-gradient(145deg,rgba(90,8,14,0.65)0%,rgba(30,5,8,0.78)100%);
  border-color:rgba(217,32,42,0.25);
  box-shadow:0 8px 32px rgba(217,32,42,0.12), 0 1px 0 rgba(255,80,80,0.07) inset;
}

/* Corner glow */
.stat-card::before {
  content:''; position:absolute; top:0; right:0;
  width:100px; height:100px;
  background:radial-gradient(circle at top right,rgba(217,32,42,0.15),transparent 70%);
  pointer-events:none;
}
.profit-card::before {
  background:radial-gradient(circle at top right,rgba(74,222,128,0.12),transparent 70%);
}

.stat-lbl {
  font-family:var(--mono); font-size:9px; letter-spacing:2px;
  text-transform:uppercase; color:var(--muted2); margin-bottom:10px;
}
.stat-val {
  font-family:var(--bebas); font-size:clamp(24px,3vw,36px);
  letter-spacing:1px; line-height:1; margin-bottom:8px; color:var(--white);
}
.revenue-card .stat-val { color:#ff8088; }
.profit-card  .stat-val { color:#6bffa0; }

.stat-chg {
  display:flex; align-items:center; gap:5px;
  font-family:var(--mono); font-size:10px; color:var(--muted2);
}
.up   { color:var(--green); }
.down { color:var(--red-light); }

.stat-icon { position:absolute; bottom:14px; right:16px; opacity:.06; font-size:36px; }

/* ============================================================
   PROFIT BREAKDOWN CARD
   ============================================================ */
.breakdown-card { padding:24px 26px; margin-bottom:20px; }
.breakdown-title {
  font-size:13px; font-weight:700; margin-bottom:20px;
  padding-bottom:14px; border-bottom:1px solid var(--border);
  display:flex; align-items:center; gap:8px;
}
.breakdown-title::before {
  content:''; width:3px; height:14px; background:var(--green);
  border-radius:2px; display:block;
  box-shadow:0 0 8px rgba(74,222,128,0.6);
}
.breakdown-row {
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.03);
}
.breakdown-row:last-child { border-bottom:none; }
.breakdown-label {
  font-size:12px; color:var(--muted2);
  display:flex; align-items:center; gap:8px;
}
.breakdown-indicator { width:8px; height:8px; border-radius:2px; }
.breakdown-value { font-family:var(--mono); font-size:13px; font-weight:600; }
.breakdown-value.green  { color:var(--green); }
.breakdown-value.red    { color:var(--red-light); }
.breakdown-value.white  { color:var(--white); }
.breakdown-value.amber  { color:var(--amber); }

/* Margin bar */
.margin-bar-wrap { margin-top:16px; }
.margin-bar-label {
  display:flex; justify-content:space-between; align-items:center;
  font-family:var(--mono); font-size:10px; color:var(--muted2); margin-bottom:6px;
}
.margin-bar-track {
  height:6px; background:rgba(255,255,255,0.06); border-radius:3px; overflow:hidden;
}
.margin-bar-fill {
  height:100%; border-radius:3px;
  background:linear-gradient(90deg,var(--green),#22c55e);
  box-shadow:0 0 8px rgba(74,222,128,0.4);
  transition:width 1s cubic-bezier(.4,0,.2,1);
}

/* ============================================================
   CHART SECTION
   ============================================================ */
.chart-row {
  display:grid; grid-template-columns:1fr 340px;
  gap:14px; margin-bottom:20px;
}
.chart-card { padding:24px 26px; }
.chart-card-hdr {
  display:flex; align-items:flex-start; justify-content:space-between;
  margin-bottom:18px;
}
.chart-card-title { font-size:13px; font-weight:700; }
.chart-card-sub   { font-family:var(--mono); font-size:10px; color:var(--muted2); margin-top:3px; }
.chip {
  background:rgba(255,255,255,0.05); border:1px solid var(--border);
  border-radius:20px; padding:3px 10px;
  font-family:var(--mono); font-size:9px; letter-spacing:1px; color:var(--muted2);
}
.chart-wrap { height:220px; position:relative; }

/* ============================================================
   TOP PRODUCTS
   ============================================================ */
.prod-item {
  display:flex; align-items:center; gap:12px; padding:10px 0;
  border-bottom:1px solid rgba(255,255,255,0.03);
}
.prod-item:last-child { border-bottom:none; }
.prod-rank {
  width:28px; height:28px; border-radius:8px;
  background:rgba(255,255,255,0.05); border:1px solid var(--border);
  display:flex; align-items:center; justify-content:center;
  font-size:11px; font-family:var(--mono); color:var(--muted2); flex-shrink:0;
}
.prod-rank.top { background:rgba(74,222,128,0.12); border-color:rgba(74,222,128,0.25); color:var(--green); }
.prod-name {
  flex:1; font-size:12px; color:var(--muted2); font-family:var(--mono);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.prod-bar-wrap { flex:1; height:3px; background:rgba(255,255,255,0.06); border-radius:2px; overflow:hidden; }
.prod-bar { height:100%; background:linear-gradient(90deg,var(--green),#22c55e); border-radius:2px; transition:width 1s cubic-bezier(.4,0,.2,1); }
.prod-profit { font-family:var(--mono); font-size:11px; color:var(--green); min-width:60px; text-align:right; }

/* ============================================================
   KASIR PERFORMANCE
   ============================================================ */
.bottom-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.perf-card { padding:22px 24px; }
.section-title {
  font-size:13px; font-weight:700; margin-bottom:16px;
  display:flex; align-items:center; gap:8px;
}
.section-title::before {
  content:''; width:3px; height:14px; background:var(--red);
  border-radius:2px; display:block;
}
.kasir-item {
  display:flex; align-items:center; gap:12px; padding:10px 0;
  border-bottom:1px solid rgba(255,255,255,0.03);
}
.kasir-item:last-child { border-bottom:none; }
.kasir-avatar {
  width:34px; height:34px; border-radius:10px;
  background:linear-gradient(135deg,var(--red)0%,#8b0a14 100%);
  border:1px solid rgba(217,32,42,0.3);
  display:flex; align-items:center; justify-content:center;
  font-size:13px; font-weight:700; color:#fff;
  text-transform:uppercase; flex-shrink:0;
}
.kasir-name { font-size:12px; font-weight:600; color:var(--white); }
.kasir-trx  { font-family:var(--mono); font-size:10px; color:var(--muted2); margin-top:2px; }
.kasir-rev  { font-family:var(--mono); font-size:12px; color:var(--red-light); margin-left:auto; font-weight:500; }

/* ============================================================
   ANIMATIONS
   ============================================================ */
.fade-in { opacity:0; transform:translateY(14px); animation:fadeUp .45s ease forwards; }
@keyframes fadeUp { to{opacity:1;transform:translateY(0)} }
.d1{animation-delay:.05s}.d2{animation-delay:.10s}.d3{animation-delay:.15s}
.d4{animation-delay:.20s}.d5{animation-delay:.28s}.d6{animation-delay:.36s}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media(max-width:1100px){
  .chart-row{grid-template-columns:1fr}
  .stats-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:700px){
  .page{padding:20px 16px 40px}
  .stats-grid{grid-template-columns:1fr}
  .bottom-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<div class="orb o1"></div>
<div class="orb o2"></div>
<div class="orb o3"></div>

<?php
// Recent transactions untuk panel kanan
$qRecent = $conn->prepare("
    SELECT t.id_transaction, t.tanggal, t.total, u.username
    FROM transactions t
    LEFT JOIN users u ON u.id = t.id_user
    ORDER BY t.tanggal DESC LIMIT 5
");
$qRecent->execute();
$recentTrx = $qRecent->get_result()->fetch_all(MYSQLI_ASSOC);
$qRecent->close();

// Donut: Revenue, COGS, Profit breakdown
$donutData   = json_encode([$revenue, $cogs, $gross_profit]);
$donutLabels = json_encode(['Revenue','COGS','Profit']);
$donutColors = json_encode(['#d9202a','#f59e0b','#4ade80']);

$avatarColors = ['a','b','c','a','b'];
?>

<div class="page">

  <!-- ══ TOP BAR (full width) ══ -->
  <div class="top-bar row-full fade-in d1">
    <div class="top-bar-left">
      <div class="owner-badge"><span class="badge-dot"></span>OWNER VIEW</div>
      <h1>Profit Dashboard</h1>
      <div class="top-bar-sub"><?= $dariLabel ?> — <?= $sampaiLabel ?></div>
    </div>
    <div class="periode-pills">
      <?php foreach(['today'=>'Hari Ini','week'=>'Minggu Ini','month'=>'Bulan Ini','year'=>'Tahun Ini'] as $k=>$v): ?>
      <a href="?page=owner-dashboard&periode=<?= $k ?>" class="pill <?= $periode===$k?'active':'' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ══ LEFT COLUMN ══ -->
  <div style="display:flex;flex-direction:column;gap:14px">

    <!-- HERO BALANCE CARD -->
    <div class="card hero-card fade-in d2">
      <div class="hero-top">
        <div>
          <div class="hero-label">Gross Profit Bersih</div>
          <div class="hero-profit"><?= rp($gross_profit) ?></div>
          <div class="hero-sub">Revenue <?= rpS($revenue) ?> · COGS <?= rpS($cogs) ?></div>
        </div>
        <!-- Mini donut ring -->
        <div class="hero-ring-wrap">
          <canvas id="heroRing" width="80" height="80"></canvas>
          <div class="hero-ring-label"><?= $profit_margin ?>%</div>
        </div>
      </div>

      <!-- 3 mini stats inside hero -->
      <div class="hero-stats">
        <div class="hero-stat-item">
          <div class="hsi-label">Total Transaksi</div>
          <div class="hsi-val"><?= number_format($total_trx) ?></div>
          <div class="hsi-chg">
            <span class="<?= $chgTrx['d'] ?>"><?= $chgTrx['d']==='up'?'↑':'↓' ?> <?= $chgTrx['v'] ?>%</span>
          </div>
        </div>
        <div class="hero-stat-item">
          <div class="hsi-label">Avg / Transaksi</div>
          <div class="hsi-val" style="font-size:18px"><?= rpS((int)$avg_trx) ?></div>
          <div class="hsi-chg neu">periode ini</div>
        </div>
        <div class="hero-stat-item">
          <div class="hsi-label">Profit Margin</div>
          <div class="hsi-val" style="color:var(--green)"><?= $profit_margin ?>%</div>
          <div class="hsi-chg">
            <span class="<?= $chgProfit['d'] ?>"><?= $chgProfit['d']==='up'?'↑':'↓' ?> <?= $chgProfit['v'] ?>%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 4 MINI BREAKDOWN CARDS -->
    <div class="mini-grid fade-in d3">
      <div class="card mini-card">
        <div class="mini-card-icon" style="background:rgba(217,32,42,0.12);color:var(--red-light)">💰</div>
        <div class="mini-card-label">Revenue</div>
        <div class="mini-card-val" style="color:#ff8088;font-size:18px"><?= rpS($revenue) ?></div>
        <div class="mini-card-sub">
          <span class="<?= $chgRevenue['d'] ?>"><?= $chgRevenue['d']==='up'?'↑':'↓' ?> <?= $chgRevenue['v'] ?>%</span>
        </div>
      </div>
      <div class="card mini-card">
        <div class="mini-card-icon" style="background:rgba(245,158,11,0.12);color:var(--amber)">📦</div>
        <div class="mini-card-label">HPP / COGS</div>
        <div class="mini-card-val" style="color:var(--amber);font-size:18px"><?= rpS($cogs) ?></div>
        <div class="mini-card-sub" style="color:var(--muted2)">Modal terjual</div>
      </div>
      <div class="card mini-card">
        <div class="mini-card-icon" style="background:rgba(129,140,248,0.12);color:var(--indigo)">🏷️</div>
        <div class="mini-card-label">Total Diskon</div>
        <div class="mini-card-val" style="color:var(--indigo);font-size:18px"><?= rpS($total_diskon) ?></div>
        <div class="mini-card-sub" style="color:var(--muted2)">Diberikan</div>
      </div>
      <div class="card mini-card">
        <div class="mini-card-icon" style="background:rgba(74,222,128,0.10);color:var(--green)">📈</div>
        <div class="mini-card-label">Profit / Trx</div>
        <?php $avgProfit = $total_trx>0?round($gross_profit/$total_trx):0; ?>
        <div class="mini-card-val" style="color:var(--green);font-size:18px"><?= rpS($avgProfit) ?></div>
        <div class="mini-card-sub" style="color:var(--muted2)">Rata-rata</div>
      </div>
    </div>

    <!-- REVENUE vs PROFIT CHART -->
    <div class="card chart-main fade-in d4">
      <div class="chart-hdr">
        <div>
          <div class="chart-title">Revenue vs Profit Harian</div>
          <div class="chart-sub"><?= $dariLabel ?> — <?= $sampaiLabel ?></div>
        </div>
        <div class="chip">BAR + LINE</div>
      </div>
      <div class="chart-wrap"><canvas id="mainChart"></canvas></div>
      <div class="chart-legend">
        <div class="cl-item"><div class="cl-dot" style="background:#d9202a"></div>Revenue</div>
        <div class="cl-item"><div class="cl-dot" style="background:#4ade80"></div>Profit</div>
      </div>
    </div>

    <!-- TOP PRODUCTS -->
    <div class="card toprod-card fade-in d5">
      <div class="panel-title">Produk Paling Profitable</div>
      <div class="panel-sub">BERDASARKAN GROSS PROFIT PERIODE INI</div>
      <?php if(empty($topProds)): ?>
      <div style="text-align:center;padding:24px;font-family:var(--mono);font-size:11px;color:var(--muted2)">Belum ada data</div>
      <?php else: ?>
      <?php $maxP = max(array_column($topProds,'profit')) ?: 1; ?>
      <?php foreach($topProds as $i=>$p): $pct=round($p['profit']/$maxP*100); ?>
      <div class="toprod-item">
        <div class="toprod-rank <?= $i===0?'g':'' ?>"><?= $i+1 ?></div>
        <div class="toprod-name"><?= htmlspecialchars($p['nama_product']) ?></div>
        <div class="toprod-bar-wrap">
          <div class="toprod-bar" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="toprod-val"><?= rpS($p['profit']) ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div><!-- /left col -->

  <!-- ══ RIGHT PANEL ══ -->
  <div class="right-panel">

    <!-- RECENT TRANSACTIONS -->
    <div class="card trx-card fade-in d2">
      <div class="panel-title">Transaksi Terbaru</div>
      <div class="panel-sub">RECENT ACTIVITY</div>
      <?php if(empty($recentTrx)): ?>
      <div style="text-align:center;padding:24px;font-family:var(--mono);font-size:11px;color:var(--muted2)">Belum ada transaksi</div>
      <?php else: ?>
      <?php foreach($recentTrx as $i=>$t):
        $isProfit = true; // semua transaksi = income
        $col = $avatarColors[$i % count($avatarColors)];
        $initial = strtoupper(substr($t['username']??'?',0,1));
        $timeAgo = date('H:i', strtotime($t['tanggal']));
      ?>
      <div class="trx-item">
        <div class="trx-avatar <?= $col ?>"><?= $initial ?></div>
        <div>
          <div class="trx-name"><?= htmlspecialchars($t['username']??'Unknown') ?></div>
          <div class="trx-time">#<?= $t['id_transaction'] ?> · <?= $timeAgo ?></div>
        </div>
        <div class="trx-amount pos">+<?= rpS($t['total']) ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- CURRENT RESULT DONUT -->
    <div class="card result-card fade-in d3">
      <div class="panel-title">Current Result</div>
      <div class="panel-sub">DISTRIBUSI KEUANGAN</div>
      <div class="donut-wrap">
        <canvas id="resultDonut"></canvas>
      </div>
      <div class="legend-grid">
        <div class="lg-item">
          <div class="lg-left"><div class="lg-dot" style="background:#d9202a"></div>Revenue</div>
          <div class="lg-val"><?= rpS($revenue) ?></div>
        </div>
        <div class="lg-item">
          <div class="lg-left"><div class="lg-dot" style="background:#f59e0b"></div>COGS</div>
          <div class="lg-val"><?= rpS($cogs) ?></div>
        </div>
        <div class="lg-item">
          <div class="lg-left"><div class="lg-dot" style="background:#4ade80"></div>Profit</div>
          <div class="lg-val" style="color:var(--green)"><?= rpS($gross_profit) ?></div>
        </div>
      </div>
    </div>

    <!-- KASIR PERFORMANCE -->
    <div class="card trx-card fade-in d4">
      <div class="panel-title">Performa Kasir</div>
      <div class="panel-sub">REVENUE PER KASIR</div>
      <?php if(empty($kasirPerf)): ?>
      <div style="text-align:center;padding:24px;font-family:var(--mono);font-size:11px;color:var(--muted2)">Belum ada data</div>
      <?php else: ?>
      <?php foreach($kasirPerf as $i=>$k):
        $col = $avatarColors[$i % count($avatarColors)];
      ?>
      <div class="trx-item">
        <div class="trx-avatar <?= $col ?>"><?= strtoupper(substr($k['username'],0,1)) ?></div>
        <div>
          <div class="trx-name"><?= htmlspecialchars($k['username']) ?></div>
          <div class="trx-time"><?= $k['trx'] ?> transaksi</div>
        </div>
        <div class="trx-amount pos"><?= rpS($k['revenue']) ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div><!-- /right panel -->

</div><!-- /.page -->

<script>
Chart.defaults.color       = '#555';
Chart.defaults.font.family = "'DM Mono', monospace";
Chart.defaults.font.size   = 10;

/* ── HERO RING (mini donut) ── */
new Chart(document.getElementById('heroRing'), {
  type:'doughnut',
  data:{
    datasets:[{
      data:[<?= $profit_margin ?>, <?= 100-$profit_margin ?>],
      backgroundColor:['#4ade80','rgba(255,255,255,0.06)'],
      borderWidth:0,
    }]
  },
  options:{
    cutout:'72%',responsive:false,
    plugins:{legend:{display:false},tooltip:{enabled:false}}
  }
});

/* ── MAIN CHART: Revenue (bar) + Profit (line) ── */
const mCtx = document.getElementById('mainChart').getContext('2d');
const gradB = mCtx.createLinearGradient(0,0,0,210);
gradB.addColorStop(0,'rgba(217,32,42,0.55)');
gradB.addColorStop(1,'rgba(217,32,42,0.05)');

new Chart(mCtx, {
  type:'bar',
  data:{
    labels: <?= $jDates ?>,
    datasets:[
      {
        type:'bar',label:'Revenue',
        data: <?= $jRev ?>,
        backgroundColor:gradB,
        borderColor:'rgba(217,32,42,0.7)',borderWidth:1,
        borderRadius:5,borderSkipped:false,
        yAxisID:'y',
      },
      {
        type:'line',label:'Profit',
        data: <?= $jProfit ?>,
        borderColor:'#4ade80',borderWidth:2,
        backgroundColor:'transparent',
        pointBackgroundColor:'#4ade80',pointBorderColor:'#080608',
        pointBorderWidth:2,pointRadius:3,pointHoverRadius:5,
        tension:0.4,fill:false,
        yAxisID:'y',
      }
    ]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    interaction:{mode:'index',intersect:false},
    plugins:{
      legend:{display:false},
      tooltip:{
        backgroundColor:'#161618',
        borderColor:'rgba(217,32,42,0.3)',borderWidth:1,
        titleColor:'#666',bodyColor:'#fff',
        callbacks:{label:c=>' Rp '+c.parsed.y.toLocaleString('id-ID')}
      }
    },
    scales:{
      x:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{maxRotation:0}},
      y:{grid:{color:'rgba(255,255,255,0.04)'},
         ticks:{callback:v=>v>=1e6?(v/1e6).toFixed(1)+'Jt':(v/1e3).toFixed(0)+'Rb'}}
    }
  }
});

/* ── RESULT DONUT ── */
new Chart(document.getElementById('resultDonut'), {
  type:'doughnut',
  data:{
    labels: <?= $donutLabels ?>,
    datasets:[{
      data: <?= $donutData ?>,
      backgroundColor: <?= $donutColors ?>,
      borderColor:'rgba(16,10,12,0.8)',borderWidth:3,
    }]
  },
  options:{
    responsive:true,maintainAspectRatio:false,cutout:'65%',
    plugins:{
      legend:{display:false},
      tooltip:{
        backgroundColor:'#161618',
        borderColor:'rgba(217,32,42,0.3)',borderWidth:1,
        titleColor:'#666',bodyColor:'#fff',
        callbacks:{label:c=>' Rp '+c.parsed.toLocaleString('id-ID')}
      }
    }
  }
});

/* Animate bars */
document.querySelectorAll('.toprod-bar').forEach(b=>{
  const w=b.style.width;b.style.width='0';
  setTimeout(()=>b.style.width=w,600);
});
</script>
</body>
</html>