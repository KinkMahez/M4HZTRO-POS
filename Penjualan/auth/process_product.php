<?php
session_start();
require_once __DIR__ . '/../config/database.php';


// --- LOGIKA DELETE ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // Ambil data gambar lama untuk dihapus secara fisik
    $get_img = mysqli_query($conn, "SELECT gambar FROM products WHERE id_product = '$id'");
    $img_data = mysqli_fetch_assoc($get_img);
    
    if (!empty($img_data['gambar']) && file_exists("../asset/img/products/" . $img_data['gambar'])) {
        unlink("../asset/img/products/" . $img_data['gambar']);
    }

    mysqli_query($conn, "DELETE FROM products WHERE id_product = '$id'");
    $_SESSION['info'] = 'Produk berhasil dihapus!';
    header("Location: ../index.php?page=data-product");
}

// --- LOGIKA UPDATE ---
if (isset($_POST['update_product'])) {
    $id       = $_POST['id_product'];
    $barcode  = $_POST['barcode'];
    $nama     = $_POST['nama_product'];
    $harga_beli_input    = $_POST['harga_beli'];
    $harga_jual_input = $_POST['harga_jual'];
    $kategori = $_POST['kategori_product'];

    $harga_beli_bersih = str_replace('.', '', $harga_beli_input);
    $harga_beli_final = (int)$harga_beli_bersih;

    $harga_jual_bersih = str_replace('.', '', $harga_jual_input);
    $harga_jual_final = (int)$harga_jual_bersih;

    $img_sql  = "";

    if ($_FILES['gambar']['name'] != "") {
        // 1. Hapus gambar lama dari folder sebelum ganti baru
        $get_old = mysqli_query($conn, "SELECT gambar FROM products WHERE id_product = '$id'");
        $old_data = mysqli_fetch_assoc($get_old);
        if (!empty($old_data['gambar']) && file_exists("../asset/img/products/" . $old_data['gambar'])) {
            unlink("../asset/img/products/" . $old_data['gambar']);
        }

        // 2. Upload gambar baru
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $new_name = $barcode . "_" . time() . "." . $ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], "../asset/img/products/" . $new_name);
        $img_sql = ", gambar = '$new_name'";
    }

    if ($harga_beli_final >= $harga_jual_final) {
        echo "<script>alert('Rugi! Harga jual harus lebih besar dari harga beli!');</script>
        <script>window.history.back();</script>
        ";
        exit;
    }

    // Eksekusi Query Update
    $sql = "UPDATE products SET 
            barcode      = '$barcode', 
            nama_product = '$nama', 
            harga_beli   = '$harga_beli_final',
            harga_jual   = '$harga_jual_final',
            id_category  = '$kategori' 
            $img_sql 
            WHERE id_product = '$id'";

    mysqli_query($conn, $sql);
    $_SESSION['info'] = 'Produk berhasil diperbarui!';
    header("Location: ../index.php?page=data-product");
}
?>