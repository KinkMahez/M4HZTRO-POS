<?php
require_once  'config/database.php';
$conn->query("SET time_zone = '+07:00'");

// ── PERIODE FILTER ──
$periode = $_GET['periode'] ?? 'month';
switch ($periode) {
    case 'today': $dari = $sampai = date('Y-m-d'); break;
    case 'week':
        $dari   = date('Y-m-d', strtotime('monday this week'));
        $sampai = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'year':  $dari = date('Y-01-01'); $sampai = date('Y-12-31'); break;
    default:      $dari = date('Y-m-01');  $sampai = date('Y-m-t');
}
$dariLabel   = date('d M Y', strtotime($dari));
$sampaiLabel = date('d M Y', strtotime($sampai));

// Periode sebelumnya
$diffDays   = (strtotime($sampai) - strtotime($dari)) / 86400 + 1;
$prevDari   = date('Y-m-d', strtotime("$dari -$diffDays days"));
$prevSampai = date('Y-m-d', strtotime("$dari -1 day"));

// ── Stats periode ini ──
$q = $conn->prepare("SELECT COALESCE(SUM(t.total),0) AS revenue, COALESCE(SUM(t.diskon_nominal),0) AS total_diskon, COUNT(t.id_transaction) AS total_trx, COALESCE(AVG(t.total),0) AS avg_trx FROM transactions t WHERE DATE(t.tanggal) BETWEEN ? AND ?");
$q->bind_param("ss", $dari, $sampai); $q->execute();
$row = $q->get_result()->fetch_assoc(); $q->close();
$revenue = (int)$row['revenue']; $total_diskon = (int)$row['total_diskon'];
$total_trx = (int)$row['total_trx']; $avg_trx = (float)$row['avg_trx'];

// ── COGS ──
$qC = $conn->prepare("SELECT COALESCE(SUM(td.harga_beli_now*td.qty),0) AS cogs FROM transaction_detail td JOIN transactions t ON t.id_transaction=td.id_transaction WHERE DATE(t.tanggal) BETWEEN ? AND ?");
$qC->bind_param("ss", $dari, $sampai); $qC->execute();
$cogs = (int)$qC->get_result()->fetch_assoc()['cogs']; $qC->close();

$gross_profit  = $revenue - $cogs;
$profit_margin = $revenue > 0 ? round(($gross_profit / $revenue) * 100, 1) : 0;

// ── Periode sebelumnya ──
$qP = $conn->prepare("SELECT COALESCE(SUM(t.total),0) AS revenue, COUNT(t.id_transaction) AS total_trx FROM transactions t WHERE DATE(t.tanggal) BETWEEN ? AND ?");
$qP->bind_param("ss", $prevDari, $prevSampai); $qP->execute();
$prevRow = $qP->get_result()->fetch_assoc(); $qP->close();

$qPC = $conn->prepare("SELECT COALESCE(SUM(td.harga_beli_now*td.qty),0) AS cogs FROM transaction_detail td JOIN transactions t ON t.id_transaction=td.id_transaction WHERE DATE(t.tanggal) BETWEEN ? AND ?");
$qPC->bind_param("ss", $prevDari, $prevSampai); $qPC->execute();
$prevCogs    = (int)$qPC->get_result()->fetch_assoc()['cogs']; $qPC->close();
$prevRevenue = (int)$prevRow['revenue'];
$prevProfit  = $prevRevenue - $prevCogs;

function pct($now, $prev) {
    if ($prev == 0) return ['v'=>0,'d'=>'neutral'];
    $p = round((($now - $prev) / $prev) * 100, 1);
    return ['v'=>abs($p), 'd'=>$p>=0?'up':'down'];
}
$chgRevenue = pct($revenue, $prevRevenue);
$chgProfit  = pct($gross_profit, $prevProfit);
$chgTrx     = pct($total_trx, $prevRow['total_trx']);

