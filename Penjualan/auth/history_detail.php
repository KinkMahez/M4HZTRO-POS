<?php
require_once __DIR__ . '/../config/database.php';


if (!isset($_GET['id_transaction'])) {
    echo json_encode(['error' => 'ID tidak ditemukan']);
    exit;
}

$id = intval($_GET['id_transaction']);

// Ambil info transaksi + user
$stmtTrx = $conn->prepare("
    SELECT t.*, u.username 
    FROM transactions t
    LEFT JOIN users u ON u.id = t.id_user
    WHERE t.id_transaction = ?
");
$stmtTrx->bind_param("i", $id);
$stmtTrx->execute();
$trx = $stmtTrx->get_result()->fetch_assoc();

if (!$trx) {
    echo json_encode(['error' => 'Transaksi tidak ditemukan']);
    exit;
}

// Ambil item detail + gambar produk
$stmtItems = $conn->prepare("
    SELECT td.qty, td.harga_jual, td.subtotal,
           p.nama_product, p.gambar
    FROM transaction_detail td
    LEFT JOIN products p ON p.id_product = td.id_product
    WHERE td.id_transaction = ?
");
$stmtItems->bind_param("i", $id);
$stmtItems->execute();
$items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'id'        => $trx['id_transaction'],
    'tanggal'   => $trx['tanggal'],
    'username'  => $trx['username'] ?? 'Admin',
    'total'     => $trx['total'],
    'bayar'     => $trx['bayar'],
    'kembalian' => $trx['kembalian'],
    'subtotal'  => $trx['subtotal'],
    'diskon_nominal' => $trx['diskon_nominal'],
    'items'     => $items
]);
?>