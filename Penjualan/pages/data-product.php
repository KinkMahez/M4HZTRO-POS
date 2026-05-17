<?php
// Koneksi Database
require_once 'config/database.php';

// --- LOGIKA AMBIL DATA --- //
$result = mysqli_query($conn, "
    SELECT 
        p.id_product, 
        p.nama_product, 
        p.harga_beli,
        p.harga_jual, 
        p.stok, 
        p.barcode, 
        p.gambar, 
        p.id_category,
        c.nama_category 
    FROM products p
    LEFT JOIN categories c ON p.id_category = c.id_category 
    ORDER BY id_product DESC
");

$suppliers = $conn->query("SELECT * FROM categories");
$search = $_GET['search'] ?? $_GET['category'] ?? '';
$searchParam = "%$search%";
?>

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

<link rel="stylesheet" href="asset/css/pages.css?v=<?= time(); ?>">

<div class="product-container">
    <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" id="product-search" placeholder="Cari Data Produk...." onkeyup="filterProducts()" value="<?= htmlspecialchars($search) ?>">
    </div>

    <div class="table-card">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Gambar</th>
                    <th>Barcode</th>
                    <th>Nama Product</th>
                    <th>Kategori</th>
                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'owner'): ?>
                    <th>Harga Beli</th>
                    <?php endif; ?>
                    <th>Harga Jual</th>
                    <th style="text-align: center;">Stok</th>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <th style="text-align: center;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php $stok = (int)$row['stok']; ?>
                        <tr>
                            <td>
                                <img src="asset/img/products/<?= $row['gambar'] ?: 'placeholder.png' ?>"
                                    alt="Produk" class="product-thumb"
                                    onerror="this.src='asset/img/products/placeholder.png';" alt="<?= htmlspecialchars($row['nama_product']) ?>">
                            </td>
                            <td><code><?= $row['barcode'] ?></code></td>
                            <td class="product-name-cell"><?= htmlspecialchars($row['nama_product']) ?></td>
                            <td><?= htmlspecialchars($row['nama_category']) ?></td>
                            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'owner'): ?>
                            <td style="color: #d72437; font-family: 'Jakarta', sans-serif;">Rp <?= number_format($row['harga_beli'], 0, ',', '.') ?></td>
                             <?php endif; ?>
                            <td style="color: #40ca73; font-family: 'Jakarta', sans-serif;">Rp <?= number_format($row['harga_jual'], 0, ',', '.') ?></td>
                            <td class="stock-cell" style="text-align: center;">
                                <span class="badge <?= ($stok == 0) ? 'badge-empty' : (($stok < 10) ? 'badge-low' : 'badge-ok') ?>">
                                    <?= $stok ?>
                                </span>
                            </td>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <td style="text-align: center;">
                                    <button class="btn-action btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)">
                                        <img src="asset/img/edit.png" alt="">
                                    </button>

                                    <button class="btn-action btn-delete" onclick="confirmDelete(<?= $row['id_product'] ?>, '<?= addslashes($row['nama_product']) ?>')">
                                        <img src="asset/img/trash-2.png" alt="">
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 20px;">Belum ada produk.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL STRUCTURE -->
<div id="productModal" class="modal-edit">
    <div class="modal-content" style="background: #1c1c1e; padding: 25px; border-radius: 15px; width: 100%; max-width: 500px; color: white; font-weight: 400;">
        <h3 id="modalTitle" style="margin-bottom: 0.9rem; color: #d72437; text-align: center; font-family: 'Inter', sans-serif;">Add Product</h3>

        <form method="POST" action="auth/process_product.php" enctype="multipart/form-data">
            <input type="hidden" name="id_product" id="modal_id_product">

            <div class="form-group">
                <label>Product Image</label>
                <div class="upload-area" style="border: 2px dashed #555555; padding: 20px; text-align: center; border-radius: 10px; margin-bottom: 15px;">
                    <input type="file" name="gambar" id="imgInput" accept="image/*" onchange="previewImage(this)">
                    <div id="preview-box">
                        <span>Click to choose image</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Barcode</label>
                <input type="text" name="barcode" id="modal_barcode" required style="width: 100%; padding: 10px; margin-top: 5px;">
            </div>

            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="nama_product" id="modal_nama_product" required style="width: 100%; padding: 10px; margin-top: 5px;">
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="kategori_product" id="modal_kategori" required style="width: 100%; padding: 10px; margin-top: 5px; border-radius: 6px;">
                    <option value="">Pilih Category...</option>
                    <?php while ($s = $suppliers->fetch_assoc()): ?>
                        <option value="<?= $s['id_category'] ?>"><?= htmlspecialchars($s['nama_category']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group-row">
                <div class="form-group">
                <label>Buy Price (IDR)</label>
                <input type="text" name="harga_beli" id="modal_harga_beli" required style="width: 100%; padding: 10px; margin-top: 5px;" oninput="formatCurrencyInput(this)" placeholder="Rp 0">
            </div>
            <div class="form-group">
                <label>Sell Price (IDR)</label>
                <input type="text" name="harga_jual" id="modal_harga_jual" required style="width: 100%; padding: 10px; margin-top: 5px;" oninput="formatCurrencyInput(this)" placeholder="Rp 0">
            </div>
            </div>

            <div class="modal-footer" style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" name="add_product" id="btnSave" class="btn-save" style="flex: 1; padding: 12px; background: #d72437; color: white; border: none; border-radius: 8px; cursor: pointer; font-family: 'Jakarta', sans-serif; font-weight: 500;">Save Product</button>
                <button type="button" onclick="closeModalUpdate()" class="btnClose" style="flex: 1; padding: 12px; background: #3a3a3c;
  color: #cbcbcc; border: none; border-radius: 8px; cursor: pointer; font-family: 'Jakarta', sans-serif; font-weight: 500;">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script src="asset/js/products.js?v=<?= time(); ?>"></script>
<script>
    function formatCurrencyInput(input) {
        // Ambil angka murni
        let value = input.value.replace(/[^0-9]/g, "");

        // Tampilkan format ribuan di input
        if (value !== "") {
            input.value = parseInt(value).toLocaleString("id-ID");
        } else {
            input.value = "";
        }

    }
</script>