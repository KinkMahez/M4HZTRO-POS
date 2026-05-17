<?php
ini_set('display_errors', 0);
error_reporting(0);
ob_start(); // Buffer semua output

session_start();
require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Keranjang kosong atau data tidak valid.']);
    exit;
}

$cart          = $input['cart'];
$subtotal      = (int)$input['subtotal'];
$diskon_persen = (float)$input['diskon_persen'];
$diskon_nominal= (int)$input['diskon_nominal'];
$total_bill    = (int)$input['total_bill'];    // setelah diskon
$pay_amount    = (int)$input['pay_amount'];
$change_amount = (int)$input['change_amount'];
$buy_price     = (int)$input['harga_beli'];     // total harga beli untuk menghitung profit
$id_user       = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
$username       = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';


mysqli_begin_transaction($conn);

try {
    // Simpan transaksi dengan kolom diskon
    $stmt_trans = mysqli_prepare($conn, "
        INSERT INTO transactions (tanggal, subtotal, total, bayar, kembalian, id_user, diskon_persen, diskon_nominal) 
        VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt_trans) throw new Exception("Gagal prepare transaksi: " . mysqli_error($conn));

    mysqli_stmt_bind_param($stmt_trans, "iiiiidi", 
        $subtotal, $total_bill, $pay_amount, $change_amount, $id_user, $diskon_persen, $diskon_nominal
    );
    if (!mysqli_stmt_execute($stmt_trans)) throw new Exception("Gagal simpan transaksi utama.");

    $transaction_id = mysqli_insert_id($conn);

    $stmt_update_stok   = mysqli_prepare($conn, "UPDATE products SET stok = stok - ? WHERE id_product = ?");
$stmt_insert_detail = mysqli_prepare($conn, "
    INSERT INTO transaction_detail 
        (id_transaction, id_product, qty, harga_beli_now, harga_jual, subtotal) 
    VALUES (?, ?, ?, ?, ?, ?)
");

foreach ($cart as $item) {
    $id_product = (int)$item['id_product'];
    $qty        = (int)$item['qty'];

    // Ambil harga_beli dan harga_jual dari DB — jangan percaya data client
    $res_check  = mysqli_query($conn, "
        SELECT stok, harga_beli, harga_jual, nama_product 
        FROM products 
        WHERE id_product = $id_product FOR UPDATE
    ");
    $product_db = mysqli_fetch_assoc($res_check);

    if (!$product_db) throw new Exception("Produk ID $id_product tidak ditemukan.");
    if ($product_db['stok'] < $qty) {
        throw new Exception("Stok tidak cukup untuk: " . $product_db['nama_product'] . " (Sisa: " . $product_db['stok'] . ")");
    }

    $harga_beli_item = (int)$product_db['harga_beli'];
    $harga_jual      = (int)$product_db['harga_jual'];
    $subtotal_item   = $harga_jual * $qty;

    mysqli_stmt_bind_param($stmt_update_stok, "ii", $qty, $id_product);
    if (!mysqli_stmt_execute($stmt_update_stok)) 
        throw new Exception("Gagal potong stok produk ID $id_product.");

    mysqli_stmt_bind_param($stmt_insert_detail, "iiiiii", 
        $transaction_id, $id_product, $qty, $harga_beli_item, $harga_jual, $subtotal_item);
    if (!mysqli_stmt_execute($stmt_insert_detail)) 
        throw new Exception("Gagal simpan detail: " . $product_db['nama_product']);
}

    mysqli_commit($conn);
    echo json_encode([
        'success' => true,
        'transaction_id' => $transaction_id,
        'receipt' => [
            'id'             => $transaction_id,
            'tanggal'        => date('d/m/Y H:i'),
            'items'          => $cart,
            'subtotal'       => $subtotal,
            'diskon_persen'  => $diskon_persen,
            'diskon_nominal' => $diskon_nominal,
            'total'          => $total_bill,
            'bayar'          => $pay_amount,
            'kembalian'      => $change_amount,
            'username'       => $username
        ]
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}