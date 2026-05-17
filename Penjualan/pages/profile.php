<?php
require_once 'config/database.php';

$userId = $_SESSION['id'];

// ── UPDATE PROFIL ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
  $username = trim($_POST['username']);

  // Cek username sudah dipakai user lain
  $cek = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
  $cek->bind_param("si", $username, $userId);
  $cek->execute();
  if ($cek->get_result()->num_rows > 0) {
    $errorProfil = "Username sudah digunakan.";
  } else {
    $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
    $stmt->bind_param("si", $username, $userId);
    $stmt->execute();
    $_SESSION['username'] = $username;
    $successProfil = "Profil berhasil diperbarui.";
  }
}

// ── UPDATE PASSWORD ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
  $pwLama    = $_POST['pw_lama'];
  $pwBaru    = $_POST['pw_baru'];
  $pwConfirm = $_POST['pw_confirm'];

  $stmtPw = $conn->prepare("SELECT password FROM users WHERE id = ?");
  $stmtPw->bind_param("i", $userId);
  $stmtPw->execute();
  $userRow = $stmtPw->get_result()->fetch_assoc();

  if (!password_verify($pwLama, $userRow['password'])) {
    $errorPassword = "Password lama tidak sesuai.";
  } elseif (strlen($pwBaru) < 8) {
    $errorPassword = "Password baru minimal 8 karakter.";
  } elseif ($pwBaru !== $pwConfirm) {
    $errorPassword = "Konfirmasi password tidak cocok.";
  } else {
    $hash = password_hash($pwBaru, PASSWORD_DEFAULT);
    $stmtUp = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmtUp->bind_param("si", $hash, $userId);
    $stmtUp->execute();
    $successPassword = "Password berhasil diubah. Silakan login ulang.";
    // Logout setelah ganti password
    session_destroy();
    echo "<script>setTimeout(()=>window.location.href='index.php',2000)</script>";
  }
}

// ── AMBIL DATA USER ──
$stmtUser = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

