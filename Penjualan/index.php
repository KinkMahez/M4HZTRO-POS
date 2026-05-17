<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: pages/login.php");
    exit;
}
date_default_timezone_set('Asia/Jakarta');
$role = $_SESSION['role']; 
$page = $_GET['page'] ?? ($role === 'admin' && $role === 'owner' ? 'dashboard' : 'cashier');


// ── DEFINISI AKSES PER ROLE ──
$adminOnly = ['dashboard', 'category', 'restock', 'sales-report', 'history', 'add-product', 'user-management'];
$kasirOnly = ['cashier'];
$ownerOnly = ['owner-dashboard'];
$shared    = ['profile', 'data-product'];

if (in_array($page, $adminOnly) && $role !== 'admin' && $role !== 'owner') {
    header("Location: index.php?page=cashier");
    exit;
}

if (in_array($page, $ownerOnly) && $role !== 'owner') {
    header("Location: index.php?page=cashier");
    exit;
}

if (in_array($page, $kasirOnly) && $role !== 'kasir' && $role !== 'admin' && $role !== 'owner') {
    header("Location: index.php");
    exit;
}

// ── ROUTING ──
$pages = [
    // Admin only
    'dashboard'    => 'pages/dashboard.php',
    'data-product' => 'pages/data-product.php',
    'category'     => 'pages/category.php',
    'restock'      => 'pages/restock.php',
    'sales-report' => 'pages/sales-report.php',
    'history'      => 'pages/history.php',
    'add-product'  => 'pages/add-product.php',
    'user-management' => 'pages/user-management.php',

    // Kasir & admin
    'cashier'      => 'pages/cashier.php',

    // Owner
    'owner-dashboard' => 'pages/owner-dashboard.php',

    // Shared
    'profile'         => 'pages/profile.php',
];

$pageFile = $pages[$page] ?? null;

// Kalau halaman tidak ditemukan
if (!$pageFile || !file_exists($pageFile)) {
    $pageFile = $role === 'admin' ? 'pages/dashboard.php' : 'pages/cashier.php';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M 4 H Z T R O® POS</title>
    <link rel="icon" type="image/png" href="asset/img/brand-ico.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <!-- Hubungkan CSS Utama -->
    <link rel="stylesheet" href="asset/css/style.css?v=<?= time(); ?>">
    

    <style>
        body {
            margin: 0;
            display: flex;
            height: 100vh;
            overflow: hidden;
            background-color: #000000;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            background-color: #131313;
            color: black;
            transition: all 0.3s ease;
            border-top-left-radius: 10px;
            padding-left: 1rem;
            padding-right: 1rem;
            padding-top: 1rem;
        }
    </style>
</head>

<body>

    <!-- 1. Panggil Sidebar (Tetap ada, atau bisa disembunyikan juga jika mau) -->
    <?php include 'layout/sidebar.php'; ?>

    <!-- 2. Pembungkus untuk Header dan Isi Halaman -->
    <div class="main-wrapper">

        <!-- 3. LOGIKA HEADER: Sembunyikan jika halaman adalah cashier -->

        <?php include 'layout/header.php'; ?>


        <!-- 4. Area Konten Utama -->
        <main class="content-area">
            <?php

            if ($pageFile && file_exists($pageFile)) {
                include $pageFile;
            } else {
                echo "<div style='padding:20px;'><h1 style='color:black;'>Welcome to M4HZTRO</h1></div>";
            }
            ?>
        </main>
    </div>

    <!-- 5. Panggil JavaScript -->
    <script src="asset/js/layout.js?v=<?= time(); ?>"></script>
</body>

</html>