// ── Chart harian ──
$qCh = $conn->prepare("SELECT DATE(tanggal) AS d, SUM(total) AS rev FROM transactions WHERE DATE(tanggal) BETWEEN ? AND ? GROUP BY DATE(tanggal) ORDER BY d");
$qCh->bind_param("ss", $dari, $sampai); $qCh->execute();
$chartRaw = $qCh->get_result()->fetch_all(MYSQLI_ASSOC); $qCh->close();

$qPCh = $conn->prepare("SELECT DATE(t.tanggal) AS d, SUM(t.total) AS rev, SUM(td.harga_beli_now*td.qty) AS cost FROM transactions t JOIN transaction_detail td ON td.id_transaction=t.id_transaction WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY DATE(t.tanggal) ORDER BY d");
$qPCh->bind_param("ss", $dari, $sampai); $qPCh->execute();
$profitChartRaw = $qPCh->get_result()->fetch_all(MYSQLI_ASSOC); $qPCh->close();

$chartDates = $chartRev = $chartProfit = [];
$cur = strtotime($dari); $end = strtotime($sampai);
while ($cur <= $end) {
    $d = date('Y-m-d', $cur);
    $chartDates[] = date('d/m', $cur);
    $rf = array_values(array_filter($chartRaw, fn($r) => $r['d']===$d));
    $pf = array_values(array_filter($profitChartRaw, fn($r) => $r['d']===$d));
    $r = $rf ? (int)$rf[0]['rev'] : 0;
    $c = $pf ? (int)$pf[0]['cost'] : 0;
    $chartRev[] = $r; $chartProfit[] = $r - $c;
    $cur = strtotime('+1 day', $cur);
}

// ── Top produk by profit ──
$qT = $conn->prepare("SELECT p.nama_product, SUM(td.qty) AS terjual, SUM((td.harga_jual-td.harga_beli_now)*td.qty) AS profit FROM transaction_detail td JOIN products p ON p.id_product=td.id_product JOIN transactions t ON t.id_transaction=td.id_transaction WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY p.id_product ORDER BY profit DESC LIMIT 5");
$qT->bind_param("ss", $dari, $sampai); $qT->execute();
$topProds = $qT->get_result()->fetch_all(MYSQLI_ASSOC); $qT->close();

// ── Kasir performance ──
$qK = $conn->prepare("SELECT u.username, COUNT(t.id_transaction) AS trx, SUM(t.total) AS revenue FROM transactions t JOIN users u ON u.id=t.id_user WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY t.id_user ORDER BY revenue DESC LIMIT 5");
$qK->bind_param("ss", $dari, $sampai); $qK->execute();
$kasirPerf = $qK->get_result()->fetch_all(MYSQLI_ASSOC); $qK->close();

// ── Recent transactions ──
$qR = $conn->prepare("SELECT t.id_transaction, t.tanggal, t.total, u.username FROM transactions t LEFT JOIN users u ON u.id=t.id_user ORDER BY t.tanggal DESC LIMIT 5");
$qR->execute();
$recentTrx = $qR->get_result()->fetch_all(MYSQLI_ASSOC); $qR->close();

// ── Helpers ──
function rp($n)  { return 'Rp '.number_format($n,0,',','.'); }
function rpS($n) {
    if ($n>=1000000000) return 'Rp '.number_format($n/1000000000,1,'.',',').'M';
    if ($n>=1000000)    return 'Rp '.number_format($n/1000000,1,'.',',').'Jt';
    if ($n>=1000)       return 'Rp '.number_format($n/1000,0,'.',',').'Rb';
    return 'Rp '.number_format($n,0,'.',',');
}
$jDates      = json_encode($chartDates);
$jRev        = json_encode($chartRev);
$jProfit     = json_encode($chartProfit);
$maxP        = !empty($topProds) ? max(array_column($topProds,'profit')) : 1;
$donutData   = json_encode([$revenue, $cogs, $gross_profit]);
$donutLabels = json_encode(['Revenue','COGS','Profit']);
$donutColors = json_encode(['#d9202a','#f59e0b','#4ade80']);
$avgProfit   = $total_trx > 0 ? round($gross_profit/$total_trx) : 0;
$avCols      = ['a','b','c'];
?>

