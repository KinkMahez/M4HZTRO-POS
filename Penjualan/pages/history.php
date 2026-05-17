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
  case 'all':
    default:
        $dari   = '1700-01-01';
        $sampai = date('Y-m-d');
}

$dariLabel   = date('d/m/Y', strtotime($dari));
$sampaiLabel = date('d/m/Y', strtotime($sampai));

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Logika Hapus Transaksi
if (isset($_GET['delete'])) {
    $id_to_delete = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM transaction_detail WHERE id_transaction = '$id_to_delete'");
    mysqli_query($conn, "DELETE FROM transactions WHERE id_transaction = '$id_to_delete'");
    echo "<script>window.location.href='index.php?page=history'</script>";
    $_SESSION['info'] = 'Riwayat berhasil dihapus!';
    exit;
}



// Ambil Data Transaksi
$query = "SELECT * FROM transactions WHERE DATE(tanggal) BETWEEN ? AND ? ORDER BY tanggal DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $dari, $sampai);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if (mysqli_num_rows($result) == 0) {
    echo "<!-- Debug: Tidak ada data dari tanggal $dari sampai $sampai -->";
}
$id = $_SESSION['id'];
?>

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="asset/css/pages.css?v=<?= time(); ?>">
</head>
<style>
    
.filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  background: #1f1e1e;
  border: 1px solid rgba(255, 255, 255, 0.03);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 15px;
  font-family: "Jakarta", sans-serif;
}
.filter-group {
  display: flex;
  align-items: center;
  gap: 8px;
}
.filter-label {
  font-size: 11px;
  color: #bbbbbb;
  letter-spacing: 0.05em;
  white-space: nowrap;
}
select.fs,
input.fs {
  background: #111;
  border: 0.5px solid #222;
  border-radius: 7px;
  padding: 7px 10px;
  color: #fff;
  font-size: 12px;
  outline: none;
  cursor: pointer;
}
input.fs {
  width: 130px;
  
}
select.fs:focus,
input.fs:focus {
  border-color: #e02020;
}
.filter-divider {
  width: 0.5px;
  height: 24px;
  background: #222;
}
.filter-right {
  margin-left: auto;
  display: flex;
  gap: 8px;
}
.btn-accept {
  background: #d72437;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 9px 14px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
}

.btn-accept:hover {
  background: #b81e2e;
}

.btn-outline {
  background: transparent;
  color: #888;
  border: 0.5px solid #2a2a2a;
  border-radius: 8px;
  padding: 9px 14px;
  font-size: 12px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
}
.btn-outline:hover {
  background: #1a1a1a;
  color: #fff;
}
.product-container h3 {
    font-family: 'Jakarta', sans-serif;
    color: #f0f0f0;
    margin-bottom: 12px;
    font-size: 1.1rem;
}
</style>

<?php if (isset($_SESSION['info'])): ?>
    <div class="toast-container">
        <div id="toast" class="toast-notification">
            <div class="toast-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="toast-message">
                <?= $_SESSION['info']; ?>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['info']); ?>
<?php endif; ?>

<div class="product-container">
    <h3>Total Transaksi: <?= mysqli_num_rows($result) ?></h3>
    <div class="history-header">
        <!-- Filter -->
<form method="GET" class="no-print">
  <input type="hidden" name="page" value="history">
  <div class="filter-bar">
    <div class="filter-group">
      <span class="filter-label">PERIODE</span>
      <select class="fs" name="periode" onchange="this.form.submit()">
        <option value="all" <?= $periode === 'all'  ? 'selected' : '' ?>>Semua</option>
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
      <a href="index.php?page=history" class="btn-outline">Reset</a>
      <button type="submit" class="btn-accept">Terapkan</button>
    </div>
  </div>
