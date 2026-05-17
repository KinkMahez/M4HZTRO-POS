<?php
require_once 'config/database.php';

// Simpan restock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $produk_id  = intval($_POST['produk_id']);
    $jumlah     = intval($_POST['jumlah']);
    $supplier_id = $_POST['supplier_id'] ? intval($_POST['supplier_id']) : null;
    $catatan    = trim($_POST['catatan']);
    $user_id    = $_SESSION['id'];

    // Tambah stok produk
    $stmt = $conn->prepare("UPDATE products SET stok = stok + ? WHERE id_product = ?");
    $stmt->bind_param("ii", $jumlah, $produk_id);
    $stmt->execute();

    // Simpan riwayat restock
    $sup = $supplier_id ?? null;
    $stmt2 = $conn->prepare("INSERT INTO restock (id_product, jumlah, supplier_id, catatan, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt2->bind_param("iiisi", $produk_id, $jumlah, $sup, $catatan, $user_id);
    $stmt2->execute();

    echo "<script>window.location.href='index.php?page=restock'</script>";
    $_SESSION['info'] = 'Stock Produk berhasil ditambahkan!';
    exit;
}

// Tambah supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_supplier'])) {
    $nama   = trim($_POST['nama']);
    $kontak = trim($_POST['kontak']);
    $stmt = $conn->prepare("INSERT INTO supplier (nama, kontak) VALUES (?, ?)");
    $stmt->bind_param("ss", $nama, $kontak);
    $stmt->execute();
    echo "<script>window.location.href='index.php?page=restock'</script>";
    $_SESSION['info'] = 'Supplier berhasil ditambahkan!';
    exit;
}

// Ambil data
$products  = $conn->query("SELECT id_product, nama_product, stok FROM products ORDER BY nama_product");
$suppliers = $conn->query("SELECT * FROM supplier ORDER BY nama");

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchParam = "%$search%";

