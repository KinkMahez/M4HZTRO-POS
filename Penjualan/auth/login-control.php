<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$username = mysqli_real_escape_string($conn, $_POST['username']);
$password = $_POST['password'];

$stmt = $conn->prepare("SELECT id, username, password, role, is_active FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    
    if ($user['is_active'] == 0) {
        $_SESSION['error'] = "Akun Anda dinonaktifkan! Silakan hubungi Admin.";
        header("Location: ../pages/login.php");
        exit;
    }

    
    if (password_verify($password, $user['password'])) {
        $_SESSION['login']    = true;
        $_SESSION['id']       = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        
        $_SESSION['success']  = 'Login berhasil! Welcome back, ' . $user['username'];
        
        
        header('Location: ../pages/login.php'); 
        exit;
    } else {
        // Jika password salah
        $_SESSION['error'] = "Password atau username salah!";
        header("Location: ../pages/login.php");
        exit;
    }
} else {
    // Jika username tidak ditemukan di database
    $_SESSION['error'] = "Password atau username salah!";
    header("Location: ../pages/login.php");
    exit;
}