</form>
    </div>

    <div class="table-card">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>ID Transaksi</th>
                    <th>Subtotal</th>
                    <th>Diskon</th>
                    <th>Total</th>
                    <th>Bayar</th>
                    <th>Kembalian</th>
                    <th style="text-align: center;">IDK</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td class="trx-id"><?php echo $row['id_transaction']; ?></td>

                        <td class="price" style="font-family: 'Jakarta', sans-serif; ">Rp <?php echo number_format($row['subtotal'], 0, ',', '.'); ?></td>
                        <td class="discount" style="font-family: 'Jakarta', sans-serif; color: #3b82f6;"><?php echo (int) $row['diskon_persen']; ?>%</td>
                        <td class="price" style="font-family: 'Jakarta', sans-serif; ">Rp <?php echo number_format($row['total'], 0, ',', '.'); ?></td>
                        <td class="payment" style="font-family: 'Jakarta', sans-serif; color: #40ca73;">Rp <?php echo number_format($row['bayar'], 0, ',', '.'); ?></td>
                        <td class="change" style="font-family: 'Jakarta', sans-serif; color:#d72437; ">Rp <?php echo number_format($row['kembalian'], 0, ',', '.'); ?></td>
                        <td style="text-align: center;"><span class="badge-kasir"><?php echo strtoupper($row['id_user']); ?></span></td>
                        <td style="text-align: center;">
                            <button onclick="showDetail('<?php echo $row['id_transaction']; ?>')" class="btn-action btn-eye">
                                <img src="asset/img/eye.png" alt="">
                            </button>
                            <a href="index.php?page=history&delete=<?php echo $row['id_transaction']; ?>" class="btn-action btn-delete" onclick="return confirm('Hapus transaksi ini?')">
                                <img src="asset/img/trash-2.png" alt="">
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Detail -->
<div id="modalOverlayHs" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:999;align-items:center;justify-content:center;">
    <div style="width:440px;background:#161616;border:0.5px solid #2a2a2a;border-radius:16px;overflow:hidden;font-family:'Segoe UI',sans-serif;max-height:95vh;display:flex;flex-direction:column;">

        <!-- Header -->
        <div style="background:#1a1a1a;padding:18px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:0.5px solid #222;flex-shrink:0;">
            <div>
                <div style="font-size:10px;color:#555;letter-spacing:.08em;margin-bottom:3px;">DETAIL TRANSAKSI</div>
                <div style="font-size:15px;font-weight:600;color:#fff;" id="modalInvoice">Invoice #—</div>
            </div>
            <button onclick="closeModalDet()" style="background:#222;border:0.5px solid #2a2a2a;border-radius:8px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#666;font-size:16px;line-height:1;">×</button>
        </div>

        <!-- Info Cards -->
        <div style="padding:14px 20px;display:flex;gap:10px;border-bottom:0.5px solid #1e1e1e;flex-shrink:0;">
            <div style="flex:1;background:#111;border-radius:10px;padding:11px 12px;">
                <div style="font-size:10px;color:#444;letter-spacing:.06em;margin-bottom:4px;">TANGGAL</div>
                <div style="font-size:12px;color:#ccc;" id="modalTanggal">—</div>
                <div style="font-size:11px;color:#555;margin-top:2px;" id="modalJam">—</div>
            </div>
            <div style="flex:1;background:#111;border-radius:10px;padding:11px 12px;">
                <div style="font-size:10px;color:#444;letter-spacing:.06em;margin-bottom:4px;">KASIR</div>
                <div style="font-size:12px;color:#ccc;" id="modalKasir">—</div>
            </div>
            <div style="flex:1;background:#111;border-radius:10px;padding:11px 12px;">
                <div style="font-size:10px;color:#444;letter-spacing:.06em;margin-bottom:4px;">TOTAL ITEM</div>
                <div style="font-size:12px;color:#ccc;" id="modalJumlahItem">—</div>
            </div>
        </div>

        <!-- Item List (scrollable) -->
        <div style="padding:14px 20px;overflow-y:auto;flex:1;">
            <div style="font-size:10px;color:#444;letter-spacing:.08em;margin-bottom:10px;">ITEM PEMBELIAN</div>
            <div id="detailContent">
                <div style="text-align:center;padding:24px;color:#333;font-size:13px;">Memuat data...</div>
            </div>
        </div>

        <!-- Summary -->

        <div style="margin:0 20px;border-top:0.5px solid #222;padding:12px 0;flex-shrink:0;">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#f0f0f0;margin-bottom:7px;">
                <span>Subtotal</span>
                <span style="color:#f0f0f0;" id="modalSubtotal">—</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#555;margin-bottom:7px;">
                <span>Diskon</span>
                <span style="color:#e02020;" id="modalDiskon">—</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span style="font-size:14px;font-weight:600;color:#fff;">Total</span>
                <span style="font-size:14.1px;font-weight:700;color:#f0f0f0;" id="modalTotal">—</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#555;margin-bottom:7px;">
                <span>Bayar</span>
                <span style="color:#4ade80;" id="modalBayar">—</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#555;">
                <span>Kembali</span>
                <span style="color:#f0f0f0;" id="modalKembali">—</span>
            </div>
        </div>

        <!-- Footer -->
        <div style="padding:14px 20px;border-top:0.5px solid #1e1e1e;flex-shrink:0;">
            <button onclick="closeModalDet()" style="width:100%;background:transparent;color:#666;border:0.5px solid #2a2a2a;border-radius:8px;padding:10px;font-size:12px;cursor:pointer;">
                Tutup
            </button>
        </div>

    </div>