<script src="asset/js/chart.js"></script>
<style>
:root {
  --bg:        #080608;
  --glass:     rgba(16,10,12,0.72);
  --border:    rgba(255,255,255,0.07);
  --border-hi: rgba(255,255,255,0.11);
  --red:       #d9202a;
  --red-light: #ff3b46;
  --red-dim:   rgba(217,32,42,0.15);
  --white:     #ffffff;
  --muted2:    #777;
  --green:     #4ade80;
  --amber:     #f59e0b;
  --indigo:    #818cf8;
  --font:      'Plus Jakarta Sans', sans-serif;
  --mono:      'DM Mono', monospace;
  --bebas:     'Bebas Neue', sans-serif;
  --r:         18px;
  --blur:      blur(28px) saturate(180%);
  --sh:        0 8px 32px rgba(0,0,0,0.55), 0 1px 0 rgba(255,255,255,0.05) inset;
}

.orb { position:fixed; border-radius:50%; filter:blur(90px); mix-blend-mode:screen; pointer-events:none; z-index:0; }
.o1  { width:650px; height:650px; background:radial-gradient(circle,rgba(200,20,35,.75)0%,rgba(150,10,20,.2)55%,transparent 70%); top:-200px; right:-150px; animation:oF 13s ease-in-out infinite alternate; }
.o2  { width:480px; height:480px; background:radial-gradient(circle,rgba(160,0,60,.50)0%,transparent 65%); bottom:-120px; left:-80px; animation:oF 16s ease-in-out infinite alternate-reverse; }
.o3  { width:300px; height:300px; background:radial-gradient(circle,rgba(217,32,42,.35)0%,transparent 65%); top:42%; left:32%; animation:oF 10s ease-in-out infinite alternate; }
@keyframes oF { 0%{transform:translate(0,0) scale(1)} 100%{transform:translate(-35px,25px) scale(1.1)} }

/* Card */
.card {
 /* 1. Menaikkan opacity agar lebih terang dari bg utama */
  background: rgba(34, 34, 34, 0.51) !important;
  backdrop-filter: blur(16px) saturate(180%);
  -webkit-backdrop-filter: blur(16px) saturate(180%);

  /* 2. Mempertegas border (Gunakan border putih transparan agar 'Pop') */
  border: 1px solid rgba(255, 255, 255, 0.12) !important;

  /* 3. Shadow lebih tebal agar card terlihat 'mengambang' */
  box-shadow:
    0 10px 40px -10px rgba(0, 0, 0, 0.7),
    inset 0 1px 1px rgba(255, 255, 255, 0.1); /* Inner glow di bagian atas */
  border-radius: 12px;
  transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
}
.card:hover,
.stat-card:hover {
  background: rgba(40, 40, 40, 0.6) !important;
  transform: translateY(-5px);
  border-color: rgba(217, 32, 42, 0.6) !important;
  box-shadow:
    0 20px 50px rgba(0, 0, 0, 0.8),
    0 0 15px rgba(217, 32, 42, 0.15);
}


/* Layout */
.page {
  position: relative; z-index:1;
  display: grid;
  /* Mencegah kolom kiri mengecil sampai di bawah 0, dan kolom kanan fleksibel antara 280px - 340px */
  grid-template-columns: minmax(0, 1fr) minmax(280px, 340px); 
  gap: 16px;
  margin: 0 auto;
  align-items: start;
  font-family: 'Jakarta', sans-serif;
}
.row-full { grid-column: 1 / -1; }
.left-col { display:flex; flex-direction:column; gap:14px; }
.right-panel { display:flex; flex-direction:column; gap:14px; }

