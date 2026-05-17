<?php
require_once 'config/database.php';

if (isset($_POST['add_product'])) {
    $barcode = trim($_POST['barcode']);
    $nama    = $_POST['nama_product'];
    $kategori = $_POST['kategori_product'];
    $harga_beli_input = $_POST['harga_beli'];
    $harga_jual_input = $_POST['harga_jual'];
    $stok    = $_POST['stok'];

    $harga_beli_bersih = str_replace('.', '', $harga_beli_input);
    $harga_jual_bersih = str_replace('.', '', $harga_jual_input);
    $harga_beli_final = (int)$harga_beli_bersih;
    $harga_jual_final = (int)$harga_jual_bersih;

    // --- LOGIKA FOTO ---
    $namaFile   = $_FILES['gambar']['name'];
    $ukuranFile = $_FILES['gambar']['size'];
    $error      = $_FILES['gambar']['error'];
    $tmpName    = $_FILES['gambar']['tmp_name'];

    // 1. Cek apakah ada gambar yang diupload
    if ($error === 4) {
        echo "<script>alert('Pilih gambar terlebih dahulu!');</script>";
        return false;
    }

    // 2. Cek apakah yang diupload adalah gambar
    $ekstensiGambarValid = ['jpg', 'jpeg', 'png', 'webp'];
    $ekstensiGambar      = explode('.', $namaFile);
    $ekstensiGambar      = strtolower(end($ekstensiGambar));

    if (!in_array($ekstensiGambar, $ekstensiGambarValid)) {
         echo "<script>alert('Masukan Gambar format PNG/JPG/JPEG/WEBP');</script>
        <script>window.history.back();</script>
        ";
        exit;
    }

    // 3. Cek ukuran (misal maksimal 2MB)
    if ($ukuranFile > 2000000) {
        echo "<script>alert('Ukuran file gambar terlalu besar!');</script>
        <script>window.history.back();</script>
        ";
        exit;
    }

    // 4. Generate nama baru (agar tidak bentrok)
    $namaFileBaru = $barcode . '.' . $ekstensiGambar;

    // 5. Pindahkan file ke folder tujuan
    move_uploaded_file($tmpName, 'asset/img/products/' . $namaFileBaru);

    $nama = mysqli_real_escape_string($conn, $_POST['nama_product']);

    $cek_barcode = $conn->query("SELECT * FROM products WHERE barcode='$barcode'");
    $cek_nama = $conn->query("SELECT * FROM products WHERE nama_product='$nama'");

    if ($cek_barcode->num_rows > 0) {
        echo "<script>alert('Barcode sudah terdaftar!');</script>
        <script>window.history.back();</script>
        ";
        exit;
    }
    if ($cek_nama->num_rows > 0) {
        echo "<script>alert('Nama produk sudah terdaftar!');</script>
        <script>window.history.back();</script>
        ";
        exit;
    }
    if ($harga_beli_final >= $harga_jual_final) {
        echo "<script>alert('Rugi! Harga jual harus lebih besar dari harga beli!');</script>
        <script>window.history.back();</script>
        ";
        exit;
    }
    $query = "INSERT INTO products (barcode, nama_product, id_category, harga_beli, harga_jual, stok, gambar) 
              VALUES ('$barcode', '$nama', '$kategori', '$harga_beli_final', '$harga_jual_final', '$stok', '$namaFileBaru')";

    if (mysqli_query($conn, $query)) {
        $_SESSION['info'] = 'Data produk berhasil ditambahkan!';
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

$suppliers = $conn->query("SELECT * FROM categories");
?>

<link rel="stylesheet" href="asset/css/pages.css?v=<?= time(); ?>">
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

<div id="productModal" class="add-product">
    <div class="modal-content-add-product">
        <form method="POST" action="" enctype="multipart/form-data" class="add-product-form">
            <div class="left-side">
                <div class="form-group">
                    <label>Gambar Produk</label>
                    <div class="upload-area">
                        <input type="file" name="gambar" id="imgInput" accept="image/*" required onchange="previewImageAdd(this)">
                        <div id="preview-box">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff3b3b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                <polyline points="17 8 12 3 7 8" />
                                <line x1="12" y1="3" x2="12" y2="15" />
                            </svg>
                            <span>Pilih Gambar</span>
                            <span style="font-size: 0.7rem;">Maks. 2MB</span>
                            <span style="font-size: 0.7rem;">PNG/JPG/JPEG/WEBP</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="right-side">
                <div class="form-group">
                    <label>Barcode</label>
                    <input type="text" name="barcode" id="barcode" required placeholder="Scan or Enter Barcode">
                </div>
                <div class="form-group">
                    <label>Nama Produk</label>
                    <input type="text" name="nama_product" id="nama_product" required placeholder="Nama Produk Lengkap">
                </div>
            <div class="form-group-row">
                <div class="form-group">
                    <label>Kategori</label>

                    <select name="kategori_product" id="kategori" required>
                        <option value="">Pilih Category...</option>
                        <?php while ($s = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $s['id_category'] ?>"><?= htmlspecialchars($s['nama_category']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                        <label>Stok <span style="color: #6b6a6ae8;">(Initial)</span></label>
                        <input type="number" name="stok" required>
                    </div>
            </div>

                <div class="form-group-row">
                    <div class="form-group">
                        <label>Harga Beli (IDR)</label>
                        <input type="text" name="harga_beli" required oninput="formatCurrencyInput(this)" placeholder="Rp 0" style="text-align: right;">
                    </div>

                    <div class="form-group">
                        <label>Harga Jual (IDR)</label>
                        <input type="text" name="harga_jual" required oninput="formatCurrencyInput(this)" placeholder="Rp 0" style="text-align: right;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_product" class="btn-save">Save Product</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script src="asset/js/products.js?v=<?= time(); ?>"></script>
<script>
    function formatCurrencyInput(input) {
        let value = input.value.replace(/[^0-9]/g, "");

        if (value !== "") {
            input.value = parseInt(value).toLocaleString("id-ID");
        } else {
            input.value = "";
        }
    }
</script>