</div>

<script>
    function showDetail(id) {
        // Reset & tampilkan modal
        document.getElementById('modalOverlayHs').style.display = 'flex';
        document.getElementById('detailContent').innerHTML = '<div style="text-align:center;padding:24px;color:#333;font-size:13px;">Memuat data...</div>';
        document.getElementById('modalInvoice').textContent = 'Invoice #—';
        document.getElementById('modalTanggal').textContent = '—';
        document.getElementById('modalJam').textContent = '—';
        document.getElementById('modalKasir').textContent = '—';
        document.getElementById('modalJumlahItem').textContent = '—';
        document.getElementById('modalTotal').textContent = '—';
        document.getElementById('modalBayar').textContent = '—';
        document.getElementById('modalKembali').textContent = '—';
        document.getElementById('modalSubtotal').textContent = '—';
        document.getElementById('modalDiskon').textContent = '—';

        fetch(`auth/history_detail.php?id_transaction=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('detailContent').innerHTML = `<div style="text-align:center;padding:24px;color:#e02020;font-size:13px;">${data.error}</div>`;
                    return;
                }

                // Invoice
                document.getElementById('modalInvoice').textContent = `Invoice #${String(data.id).padStart(4,'0')}`;

                // Tanggal & jam
                const tgl = new Date(data.tanggal);
                document.getElementById('modalTanggal').textContent = tgl.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric'
                });
                document.getElementById('modalJam').textContent = tgl.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit'
                }) + ' WIB';

                // Info
                document.getElementById('modalKasir').textContent = data.username;
                document.getElementById('modalJumlahItem').textContent = `${data.items.length} produk`;

                // Summary
                document.getElementById('modalTotal').textContent = formatRp(data.total);
                document.getElementById('modalBayar').textContent = formatRp(data.bayar);
                document.getElementById('modalKembali').textContent = formatRp(data.kembalian);
                document.getElementById('modalSubtotal').textContent = formatRp(data.subtotal);
                document.getElementById('modalDiskon').textContent = formatRp("-" + data.diskon_nominal);
                // Items
                const imgBase = 'asset/img/products/'; // sesuaikan path folder gambar produk
                document.getElementById('detailContent').innerHTML = data.items.map(item => {
                    const imgSrc = item.gambar ?
                        `${imgBase}${item.gambar}` :
                        null;

                    const imgEl = imgSrc ?
                        `<img src="${imgSrc}" style="width:42px;height:42px;border-radius:8px;object-fit:cover;flex-shrink:0;">` :
                        `<div style="width:42px;height:42px;border-radius:8px;background:#2a0a0a;flex-shrink:0;"></div>`;

                    return `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#111;border-radius:10px;margin-bottom:8px;">
                  <div style="display:flex;align-items:center;gap:12px;min-width:0;">
                    ${imgEl}
                    <div style="min-width:0;">
                      <div style="font-size:13px;font-weight:500;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.nama_product ?? 'Produk'}</div>
                      <div style="font-size:11px;color:#555;margin-top:3px;">
                        Qty: ${item.qty} &nbsp;·&nbsp; ${formatRp(item.harga_jual)} / pcs
                      </div>
                    </div>
                  </div>
                  <div style="font-size:13px;font-weight:600;color:#e02020;flex-shrink:0;margin-left:12px;">${formatRp(item.subtotal)}</div>
                </div>`;
                }).join('');
            })
            .catch(() => {
                document.getElementById('detailContent').innerHTML = '<div style="text-align:center;padding:24px;color:#e02020;font-size:13px;">Gagal memuat data.</div>';
            });
    }

    function closeModalDet() {
        document.getElementById('modalOverlayHs').style.display = 'none';
    }

    function formatRp(n) {
        return 'Rp ' + parseInt(n).toLocaleString('id-ID');
    }

    function closeModalDet() {
        document.getElementById('modalOverlayHs').style.display = 'none';
    }
</script>
<script src="asset/js/products.js?v=<?= time(); ?>"></script>