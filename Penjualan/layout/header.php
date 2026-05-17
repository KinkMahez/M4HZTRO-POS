<?php 
require_once 'config/database.php';
     
    $username = $conn->query("SELECT username FROM users WHERE id = " . $_SESSION['id'])->fetch_assoc()['username'];
    $role = $conn->query("SELECT role FROM users WHERE id = " . $_SESSION['id'])->fetch_assoc()['role'];
    // 1. Logika Halaman & Judul
    $current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard'; 
    $display_title = ucwords(str_replace('-', ' ', $current_page));
    
    // 2. Class Header Compact
    $header_class = ($current_page === 'dashboard' || $current_page === 'owner-dashboard') ? '' : 'header-compact';
    
?>
<header class="header <?= $header_class; ?>">
   <div class="header-left">

  <!-- Sidebar toggle — custom hamburger -->
  <button class="sidebar-toggle" id="toggleSidebar">
    <span></span>
    <span></span>
    <span></span>
  </button>

  <!-- Divider vertikal -->
  <div class="header-divider"></div>

  <!-- Title group -->
  <div class="title-group">
    <h1 class="page-name"><?= isset($title) ? $title : $display_title; ?></h1>
    <?php if ($current_page === 'dashboard' || $current_page === 'owner-dashboard'): ?>
      <p class="page-sub" id="current-date">Memuat tanggal...</p>
    <?php else: ?>
      <p class="page-sub">
        <span>M4HZTRO POS</span>
        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="page-sub-active"><?= isset($title) ? $title : $display_title; ?></span>
      </p>
    <?php endif; ?>
  </div>

</div>

    <div class="header-right">
        <button class="notif-btn">
            <img src="asset/img/ntf2.png" alt="">
        </button>

        <div class="profile-container">
            <div class="user-info-card">
                <img src="asset/img/PP7.png" alt="Avatar" class="avatar">
                <div class="user-text">
                    <span class="user-name"><?= $username ?></span>
                    <span class="user-role"><?= $role ?></span>
                </div>
                <div class="dropdown-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
            </div>

            <!-- MODAL DROPDOWN -->
            <div class="profile-dropdown">
                <a href="index.php?page=profile" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    Profile
                </a>
                <a href="auth/logout.php" class="dropdown-item logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Logout
                </a>
            </div>
        </div>
    </div>
</header>