// ── STAT AKTIVITAS ──
$stmtStat = $conn->prepare("
    SELECT 
        COUNT(id_transaction)      AS total_trx,
        COALESCE(SUM(total), 0)   AS total_rev
    FROM transactions
    WHERE id_user = ?
");
$stmtStat->bind_param("i", $userId);
$stmtStat->execute();
$stat = $stmtStat->get_result()->fetch_assoc();

$stmtToday = $conn->prepare("
    SELECT COUNT(id_transaction) AS hari_ini
    FROM transactions
    WHERE id_user = ? AND DATE(tanggal) = CURDATE()
");
$stmtToday->bind_param("i", $userId);
$stmtToday->execute();
$today = $stmtToday->get_result()->fetch_assoc();

// ── AKTIVITAS TERBARU ──
$stmtAkt = $conn->prepare("
    SELECT t.id_transaction, t.tanggal, t.total,
           GROUP_CONCAT(p.nama_product SEPARATOR ', ') AS produk
    FROM transactions t
    JOIN transaction_detail td ON td.id_transaction = t.id_transaction
    JOIN products p ON p.id_product = td.id_product
    WHERE t.id_user = ?
    GROUP BY t.id_transaction
    ORDER BY t.tanggal DESC
    LIMIT 5
");
$stmtAkt->bind_param("i", $userId);
$stmtAkt->execute();
$aktivitas = $stmtAkt->get_result()->fetch_all(MYSQLI_ASSOC);

function formatRp($n)
{
  if ($n >= 1000000) return 'Rp ' . number_format($n / 1000000, 2) . 'jt';
  if ($n >= 1000)    return 'Rp ' . number_format($n / 1000, 0) . 'rb';
  return 'Rp ' . number_format($n, 0);
}
function formatDate($d)
{
  return date('d M Y · H:i', strtotime($d)) . ' WIB';
}
$initials = strtoupper(substr($user['username'], 0, 2));
$section  = $_GET['section'] ?? 'profil';

// ── Handle save ──
$saveMsg = '';
$saveErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_whitelabel'])) {
  $nama_toko = trim($_POST['nama_toko'] ?? '');
  $alamat    = trim($_POST['alamat']    ?? '');

  if ($nama_toko === '') {
    $saveErr = 'Nama toko wajib diisi.';
  } else {
    $stmt = $conn->prepare("UPDATE settings SET nama_toko = ?, alamat = ? WHERE id = 1");
    $stmt->bind_param("ss", $nama_toko, $alamat);
    $stmt->execute();
    $stmt->close();

    $saveMsg = 'Pengaturan toko berhasil disimpan.'; // ← ini yang kurang
  }
}

// ── Fetch current values ──
$setting = ['nama_toko' => '', 'alamat' => ''];
$res = $conn->query("SELECT nama_toko, alamat FROM settings LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
  $setting = $row;
}
?>

<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="asset/css/profile.css?v=<?= time(); ?>">

<div class="layout">

  <!-- SIDEBAR -->
  <div class="profile-card-account">
    <div class="banner"><span class="banner-text">M<span style="color:#d72437">4</span>HZTRO</span></div>
    <div class="profile-body-account">
      <div class="avatar-account"><?= $initials ?></div>
      <div class="pname"><?= htmlspecialchars($user['username']) ?></div>
      <div class="prole">POINT OF SALE SYSTEM</div>
      <div class="role-badge"><?= strtoupper($user['role']) ?></div>

      <div class="divider"></div>

      <div class="stat-grid">
        <div class="stat-box">
          <div class="stat-box-val" style="color:var(--red)"><?= number_format($stat['total_trx']) ?></div>
          <div class="stat-box-lbl">TOTAL TRX</div>
        </div>
        <div class="stat-box">
          <div class="stat-box-val" style="color: #4ade80;"><?= formatRp($stat['total_rev']) ?></div>
          <div class="stat-box-lbl">REVENUE</div>
        </div>
        <div class="stat-box">
          <div class="stat-box-val" style="color: #fff"><?= $today['hari_ini'] ?></div>
          <div class="stat-box-lbl">HARI INI</div>
        </div>
        <div class="stat-box">
          <div class="stat-box-val" style="color: #e7c65b"><?= strtoupper($user['role']) === 'ADMIN' ? 'ADMIN' : 'KASIR' ?></div>
          <div class="stat-box-lbl">LEVEL</div>
        </div>
      </div>

      <div class="divider"></div>
<?php if($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'owner'): ?>
      <div class="menu-nav">
        <?php
        $navs = [
          ['profil',    'Info Profil',    '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
          ['password',  'Ganti Password', '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
          ['preferensi', 'Preferensi  Toko',     '<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>'],
          ['aktivitas', 'Aktivitas',      '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
        ];
        foreach ($navs as $n): ?>
          <a href="?page=profile&section=<?= $n[0] ?>" class="mnav <?= $section === $n[0] ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $n[2] ?></svg>
            <?= $n[1] ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if($_SESSION['role'] === 'kasir'): ?>
      <div class="menu-nav">
        <?php
        $navs = [
          ['profil',    'Info Profil',    '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
          ['password',  'Ganti Password', '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
          ['aktivitas', 'Aktivitas',      '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
        ];
        foreach ($navs as $n): ?>
          <a href="?page=profile&section=<?= $n[0] ?>" class="mnav <?= $section === $n[0] ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $n[2] ?></svg>
            <?= $n[1] ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- CONTENT -->
  <div class="right">

    <?php if ($section === 'profil'): ?>
      <div class="card">
        <div class="card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </svg>
          INFO PROFIL
        </div>

        <?php if (isset($successProfil)): ?>
          <div class="alert alert-success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <?= $successProfil ?>
          </div>
        <?php elseif (isset($errorProfil)): ?>
          <div class="alert alert-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <?= $errorProfil ?>
          </div>
        <?php endif; ?>

        <form method="POST">
          <div class="form-row">
            <div>
              <label class="field-label">USERNAME</label>
              <input class="field" type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>
            <div>
              <label class="field-label">ROLE</label>
              <input class="field" type="text" value="<?= htmlspecialchars($user['role']) ?>" disabled>
            </div>
          </div>
          <div style="display:flex;gap:8px;margin-top:6px">
            <button type="submit" name="update_profil" class="btn-red">Simpan Perubahan</button>
            <a href="?page=profile&section=profil" class="btn-outline" style="text-decoration:none;display:inline-flex;align-items:center">Reset</a>
          </div>
        </form>
      </div>

    <?php elseif ($section === 'password'): ?>
      <div class="card">
        <div class="card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
          GANTI PASSWORD
        </div>

        <?php if (isset($successPassword)): ?>
          <div class="alert alert-success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12" />
            </svg><?= $successPassword ?></div>
        <?php elseif (isset($errorPassword)): ?>
          <div class="alert alert-error"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
            </svg><?= $errorPassword ?></div>
        <?php endif; ?>

        <div class="alert alert-warn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
          </svg>
          Setelah ganti password, kamu akan otomatis logout.
        </div>

        <form method="POST">
          <div class="form-row full">
            <div>
              <label class="field-label">PASSWORD LAMA</label>
              <div class="pw-wrap">
                <input class="field" type="password" name="pw_lama" id="pwOld" required placeholder="Masukkan password lama">
                <button type="button" class="pw-toggle" onclick="togglePw('pwOld')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div>
              <label class="field-label">PASSWORD BARU</label>
              <div class="pw-wrap">
                <input class="field" type="password" name="pw_baru" id="pwNew" required placeholder="Min. 8 karakter" oninput="checkStr(this.value)">
                <button type="button" class="pw-toggle" onclick="togglePw('pwNew')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                </button>
              </div>
              <div class="str-bars">
                <div class="str-bar" id="sb1"></div>
                <div class="str-bar" id="sb2"></div>
                <div class="str-bar" id="sb3"></div>
                <div class="str-bar" id="sb4"></div>
              </div>
              <div class="field-hint" id="strLbl">Masukkan password baru</div>
            </div>
            <div>
              <label class="field-label">KONFIRMASI PASSWORD</label>
              <div class="pw-wrap">
                <input class="field" type="password" name="pw_confirm" id="pwCf" required placeholder="Ulangi password baru">
                <button type="button" class="pw-toggle" onclick="togglePw('pwCf')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
          <div style="display:flex;gap:8px;margin-top:6px">
            <button type="submit" name="update_password" class="btn-red">Update Password</button>
            <a href="?page=profile&section=password" class="btn-outline" style="text-decoration:none;display:inline-flex;align-items:center">Batal</a>
          </div>
        </form>
      </div>

    <?php elseif ($section === 'preferensi'): ?>

      <div class="card">
        <div class="card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
            <polyline points="9 22 9 12 15 12 15 22" />
          </svg>
          WHITE LABEL — IDENTITAS TOKO
        </div>

        <?php if ($saveMsg): ?>
          <div class="alert alert-success" style="margin-bottom:16px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <?= htmlspecialchars($saveMsg) ?>
          </div>
        <?php endif; ?>

        <?php if ($saveErr): ?>
          <div class="alert alert-error" style="margin-bottom:16px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <?= htmlspecialchars($saveErr) ?>
          </div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="save_whitelabel" value="1">

          <div class="form-row" style="margin-bottom:16px">

            <!-- Nama Toko -->
            <div>
              <label class="field-label">NAMA TOKO <span style="color:#ff3b46">*</span></label>
              <input
                type="text"
                name="nama_toko"
                class="field"
                placeholder="Contoh: M4HZTRO Store"
                maxlength="50"
                value="<?= htmlspecialchars($setting['nama_toko']) ?>"
                required>
              <div class="toggle-sub" style="margin-top:6px">
                Muncul sebagai header di receipt / struk transaksi
              </div>
            </div>

            <!-- Alamat -->
            <div>
              <label class="field-label">ALAMAT TOKO</label>
              <input
                type="text"
                name="alamat"
                class="field"
                placeholder="Contoh: Jl. Sudirman No. 10, Bandung"
                maxlength="150"
                value="<?= htmlspecialchars($setting['alamat']) ?>">
              <div class="toggle-sub" style="margin-top:6px">
                Muncul sebagai subheader di bawah nama toko pada receipt
              </div>
            </div>

          </div>

          <!-- Preview receipt -->
          <div class="field-label" style="margin-bottom:10px">PREVIEW RECEIPT</div>
          <div style="
      background: rgba(255,255,255,0.03);
      border: 1px dashed rgba(255,255,255,0.12);
      border-radius: 10px;
      padding: 20px;
      text-align: center;
      margin-bottom: 20px;
      font-family: 'DM Mono', monospace;
    ">
            <div id="prev-nama" style="font-size:15px;font-weight:700;color:#fff;letter-spacing:0.5px">
              <?= $setting['nama_toko'] ?: '— Nama Toko —' ?>
            </div>
            <div id="prev-alamat" style="font-size:11px;color:rgba(255,255,255,0.40);margin-top:4px">
              <?= $setting['alamat'] ?: '— Alamat —' ?>
            </div>
            <div style="margin:12px auto;width:60%;height:1px;background:rgba(255,255,255,0.10)"></div>
            <div style="font-size:10px;color:rgba(255,255,255,0.25)">
              #0001 &nbsp;·&nbsp; <?= date('d/m/Y H:i') ?> &nbsp;·&nbsp; Kasir: Admin
            </div>
          </div>

          <button type="submit" class="btn-red">Simpan Perubahan</button>
        </form>
      </div>

      <script>
        // Live preview saat mengetik
        document.querySelector('[name="nama_toko"]').addEventListener('input', function() {
          document.getElementById('prev-nama').textContent = this.value || '— Nama Toko —';
        });
        document.querySelector('[name="alamat"]').addEventListener('input', function() {
          document.getElementById('prev-alamat').textContent = this.value || '— Alamat —';
        });
      </script>

    <?php elseif ($section === 'aktivitas'): ?>
      <div class="card">
        <div class="card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
          </svg>
          AKTIVITAS TERAKHIR
        </div>

        <?php if (empty($aktivitas)): ?>
          <div style="text-align:center;padding:32px;color:var(--t3);font-size:13px">Belum ada aktivitas transaksi.</div>
        <?php else: ?>
          <?php foreach ($aktivitas as $a): ?>
            <div class="act-row">
              <div class="act-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                  <line x1="3" y1="6" x2="21" y2="6" />
                </svg>
              </div>
              <div style="flex:1">
                <div class="act-title">Transaksi #<?= str_pad($a['id_transaction'], 4, '0', STR_PAD_LEFT) ?></div>
                <div class="act-meta"><?= formatDate($a['tanggal']) ?> · <?= htmlspecialchars(mb_strimwidth($a['produk'], 0, 40, '...')) ?></div>
              </div>
              <div class="act-amt"><?= formatRp($a['total']) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
  function togglePw(id) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
  }

  function checkStr(pw) {
    let score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const cols = ['#d72437', '#ffaa00', '#4a9eff', '#4ade80'];
    const lbls = ['Lemah', 'Cukup', 'Kuat', 'Sangat Kuat'];
    [1, 2, 3, 4].forEach(i => {
      const b = document.getElementById('sb' + i);
      if (b) b.style.background = i <= score ? cols[score - 1] : 'var(--border)';
    });
    const l = document.getElementById('strLbl');
    if (l) {
      l.textContent = score > 0 ? lbls[score - 1] : 'Masukkan password baru';
      l.style.color = score > 0 ? cols[score - 1] : 'var(--t3)';
    }
  }
</script>