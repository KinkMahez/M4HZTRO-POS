<?php
session_start();

// 1. Hapus semua variabel session
$_SESSION = array();

// 2. Jika menggunakan cookie session, hapus juga cookie-nya
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Hancurkan session secara total
session_destroy();

// 4. Redirect ke login dengan pesan sukses (optional)
header("Location: ../pages/login.php?logout=success");
exit;