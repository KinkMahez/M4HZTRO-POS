 <script>
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.documentElement.classList.add('sidebar-will-collapse');
        }
</script>

<link rel="stylesheet" href="asset/css/style.css?v=<?= time(); ?>">

<aside class="sidebar" id="mySidebar">
    <div class="brand">
        <!-- Logo sebagai tombol toggle collapse -->
        <img src="asset/img/brand-ico.png" alt="Logo" id="logoToggle" style="cursor: pointer;">
        <h1>M<span style="color: var(--accent-red);">4</span>HZTRO</h1>
    </div>
    

    <nav>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <!-- Dashboard: Aktif jika page=dashboard ATAU page tidak ada sama sekali -->
            <?php $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard'; ?>
            <p>Main Menu</p>

            <a href="index.php?page=dashboard" class="menu-item <?= ($page == 'dashboard') ? 'active' : '' ?>">
                <div class="menu-content">
                    <img src="asset/img/dashboard.png" alt="">
                    <span class="menu-text">Dashboard</span>
                </div>
            </a>

            <!-- Transaction -->
            <div class="menu-item dropdown-trigger <?= in_array($page, ['cashier', 'history']) ? 'active' : '' ?>">
                <div class="menu-content">
                    <img src="asset/img/transaction.png" alt="">
                    <span class="menu-text">Transaction</span>
                </div>
                <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>
            <div class="submenu <?= in_array($page, ['cashier', 'history']) ? 'show' : '' ?>">
                <a href="index.php?page=cashier" class="sub-item <?= ($page == 'cashier') ? 'active' : '' ?>">
                    <img src="asset/img/cashier.png" alt=""> Cashier
                </a>
                <a href="index.php?page=history" class="sub-item <?= ($page == 'history') ? 'active' : '' ?>">
                    <img src="asset/img/history.png" alt=""> History
                </a>
            </div>

            <!-- Products -->
            <?php $prod_pages = ['data-product', 'add-product', 'category', 'restock']; ?>
            <div class="menu-item dropdown-trigger <?= in_array($page, $prod_pages) ? 'active' : '' ?>">
                <div class="menu-content">
                    <img src="asset/img/shirt.png" alt="">
                    <span class="menu-text">Products</span>
                </div>
                <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>
            <div class="submenu <?= in_array($page, $prod_pages) ? 'show' : '' ?>">
                 <a href="index.php?page=category" class="sub-item <?= ($page == 'category') ? 'active' : '' ?>">
                    <img src="asset/img/tabler_tags.png" alt=""> Category
                </a>
                 <a href="index.php?page=add-product" class="sub-item <?= ($page == 'add-product') ? 'active' : '' ?>">
                    <img src="asset/img/plus.png" alt=""> Add Product
                </a>
                <a href="index.php?page=data-product" class="sub-item <?= ($page == 'data-product') ? 'active' : '' ?>">
                    <img src="asset/img/list.png" alt=""> Product Data
                </a>
                <a href="index.php?page=restock" class="sub-item <?= ($page == 'restock') ? 'active' : '' ?>">
                    <img src="asset/img/tabler_rotate.png" alt=""> Restock
                </a>
                <!-- Tambahkan sub-item lainnya dengan pola yang sama -->
            </div>

            <!-- Sales Report: Aktif HANYA jika page=sales-report -->
            <a href="index.php?page=sales-report" class="menu-item <?= ($page == 'sales-report') ? 'active' : '' ?>">
                <div class="menu-content">
                    <img src="asset/img/rpt2.png" alt="">
                    <span class="menu-text">Sales Report</span>
                </div>
            </a>

            <a href="index.php?page=user-management" class="menu-item <?= ($page == 'user-management') ? 'active' : '' ?>">
                <div class="menu-content">
                    <img src="asset/img/user.png" alt="">
                    <span class="menu-text">User Management</span>
                </div>
            </a>
        <?php endif; ?>

        <?php if ($_SESSION['role'] === 'kasir'): ?>
            <p>Main Menu</p>
            <div class="menu-item dropdown-trigger <?= in_array($page, ['cashier', 'data-product']) ? 'active' : '' ?>">
                <div class="menu-content">
                    <img src="asset/img/cashierpp2.png" alt="">
                    <span class="menu-text">Cashier Menu</span>
                </div>
                <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>
            <div class="submenu <?= in_array($page, ['cashier', 'data-product']) ? 'show' : '' ?>">
                <a href="index.php?page=cashier" class="sub-item <?= ($page == 'cashier') ? 'active' : '' ?>">
                    <img src="asset/img/cashier.png" alt=""> Cashier
                </a>
                <a href="index.php?page=data-product" class="sub-item <?= ($page == 'data-product') ? 'active' : '' ?>">
                    <img src="asset/img/list.png" alt=""> Product Data
                </a>
            </div>
        <?php endif; ?>

         <?php if ($_SESSION['role'] === 'owner'): ?>
            <p>Main Menu</p>
            <div class="menu-item dropdown-trigger <?= in_array($page, ['owner-dashboard', 'data-product']) ? 'active' : '' ?>">
                <div class="menu-content">
                    <img src="asset/img/cashierpp2.png" alt="">
                    <span class="menu-text">Owner Menu</span>
                </div>
                <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>
            <div class="submenu <?= in_array($page, ['owner-dashboard', 'data-product']) ? 'show' : '' ?>">
                <a href="index.php?page=owner-dashboard" class="sub-item <?= ($page == 'owner-dashboard') ? 'active' : '' ?>">
                    <img src="asset/img/dashboard.png" alt=""> Dashboard
                </a>
                <a href="index.php?page=data-product" class="sub-item <?= ($page == 'data-product') ? 'active' : '' ?>">
                    <img src="asset/img/list.png" alt=""> Product Data
                </a>
            </div>
        <?php endif; ?>
    </nav>


</aside>