$stmt = $conn->prepare("
    SELECT r.id, r.jumlah, r.catatan, r.created_at,
           p.nama_product, 
           s.nama AS nama_supplier,
           u.username
    FROM restock r
    JOIN products p ON r.id_product = p.id_product
    LEFT JOIN supplier s ON r.supplier_id = s.id
    JOIN users u ON r.user_id = u.id
    WHERE p.nama_product LIKE ? OR s.nama LIKE ? OR u.username LIKE ?
    ORDER BY r.created_at DESC
    LIMIT 50
");
$stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
$stmt->execute();
$riwayat = $stmt->get_result();

// Summary
$totalHariIni = $conn->query("SELECT COALESCE(SUM(jumlah),0) as total FROM restock WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
$totalTrx     = $conn->query("SELECT COUNT(*) as total FROM restock WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
$stokRendah   = $conn->query("SELECT COUNT(*) as total FROM products WHERE stok <= 5")->fetch_assoc()['total'];
?>


<link rel="stylesheet" href="asset/css/pages.css?v=<?= time(); ?>">
<link rel="stylesheet" href="asset/css/restock.css?v=<?= time(); ?>">
<script type="text/javascript" src="[https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js](https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js)"></script>
<script type="text/javascript">
    (function() {
        emailjs.init("-maO7Qn8_jT_Z3GBp"); // Ganti dengan Public Key dari dashboard
    })();
</script>

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

<div class="grid">
    <!-- Form -->
    <div class="card-restock">
        <div class="card-title">Form Stok Masuk</div>
        <form method="POST" class="restock" id="restockForm">
            <label>Pilih Produk</label>
            <select name="produk_id" id="product-select" required>
                <option value="">Pilih Produk...</option>
                <?php while ($p = $products->fetch_assoc()): ?>
                    <option value="<?= $p['id_product'] ?>" data-nama_product="<?= htmlspecialchars($p['nama_product']) ?>">
                        <?= htmlspecialchars($p['nama_product']) ?> <span style="font-family: 'Jakarta', sans-serif; font-size: 12px; color: #888;">(Stok: <?= $p['stok'] ?>)</span>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Jumlah Tambahan</label>
            <input type="number" name="jumlah" placeholder="Contoh: 50" min="1" required>

            <label>Supplier</label>
            <select name="supplier_id" id="supplier-select">
                <option value="">Umum / Tanpa Supplier</option>
                <?php while ($s = $suppliers->fetch_assoc()): ?>
                    <option value="<?= $s['id'] ?>" data-email="<?= htmlspecialchars($s['kontak']) ?>"
                        data-nama="<?= htmlspecialchars($s['nama']) ?>"><?= htmlspecialchars($s['nama']) ?></option>
                <?php endwhile; ?>
            </select>

            <label>Catatan</label>
            <textarea name="catatan" placeholder="Opsional..."></textarea>

            <button type="submit" name="simpan" class="btn-save" id="submitBtn">Simpan Stok Masuk</button>
        </form>
    </div>

    <!-- Tabel Riwayat -->
    <div class="card-restock">
        <div class="riwayat-header">
            <div class="riwayat-title">Riwayat Restock Terakhir</div>

            <button class="btn-red" onclick="document.getElementById('modalSupplier').classList.add('active')">+ Tambah Supplier</button>
        </div>

        <div class="table-wrapper">
            <table class="t-restock">
                <thead>
                    <tr>
                        <th style="width:140px">TANGGAL</th>
                        <th>PRODUK</th>
                        <th style="width:70px">QTY</th>
                        <th>SUPPLIER</th>
                        <th style="width:80px">USER</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($riwayat->num_rows === 0): ?>
                        <tr style="position: relative;">
                            <td colspan="5" class="empty-state">Belum ada riwayat restock.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($r = $riwayat->fetch_assoc()): ?>
                            <tr>
                                <td style="font-size:12px;color:#555"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                                <td class="prod-name"><?= htmlspecialchars($r['nama_product']) ?></td>
                                <td><span class="badge-qty">+<?= $r['jumlah'] ?></span></td>
                                <td style="color:#777;font-size:12px"><?= htmlspecialchars($r['nama_supplier'] ?? 'Umum') ?></td>
                                <td style="font-size:12px;color:#777"><?= htmlspecialchars($r['username']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Supplier -->
<div class="modal-overlay" id="modalSupplier" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-supply">
        <div class="modal-supply-title">Tambah Supplier Baru</div>
        <form method="POST">
            <label>Nama Supplier</label>
            <input type="text" name="nama" placeholder="Contoh: PT. Sumber Sandang" style="margin-top: 0.8rem; margin-bottom: 0.5rem;" required>
            <label>Kontak</label>
            <input type="email" name="kontak" placeholder="Email Supplier" style="margin-top: 0.8rem;" required>
            <div class="modal-actions">
                <button type="submit" name="tambah_supplier" class="btn-save">Simpan</button>
                <button type="button" class="btn-outline" onclick="document.getElementById('modalSupplier').classList.remove('active')">Batal</button>
            </div>
        </form>
    </div>
</div>

<script src="asset/js/products.js?v=<?= time(); ?>"></script>


<!-- Pastikan script ini diletakkan sebelum tag tutup </body> -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js"></script>

<script type="text/javascript">
    (function() {
        // Ganti dengan Public Key EmailJS Anda
        emailjs.init("-maO7Qn8_jT_Z3GBp");
    })();

    const restockForm = document.getElementById('restockForm');
    const submitBtn = document.getElementById('submitBtn');
    const selectSupplier = document.getElementById('supplier-select');
    const selectProduct = document.getElementById('product-select');

    restockForm.addEventListener('submit', function(event) {
        // 1. Cek apakah ini submit "sungguhan" atau kiriman dari script
        if (restockForm.getAttribute('data-submitting') === 'true') {
            return; // Biarkan form terkirim ke PHP
        }

        // 2. Tahan submit awal untuk proses Email
        event.preventDefault();

        // Ambil data supplier
        const selectedOption = selectSupplier.options[selectSupplier.selectedIndex];
        const supplierEmail = selectedOption.getAttribute('data-email');
        const supplierNama = selectedOption.getAttribute('data-nama');
        const productName = selectProduct.options[selectProduct.selectedIndex].getAttribute('data-nama_product');

        // Fungsi untuk lanjut simpan ke database (PHP)
        const submitToDatabase = () => {
            // Tandai bahwa kita siap kirim ke database
            restockForm.setAttribute('data-submitting', 'true');

            // Tambahkan input hidden secara dinamis untuk memastikan $_POST['simpan'] terdeteksi di PHP
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'simpan';
            hiddenInput.value = '1';
            restockForm.appendChild(hiddenInput);

            // Kirim form secara resmi
            restockForm.submit();
        };

        // 3. Logika Pengiriman Email
        if (!supplierEmail || supplierEmail.trim() === "") {
            console.log("Tidak ada email supplier, langsung simpan ke DB...");
            submitToDatabase();
        } else {
            // Visual feedback
            submitBtn.disabled = true;
            submitBtn.innerText = "Mengirim Email...";

            const templateParams = {
                to_email: supplierEmail,
                supplier_name: supplierNama,
                product_name: productName,
                jumlah: restockForm.jumlah.value,
                catatan: restockForm.catatan.value || "-"
            };

            emailjs.send('service_r3ky1gb', 'template_t3ixkvv', templateParams)
                .then(() => {
                    console.log("Email berhasil terkirim!");
                    submitToDatabase();
                })
                .catch((error) => {
                    console.error("Gagal kirim email:", error);
                    // Tetap simpan ke database meskipun email gagal
                    if (confirm("Gagal kirim email. Tetap simpan ke Database?")) {
                        submitToDatabase();
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.innerText = "Simpan & Kirim Email";
                    }
                });
        }
    });
</script>