/* Top bar */
.top-bar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; }
.top-bar h1 {
  font-family:var(--bebas); font-size:clamp(30px,3.5vw,46px); letter-spacing:2px; line-height:1;
  background:linear-gradient(135deg,#fff 30%,rgba(255,255,255,.45) 100%);
  -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.top-bar-sub { font-family:var(--mono); font-size:10px; color:var(--muted2); letter-spacing:.5px; margin-top:3px; }
.owner-badge {
  display:inline-flex; align-items:center; gap:7px;
  background:var(--red-dim); border:1px solid rgba(217,32,42,0.28);
  border-radius:30px; padding:5px 14px; width:fit-content; margin-bottom:6px;
  font-family:var(--mono); font-size:9px; color:var(--red-light); letter-spacing:2px;
}
.badge-dot { width:5px; height:5px; border-radius:50%; background:var(--red-light); box-shadow:0 0 7px var(--red-light); animation:pulse 2s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.3;transform:scale(.7)} }
.periode-pills { display:flex; gap:6px; flex-wrap:wrap; }
.pill {
  padding:6px 14px; border-radius:30px; border:1px solid var(--border);
  background:rgba(255,255,255,0.04); color:#ffffff; font-size:10px;
  font-family:var(--mono); cursor:pointer; text-decoration:none;
  transition:all .2s; white-space:nowrap; letter-spacing:.5px;
  box-shadow:
    0 10px 40px -10px rgba(0, 0, 0, 0.7),
    inset 0 1px 1px rgba(255, 255, 255, 0.1);
    font-family: 'Jakarta', sans-serif;
}
.pill:hover, .pill.active { background:var(--red-dim); border-color:rgba(217,32,42,.35); color:var(--red-light); }

/* Hero card */
.hero-card {
  padding: 30px 32px;
  background: linear-gradient(145deg,rgba(80,5,10,0.75)0%,rgba(20,4,6,0.90)100%);
  border-color: rgba(217,32,42,0.22);
  box-shadow: 0 12px 48px rgba(217,32,42,0.15), 0 1px 0 rgba(255,100,80,0.07) inset;
}
.hero-card::before {
  content:''; position:absolute; inset:0;
  background:
    radial-gradient(ellipse 80% 60% at 90% 50%, rgba(217,32,42,0.18)0%, transparent 60%),
    radial-gradient(ellipse 40% 50% at 10% 20%, rgba(255,80,80,0.08)0%, transparent 55%);
  pointer-events:none;
}
.hero-card::after { display:none; }
.hero-top    { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:20px; }
.hero-label  { font-family:var(--mono); font-size:9px; letter-spacing:2.5px; text-transform:uppercase; color:rgba(255,255,255,.35); margin-bottom:8px; }
.hero-profit { font-family: "Bebas", sans-serif; font-size:clamp(36px,5vw,58px); letter-spacing:1px; line-height:1; color:#6bffa0; text-shadow:0 0 40px rgba(74,222,128,0.3); }
.hero-sub    { font-family:var(--mono); font-size:11px; color:rgba(255,255,255,.35); margin-top:6px; }
.hero-ring-wrap  { width:80px; height:80px; position:relative; flex-shrink:0; }
.hero-ring-label { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-family:"Bebas", sans-serif; font-size:18px; color:var(--green); }
.hero-stats  { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-top:22px; padding-top:20px; border-top:1px solid rgba(255,255,255,0.07); }
.hsi-label   {  font-size:9px; letter-spacing:1.5px; color:rgba(255,255,255,.3); text-transform:uppercase; margin-bottom:5px; }
.hsi-val     { font-family:"Bebas", sans-serif; font-size:22px; letter-spacing:.5px; line-height:1; color: white; }
.hsi-chg     {  font-size:10px; margin-top:3px; color: var(--muted2); }
.up   { color:var(--green); }
.down { color:var(--red-light); }
.neu  { color:var(--muted2); }

/* Mini cards */
.mini-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
.mini-card { padding:16px 18px; }
.mini-icon { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; font-size:14px; }
.mini-lbl  { font-family:var(--mono); font-size:9px; letter-spacing:1.5px; text-transform:uppercase; color:var(--muted2); margin-bottom:6px; }
.mini-val  { font-family:"Bebas", sans-serif; font-size:20px; letter-spacing:.5px; line-height:1; }
.mini-sub  { font-family:var(--mono); font-size:9px; color:var(--muted2); margin-top:4px; }

/* Chart card */
.chart-card  { padding:24px 26px; }
.chart-hdr   { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:20px; }
.chart-title { font-size:13px; font-weight:700; }
.chart-sub   { font-family:var(--mono); font-size:10px; color:var(--muted2); margin-top:3px; }
.chip { background:rgba(255,255,255,0.05); border:1px solid var(--border); border-radius:20px; padding:3px 10px; font-family:var(--mono); font-size:9px; letter-spacing:1px; color:var(--muted2); }
.chart-wrap  { height:210px; position:relative; }
.chart-legend { display:flex; gap:16px; margin-top:14px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.05); }
.cl-item { display:flex; align-items:center; gap:6px; font-family:var(--mono); font-size:10px; color:var(--muted2); }
.cl-dot  { width:8px; height:8px; border-radius:2px; flex-shrink:0; }

/* Top products */
.toprod-card { padding:22px 26px; }
.toprod-item { display:flex; align-items:center; gap:12px; padding:9px 0; border-bottom:1px solid rgba(255,255,255,0.03); }
.toprod-item:last-child { border-bottom:none; }
.toprod-rank { width:26px; height:26px; border-radius:7px; background:rgba(255,255,255,.05); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; font-family:var(--mono); font-size:10px; color:var(--muted2); flex-shrink:0; }
.toprod-rank.g { background:rgba(74,222,128,.10); border-color:rgba(74,222,128,.22); color:var(--green); }
.toprod-name  { flex:1; font-size:11px; font-family:var(--mono); color:var(--muted2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.toprod-bw    { flex:1; height:3px; background:rgba(255,255,255,.06); border-radius:2px; overflow:hidden; }
.toprod-bar   { height:100%; background:linear-gradient(90deg,var(--green),#22c55e); border-radius:2px; transition:width 1s cubic-bezier(.4,0,.2,1); }
.toprod-val   { font-family:var(--mono); font-size:11px; color:var(--green); min-width:55px; text-align:right; }

/* Panel cards (right) */
.panel-card  { padding:22px 22px; }
.panel-title { font-size:13px; font-weight:700; margin-bottom:4px; color: white; }
.panel-sub   { font-family:var(--mono); font-size:9px; color:var(--muted2); letter-spacing:.5px; margin-bottom:16px; }
.trx-item { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.03); }
.trx-item:last-child { border-bottom:none; }
.trx-av { width:34px; height:34px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; text-transform:uppercase; }
.trx-av.a { background:linear-gradient(135deg,#d9202a,#8b0a14); border:1px solid rgba(217,32,42,.3); }
.trx-av.b { background:linear-gradient(135deg,#1e3a5f,#0d1f35); border:1px solid rgba(74,158,255,.2); }
.trx-av.c { background:linear-gradient(135deg,#14532d,#052e16); border:1px solid rgba(74,222,128,.2); }
.trx-name   { font-size:12px; font-weight:600; color:var(--white); }
.trx-time   { font-family:var(--mono); font-size:10px; color:var(--muted2); margin-top:2px; }
.trx-amount { margin-left:auto; font-family:var(--mono); font-size:12px; font-weight:600; color:var(--green); }
.donut-wrap  { height:150px; position:relative; margin:12px 0; }
.legend-grid { display:flex; flex-direction:column; gap:7px; margin-top:8px; }
.lg-item { display:flex; align-items:center; justify-content:space-between; }
.lg-left { display:flex; align-items:center; gap:7px; font-family:var(--mono); font-size:10px; color:var(--muted2); }
.lg-dot  { width:7px; height:7px; border-radius:2px; flex-shrink:0; }
.lg-val  { font-family:var(--mono); font-size:10px; color:var(--white); }
.footer-dashboard {
  font-size: 12px;
  color:#ffffff99;
  font-family: "Jakarta", sans-serif;
  margin-top: 1.5rem;
  margin-bottom: 1rem;
}
/* Animations */
.fade-in { opacity:0; transform:translateY(12px); animation:fadeUp .42s ease forwards; }
@keyframes fadeUp { to{opacity:1;transform:translateY(0)} }
.d1{animation-delay:.04s} .d2{animation-delay:.09s} .d3{animation-delay:.14s}
.d4{animation-delay:.19s} .d5{animation-delay:.26s}

/* Responsive */
@media(max-width:1050px) {
  .page { grid-template-columns:1fr; }
  .right-panel { display:grid; grid-template-columns:1fr 1fr; }
}
@media(max-width:680px) {
  .page { padding:16px 14px 36px; }
  .mini-grid { grid-template-columns:repeat(2,1fr); }
  .hero-stats { grid-template-columns:repeat(2,1fr); }
  .right-panel { grid-template-columns:1fr; }
}
</style>
</head>

<div class="orb o1"></div>
<div class="orb o2"></div>
<div class="orb o3"></div>

<div class="page">

  <!-- TOP BAR -->
  <div class="top-bar row-full fade-in d1">
    <div>
      <div class="owner-badge"><span class="badge-dot"></span>OWNER VIEW</div>
      <div class="top-bar-sub"><?= $dariLabel ?> — <?= $sampaiLabel ?></div>
    </div>
    <div class="periode-pills">
      <?php foreach(['today'=>'Hari Ini','week'=>'Minggu Ini','month'=>'Bulan Ini','year'=>'Tahun Ini'] as $k=>$v): ?>
      <a href="?page=owner-dashboard&periode=<?= $k ?>" class="pill <?= $periode===$k?'active':'' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- LEFT COLUMN -->
  <div class="left-col">

    <!-- Hero card -->
    <div class="card hero-card fade-in d2">
      <div class="hero-top">
        <div>
          <div class="hero-label">Gross Profit</div>
          <div class="hero-profit"><?= rp($gross_profit) ?></div>
          <div class="hero-sub">Revenue <?= rpS($revenue) ?> · COGS <?= rpS($cogs) ?></div>
        </div>
        <div class="hero-ring-wrap">
          <canvas id="heroRing" width="80" height="80"></canvas>
          <div class="hero-ring-label"><?= $profit_margin ?>%</div>
        </div>
      </div>
      <div class="hero-stats">
        <div>
          <div class="hsi-label">Total Transaksi</div>
          <div class="hsi-val"><?= number_format($total_trx) ?></div>
          <div class="hsi-chg <?= $chgTrx['d'] ?>"><?= $chgTrx['d']==='up'?'↑':'↓' ?> <?= $chgTrx['v'] ?>%</div>
        </div>
        <div>
          <div class="hsi-label">Avg / Transaksi</div>
          <div class="hsi-val" style="font-size:18px"><?= rpS((int)$avg_trx) ?></div>
          <div class="hsi-chg neu">periode ini</div>
        </div>
        <div>
          <div class="hsi-label">Profit Margin</div>
          <div class="hsi-val" style="color:var(--green)"><?= $profit_margin ?>%</div>
          <div class="hsi-chg <?= $chgProfit['d'] ?>"><?= $chgProfit['d']==='up'?'↑':'↓' ?> <?= $chgProfit['v'] ?>%</div>
        </div>
      </div>
    </div>

    <!-- 4 Mini cards -->
    <div class="mini-grid fade-in d3">
      <div class="card mini-card">
        <div class="mini-icon" style="background:rgba(217,32,42,0.12)">💰</div>
        <div class="mini-lbl">Revenue</div>
        <div class="mini-val" style="color:#ff8088"><?= rpS($revenue) ?></div>
        <div class="mini-sub <?= $chgRevenue['d'] ?>"><?= $chgRevenue['d']==='up'?'↑':'↓' ?> <?= $chgRevenue['v'] ?>%</div>
      </div>
      <div class="card mini-card">
        <div class="mini-icon" style="background:rgba(245,158,11,0.12)">📦</div>
        <div class="mini-lbl">HPP / COGS</div>
        <div class="mini-val" style="color:var(--amber)"><?= rpS($cogs) ?></div>
        <div class="mini-sub">Modal terjual</div>
      </div>
      <div class="card mini-card">
        <div class="mini-icon" style="background:rgba(129,140,248,0.12)">🏷️</div>
        <div class="mini-lbl">Total Diskon</div>
        <div class="mini-val" style="color:var(--indigo)"><?= rpS($total_diskon) ?></div>
        <div class="mini-sub">Diberikan</div>
      </div>
      <div class="card mini-card">
        <div class="mini-icon" style="background:rgba(74,222,128,0.10)">📈</div>
        <div class="mini-lbl">Profit / Trx</div>
        <div class="mini-val" style="color:var(--green)"><?= rpS($avgProfit) ?></div>
        <div class="mini-sub">Rata-rata</div>
      </div>
    </div>

    <!-- Chart -->
    <div class="card chart-card fade-in d4">
      <div class="chart-hdr">
        <div>
          <div class="chart-title" style="color: white;">Revenue vs Profit Harian</div>
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

    <!-- Top products -->
    <div class="card toprod-card fade-in d5">
      <div class="panel-title">Produk Paling Profitable</div>
      <div class="panel-sub">BERDASARKAN GROSS PROFIT</div>
      <?php if(empty($topProds)): ?>
      <div style="text-align:center;padding:24px;font-family:var(--mono);font-size:11px;color:var(--muted2)">Belum ada data</div>
      <?php else: foreach($topProds as $i=>$p): $pct=round($p['profit']/$maxP*100); ?>
      <div class="toprod-item">
        <div class="toprod-rank <?= $i===0?'g':'' ?>"><?= $i+1 ?></div>
        <div class="toprod-name"><?= htmlspecialchars($p['nama_product']) ?></div>
        <div class="toprod-bw"><div class="toprod-bar" style="width:<?= $pct ?>%"></div></div>
        <div class="toprod-val"><?= rpS($p['profit']) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </div>

  <!-- RIGHT PANEL -->
  <div class="right-panel">

    <!-- Recent transactions -->
    <div class="card panel-card fade-in d2">
      <div class="panel-title">Transaksi Terbaru</div>
      <div class="panel-sub">RECENT ACTIVITY</div>
      <?php if(empty($recentTrx)): ?>
      <div style="text-align:center;padding:24px;font-family:var(--mono);font-size:11px;color:var(--muted2)">Belum ada data</div>
      <?php else: foreach($recentTrx as $i=>$t): ?>
      <div class="trx-item">
        <div class="trx-av <?= $avCols[$i%3] ?>"><?= strtoupper(substr($t['username']??'?',0,1)) ?></div>
        <div>
          <div class="trx-name"><?= htmlspecialchars($t['username']??'Unknown') ?></div>
          <div class="trx-time">#<?= $t['id_transaction'] ?> · <?= date('H:i',strtotime($t['tanggal'])) ?></div>
        </div>
        <div class="trx-amount">+<?= rpS($t['total']) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Donut breakdown -->
    <div class="card panel-card fade-in d3">
      <div class="panel-title">Current Result</div>
      <div class="panel-sub">DISTRIBUSI KEUANGAN</div>
      <div class="donut-wrap"><canvas id="resultDonut"></canvas></div>
      <div class="legend-grid">
        <div class="lg-item"><div class="lg-left"><div class="lg-dot" style="background:#d9202a"></div>Revenue</div><div class="lg-val"><?= rpS($revenue) ?></div></div>
        <div class="lg-item"><div class="lg-left"><div class="lg-dot" style="background:#f59e0b"></div>COGS</div><div class="lg-val"><?= rpS($cogs) ?></div></div>
        <div class="lg-item"><div class="lg-left"><div class="lg-dot" style="background:#4ade80"></div>Profit</div><div class="lg-val" style="color:var(--green)"><?= rpS($gross_profit) ?></div></div>
      </div>
    </div>

    <!-- Kasir performance -->
    <div class="card panel-card fade-in d4">
      <div class="panel-title">Performa Kasir</div>
      <div class="panel-sub">REVENUE PER KASIR</div>
      <?php if(empty($kasirPerf)): ?>
      <div style="text-align:center;padding:24px;font-family:var(--mono);font-size:11px;color:var(--muted2)">Belum ada data</div>
      <?php else: foreach($kasirPerf as $i=>$k): ?>
      <div class="trx-item">
        <div class="trx-av <?= $avCols[$i%3] ?>"><?= strtoupper(substr($k['username'],0,1)) ?></div>
        <div>
          <div class="trx-name"><?= htmlspecialchars($k['username']) ?></div>
          <div class="trx-time"><?= $k['trx'] ?> transaksi</div>
        </div>
        <div class="trx-amount"><?= rpS($k['revenue']) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </div>

</div><!-- /.page -->
<footer style="text-align: center;">
  <p class="footer-dashboard">© 2026 M4HZTRO. All rights reserved.</p>
</footer>

<script>
Chart.defaults.color       = '#555';
Chart.defaults.font.family = "'DM Mono', monospace";
Chart.defaults.font.size   = 10;

/* Hero ring */
new Chart(document.getElementById('heroRing'), {
  type: 'doughnut',
  data: { datasets:[{ data:[<?= $profit_margin ?>,<?= 100-$profit_margin ?>], backgroundColor:['#4ade80','rgba(255,255,255,0.05)'], borderWidth:0 }] },
  options: { cutout:'72%', responsive:false, plugins:{legend:{display:false},tooltip:{enabled:false}} }
});

/* Main chart */
const mCtx = document.getElementById('mainChart').getContext('2d');
const gB = mCtx.createLinearGradient(0,0,0,210);
gB.addColorStop(0,'rgba(217,32,42,0.55)'); gB.addColorStop(1,'rgba(217,32,42,0.05)');
new Chart(mCtx, {
  type: 'bar',
  data: {
    labels: <?= $jDates ?>,
    datasets: [
      { type:'bar',  label:'Revenue', data:<?= $jRev ?>,    backgroundColor:gB, borderColor:'rgba(217,32,42,0.7)', borderWidth:1, borderRadius:5, borderSkipped:false },
      { type:'line', label:'Profit',  data:<?= $jProfit ?>, borderColor:'#4ade80', borderWidth:2, backgroundColor:'transparent', pointBackgroundColor:'#4ade80', pointBorderColor:'#080608', pointBorderWidth:2, pointRadius:3, tension:0.4, fill:false }
    ]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    interaction: { mode:'index', intersect:false },
    plugins: {
      legend: { display:false },
      tooltip: { backgroundColor:'#161618', borderColor:'rgba(217,32,42,0.3)', borderWidth:1, titleColor:'#666', bodyColor:'#fff', callbacks:{label:c=>' Rp '+c.parsed.y.toLocaleString('id-ID')} }
    },
    scales: {
      x: { grid:{color:'rgba(255,255,255,0.04)'}, ticks:{maxRotation:0} },
      y: { grid:{color:'rgba(255,255,255,0.04)'}, ticks:{callback:v=>v>=1e6?(v/1e6).toFixed(1)+'Jt':(v/1e3).toFixed(0)+'Rb'} }
    }
  }
});

/* Result donut */
new Chart(document.getElementById('resultDonut'), {
  type: 'doughnut',
  data: { labels:<?= $donutLabels ?>, datasets:[{ data:<?= $donutData ?>, backgroundColor:<?= $donutColors ?>, borderColor:'rgba(16,10,12,0.8)', borderWidth:3 }] },
  options: {
    responsive:true, maintainAspectRatio:false, cutout:'65%',
    plugins: {
      legend: { display:false },
      tooltip: { backgroundColor:'#161618', borderColor:'rgba(217,32,42,0.3)', borderWidth:1, titleColor:'#666', bodyColor:'#fff', callbacks:{label:c=>' Rp '+c.parsed.toLocaleString('id-ID')} }
    }
  }
});

/* Animate bars */
document.querySelectorAll('.toprod-bar').forEach(b => {
  const w = b.style.width; b.style.width = '0';
  setTimeout(() => b.style.width = w, 600);
});
</script>
