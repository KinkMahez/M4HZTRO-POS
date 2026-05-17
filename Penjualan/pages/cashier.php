<?php
// Koneksi Database
require_once 'config/database.php';

// Ambil data produk untuk ditampilkan dalam Grid
$products_query = mysqli_query($conn, "
    SELECT 
        p.id_product, 
        p.nama_product, 
        p.harga_jual,
        p.harga_beli, 
        p.stok, 
        p.barcode, 
        p.gambar, 
        c.nama_category 
    FROM products p
    LEFT JOIN categories c ON p.id_category = c.id_category 
    WHERE p.stok > 0
");
$query = "SELECT nama_category FROM categories GROUP BY nama_category ORDER BY nama_category ASC LIMIT 6";
$cat_btn = mysqli_query($conn, $query);
$products_json = [];
while ($row = mysqli_fetch_assoc($products_query)) {
    $products_json[] = $row;
}

$settings_query = mysqli_query($conn, "SELECT nama_toko, alamat FROM settings WHERE id = 1");
$settings = mysqli_fetch_assoc($settings_query);
$nama_toko = $settings['nama_toko'] ?? 'M4HZTRO';
$alamat = $settings['alamat'] ?? 'Jln Nanjung No.67';
?>
<link rel="stylesheet" href="asset/css/pages.css?v=<?= time(); ?>">

<div class="pos-wrapper">
    <!-- AREA KIRI: Grid Produk & Kategori -->
    <div class="pos-products-section">

        <div class="search-wrapper">
            <div class="pos-header">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="product-search" placeholder="Cari Produk atau Scan Barcode....." onkeyup="filterProducts()">
                </div>
            </div>
            <div class="category-tabs">
                <button class="cat-btn active" data-category="all">All</button>
                <?php foreach ($cat_btn as $s): ?>
                    <button class="cat-btn" data-category="<?= htmlspecialchars($s['nama_category']) ?>">
                        <?= htmlspecialchars($s['nama_category']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="products-grid" id="products-grid">
            <?php foreach ($products_json as $p): ?>
                <div class="product-card"
                    onclick="addToCart(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)"
                    data-category="<?= htmlspecialchars($p['nama_category']) ?>" data-barcode="<?= htmlspecialchars($p['barcode']) ?>">
                    <div class="card-img">
                        <img src="asset/img/products/<?= $p['gambar'] ?: 'placeholder.png' ?>"
                            onerror="this.src='asset/img/products/placeholder.png';" alt="<?= htmlspecialchars($p['nama_product']) ?>">
                        <span class="stock-tag">Stok: <?= $p['stok'] ?></span>
                    </div>
                    <div class="card-info">
                        <p class="p-name" title="<?= htmlspecialchars($p['nama_product']) ?>"><?= htmlspecialchars($p['nama_product']) ?></p>
                        <p class="p-category"><?= $p['nama_category'] ?></p>
                        <p class="p-price">Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- AREA KANAN: Keranjang & Pembayaran -->
    <div class="pos-cart-section">
        <div class="cart-header">
            <h3 style="color: white;">New Order</h3>
            <button class="btn-clear" onclick="clearCart()"><i class="fas fa-trash"></i></button>
        </div>

        <div class="cart-items-list" id="cart-list">
            <!-- Item muncul di sini -->
            <div class="empty-state"><img src="asset/img/x-cart.png" alt="">Empty</div>
        </div>

        <div class="cart-footer">
            <div class="summary-lines">
                <span>Subtotal</span>
                <span id="subtotal" style="color: #fff6f6;">Rp 0</span>
            </div>
            <div class="summary-line total">
                <span>Total</span>
                <span id="grand-total">Rp 0</span>
            </div>

            <button id="btn-checkout" class="btn-checkout" onclick="openPaymentModal()">
                Add Item
            </button>
        </div>
    </div>
</div>

<!-- MODAL PEMBAYARAN -->
<div id="modal-payment">
    <div class="modal-card">

        <div class="modal-header">
            <div class="icon-wrap">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21 7H3a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-1 12H4V9h16v10zm-3-5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM6 5h12a1 1 0 0 0 0-2H6a1 1 0 0 0 0 2z" />
                </svg>
            </div>
            <div>
                <h2>Pembayaran</h2>
                <p>Selesaikan transaksi pelanggan</p>
            </div>
        </div>

        <!-- SUMMARY BOX -->
        <div class="total-box">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#888;margin-bottom:6px;">
                <span>Subtotal</span>
                <span id="modal-subtotal-display">Rp 0</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#4ade80;margin-bottom:8px;">
                <span>Diskon</span>
                <span id="modal-diskon-display">Rp 0</span>
            </div>
            <div class="divider" style="margin-bottom: 0.5rem;"></div>
            <span class="label">TOTAL TAGIHAN</span>
            <div id="modal-grand-total">Rp 0</div>
        </div>

        <div class="divider" style="margin-bottom: 0.9rem;"></div>

        <!-- DISKON INPUT -->
        <label class="input-label">DISKON (%)</label>
        <div style="display:flex;gap:8px;margin-bottom:14px;">
            <input
                type="number"
                id="modal-diskon-input"
                placeholder="0"
                min="0"
                max="100"
                style="flex:1;"
                oninput="applyDiskon()">
        </div>

        <!-- UANG DITERIMA -->
        <label class="input-label">UANG DITERIMA</label>
        <input
            type="text"
            id="modal-pay-input"
            oninput="formatCurrencyInput(this);" placeholder="Rp0" />

        <!-- SHORTCUT BUTTONS -->
        <div id="shortcut-wrap" style="display:flex;gap:8px;margin-top:10px;margin-bottom:14px;"></div>

        <!-- KEMBALIAN -->
        <div class="change-box" id="change-box">
            <span class="change-label">Kembalian</span>
            <span id="modal-change-display">Rp 0</span>
        </div>

        <div class="btn-group">
            <button class="btn-cancel" onclick="closePaymentModal()">BATAL</button>
            <button
                id="btn-confirm-finish"
                onclick="finishTransaction()"
                disabled>
                KONFIRMASI →
            </button>
        </div>

    </div>
</div>

<div id="modal-receipt" style="display:none; position:fixed; inset:0; z-index:10000; background:rgba(0,0,0,0.75); backdrop-filter:blur(5px); justify-content:center; align-items:center;">
    <div style="background:#1c1c1e; border-radius:0px; padding:28px 24px; width:360px; max-height:95vh; overflow-y:auto; font-family:'Inter',sans-serif; color:#f2f2f7;">

        <!-- Header Struk -->
        <div style="text-align:center; border-bottom:1px dashed #3a3a3c; padding-bottom:16px; margin-bottom:16px;">
            <h2 style="font-size:2.4rem; font-weight:500; color:#fff; margin:0; font-family: 'Bebas', sans-serif;letter-spacing: 0.1rem;" id="store-name"><?= htmlspecialchars($nama_toko) ?></h2>
            <p style="color:#636366; font-size:0.75rem; margin:4px 0 0;" id="addres"><?= htmlspecialchars($alamat) ?></p>
            <p style="color:#636366; font-size:0.75rem; margin-top: 0.18rem;" id="receipt-date">-</p>
            <p style="color:#8e8e93; font-size:0.72rem; margin-top: 0.18rem;"> Kasir: <span id="receipt-cashier" style="color:#c0283a; font-weight:700;">Admin</span></p>
            <p style="color:#8e8e93; font-size:0.72rem; margin-top: 0.18rem;">No. Transaksi: <span id="receipt-id" style="color:#c0283a; font-weight:700;">#0000</span></p>
        </div>

        <!-- Item List -->
        <div id="receipt-items" style="margin-bottom:16px; "></div>

        <!-- Total -->
        <div style="border-top:1px dashed #3a3a3c; padding-top:14px; font-family: 'Jakarta', sans-serif;">
            <div style="display:flex; justify-content:space-between; color:#8e8e93; font-size:0.85rem; margin-bottom:6px;">
                <span>Subtotal</span><span id="receipt-subtotal">Rp 0</span>
            </div>
            <div id="receipt-diskon-row" style="display:none; justify-content:space-between; font-size:13px; color:#4ade80;">
                <span>Diskon</span>
                <span id="receipt-diskon"></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:500; font-size:0.92rem; color:#fff; margin-bottom:6px;">
                <span>Total</span><span id="receipt-total" style="color:#c0283a; font-weight: 550;">Rp 0</span>
            </div>
            <div style="display:flex; justify-content:space-between; color:#8e8e93; font-size:0.85rem; margin-bottom:4px;">
                <span>Bayar</span><span id="receipt-pay">Rp 0</span>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:500; font-size:0.92rem; ">
                <span>Kembalian</span><span id="receipt-change" style="color:#10b981; font-weight: 550;">Rp 0</span>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align:center; margin-top:16px; border-top:1px dashed #3a3a3c; padding-top:14px;">
            <p style="color:#636366; font-size:0.75rem;">Wear It Like a <span style="font-family: 'Noto', sans-serif;">MAESTRO</span></p>
        </div>

        <!-- Tombol -->
        <div style="display:grid; grid-template-columns:1fr 1.8fr; gap:10px; margin-top:20px;">
            <button onclick="closeReceiptModal()" style="padding:13px; border-radius:8px; border:none; background:#2c2c2e; color:#8e8e93; font-weight:600; cursor:pointer;">Tutup</button>
            <button onclick="printReceipt()" style="padding:13px; border-radius:8px; border:none; background:#c0283a; color:white; font-weight:600; cursor:pointer;">Cetak Struk</button>
        </div>
    </div>
</div>

<div id="print-area"></div>
<script src="asset/js/cashier.js?v=<?= time(); ?>"></script>

<script>
    // Ambil data dari PHP dan kirim ke fungsi JS
    const dataDariDatabase = <?php echo json_encode($products_json); ?>;
    setProductsData(dataDariDatabase);
</script>