<?php
session_start();
$hasError = isset($_SESSION['error']);
$hasSuccess = isset($_SESSION['success']);
$role_for_js = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$mode = isset($_GET['mode']) && $_GET['mode'] === 'qr' ? 'qr' : 'default';
?>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>L O G I N</title>
    <link rel="stylesheet" href="../asset/css/style.css?v=<?= time(); ?>">
    <link rel="icon" type="image/png" href="../asset/img/brand-ico.png">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>

<body>
    <div class="container-wrap">

        <?php if ($mode === 'default'): ?>
            <div class="login-container">
                <h1 class="login-logo">M<span style="color: #D72437 ;">4</span>HZTRO</h1>
                <h2 class="h2-s">Sign In</h2>

                <?php if ($hasError): ?>
                    <div class="toast-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= $_SESSION['error']; ?></span>
                    </div>
                <?php unset($_SESSION['error']);
                endif; ?>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="toast-success" data-role="<?= $role_for_js ?>">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= $_SESSION['success']; ?></span>
                    </div>
                <?php unset($_SESSION['success']);
                endif; ?>
                <form action="../auth/login-control.php" class="login-form" method="post">
                    <label for="Username">Username <span style="color: #D72437 ;">*</span></label>
                    <div class="input-box" id="input-box" style="margin-bottom: 3rem;">
                        <i id="logoL" class="fa fa-user"></i>
                        <input type="text" id="Username" name="username" class="<?= $hasError ? 'input-error' : '' ?> <?= $hasSuccess ? 'input-success' : '' ?>" required placeholder="Enter Username">
                    </div>
                    <label for="Password">Password <span style="color: #D72437 ;">*</span></label>
                    <div class="input-box" id="input-box" style="margin-bottom: 3.5rem;">
                        <i id="logoL" class="fa fa-lock"></i>
                        <input type="password" id="Password" name="password" class="<?= $hasError ? 'input-error' : '' ?> <?= $hasSuccess ? 'input-success' : '' ?>" required placeholder="Enter Password">
                        <i id="togglePassword" class="fa-solid fa-eye"></i>
                    </div>
                    <button type="submit" id="btn-login">Sign In</button>
                    <div class="line-form"><span>or</span></div>
                    <a href="login.php?mode=qr" class="ID-btn"><img src="../asset/img/qr.png" alt="ID Card">Sign In with ID Card</a>
                </form>
                <p class="copyright">© m4hztro 2026 | all right reserved</p>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'qr'): ?>
            <div class="qr-container">
                <h1 class="login-logo">M<span style="color:#D72437">4</span>HZTRO</h1>
                <h2 class="h2-s">Sign In</h2>


                <?php if ($hasError): ?>
                    <div class="toast-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= $_SESSION['error'] ?></span>
                    </div>
                <?php unset($_SESSION['error']);
                endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="toast-success" data-role="<?= $role_for_js ?>">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= $_SESSION['success'] ?></span>
                    </div>
                <?php unset($_SESSION['success']);
                endif; ?>

                <!-- Scanner UI -->
                <div class="scanner-label">
                    <span class="scanner-dot" id="scannerDot"></span>
                    <span id="scannerStatus" style="font-family: 'Jakarta', sans-serif;">Scan your ID Card to log in</span>
                </div>

                <div class="scanner-box" id="scannerBox">
                    <!-- Sudut dekoratif -->
                    <div class="corner tl"></div>
                    <div class="corner tr"></div>
                    <div class="corner bl"></div>
                    <div class="corner br"></div>

                    <!-- Scan line animasi -->
                    <div class="scan-line" id="scanLine"></div>

                    <!-- Icon kartu di tengah -->
                    <div class="scanner-icon" id="scannerIcon">
                        <i class="fa-solid fa-id-card"></i>
                        <span style="font-family: 'Jakarta', sans-serif;">Scan ID Card</span>
                    </div>
                </div>

                <form action="../auth/login-qr.php" method="post" id="qrForm">
                    <input type="password" id="scannerInput" class="input-hidden"
                        name="token" autofocus autocomplete="off">
                </form>

                <div class="line-form"><span>or</span></div>
                <a href="login.php?mode=default" class="ID-btn">Sign In Manually</a>
                <p class="copyright">© m4hztro 2026 | all right reserved</p>
            </div>

            <script src="../asset/js/qr.js?v=<?= time(); ?>"></script>
        <?php endif; ?>

        <div class="brand-container">
            <img src="../asset/img/brand-logo.png" alt="logos" class="logo">
            <img src="../asset/img/m4hztro.png" alt="logos" class="brand-name">

            <!-- Tambahkan ini -->
            <div class="pos-badge">
                <span class="pos-line"></span>
                <span class="pos-text">POINT OF SALE SYSTEM</span>
                <span class="pos-line"></span>
            </div>
            <p class="pos-desc">Inventory · Transaksi · Laporan</p>
            <!-- Sampai sini -->

            <p><span class="line">- </span>Wear it like a <span style="font-family: 'Noto', serif;">MAESTRO</span><span class="line"> -</span></p>
            <div class="relative-container">
                <img src="../asset/img/Parental.jfif" alt="parental" class="parental">
            </div>
        </div>
    </div>

    <script>
        // Toggle password — hanya di mode default
        const toggle = document.getElementById("togglePassword");
        const password = document.getElementById("Password");

        if (toggle && password) {
            toggle.addEventListener("click", () => {
                const type = password.getAttribute("type") === "password" ? "text" : "password";
                password.setAttribute("type", type);
                toggle.classList.toggle("fa-eye");
                toggle.classList.toggle("fa-eye-slash");
            });
        }

        // Toast error
        document.addEventListener("DOMContentLoaded", () => {
            const toast = document.querySelector('.toast-error');
            if (!toast) return;
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100px)';
            }, 4000);
            setTimeout(() => {
                toast.remove();
            }, 4500);
        });

        // Toast success + redirect
        const successToast = document.querySelector('.toast-success');
        if (successToast) {
            const btnLogin = document.getElementById('btn-login');
            const userRole = successToast.getAttribute('data-role');
            const sound = new Audio('../asset/js/success.mp3');
            sound.play();

            if (btnLogin) {
                btnLogin.innerText = 'Redirecting...';
                setTimeout(() => {
                    btnLogin.innerText = 'Login Success!';
                }, 2500);
            }
            setTimeout(() => {
                successToast.style.opacity = '0';
                successToast.style.transform = 'translateX(40px)';
            }, 2500);
            setTimeout(() => {
                if (userRole === 'kasir') {
                    window.location.href = '../index.php?page=cashier';
                } 
                if (userRole === 'owner') {
                    window.location.href = '../index.php?page=owner-dashboard';
                }
                else {
                    // Default untuk admin atau lainnya
                    window.location.href = '../index.php?page=dashboard';
                }
            }, 3200);
        }

        // Input error cleanup
        const inputBoxes = document.querySelectorAll('.input-error');
        setTimeout(() => {
            inputBoxes.forEach(box => box.classList.remove('input-error'));
        }, 4000);

        const successInputBoxes = document.querySelectorAll('.input-success');
        setTimeout(() => {
            successInputBoxes.forEach(box => box.classList.remove('input-success'));
        }, 4000);
    </script>

</body>

</html>