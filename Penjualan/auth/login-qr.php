<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// 1. Cek apakah ada token
if (isset($_POST['token']) && !empty(trim($_POST['token']))) {
    $token = trim($_POST['token']); 

    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE qr_token = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        $_SESSION['login']    = true;
        $_SESSION['id']       = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        
        $_SESSION['success'] = "Access Granted! Welcome, " . $user['username'];

        header('Location: ../pages/login.php?mode=qr');
        exit;
    } else {
        $_SESSION['error'] = 'ID Card tidak valid';
        header('Location: ../pages/login.php?mode=qr');
        exit;
    }
} else {
    $_SESSION['error'] = 'ID QR tidak valid';
    header('Location: ../pages/login.php?mode=qr');
    exit;
}
