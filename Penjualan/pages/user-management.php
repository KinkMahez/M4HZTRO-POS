<?php
require_once 'config/database.php';

$errors  = [];
$success = false;
$old     = [];
$cek_id      = $_SESSION['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $old = $_POST;

  $username  = trim($_POST['username']  ?? '');
  $password  = trim($_POST['password']  ?? '');
  $confirm   = trim($_POST['confirm']   ?? '');
  $role      = $_POST['role']           ?? '';
  $is_active = isset($_POST['is_active']) ? 1 : 0;
  $qr_token  = !empty($_POST['qr_token']) ? trim($_POST['qr_token']) : bin2hex(random_bytes(16));

  // ── Validation ──
  if ($username === '')            $errors['username'] = 'Username wajib diisi.';
  elseif (strlen($username) < 3)   $errors['username'] = 'Username minimal 3 karakter.';
  elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username))
    $errors['username'] = 'Username hanya boleh huruf, angka, dan underscore.';

  if ($password === '')            $errors['password'] = 'Password wajib diisi.';
  elseif (strlen($password) < 6)   $errors['password'] = 'Password minimal 6 karakter.';

  if ($confirm !== $password)      $errors['confirm']  = 'Konfirmasi password tidak cocok.';

  if (!in_array($role, ['admin', 'kasir'])) $errors['role'] = 'Pilih role yang valid.';

  // ── Check duplicate username ──
  if (empty($errors['username'])) {
    $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $chk->bind_param("s", $username);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) $errors['username'] = 'Username sudah digunakan.';
    $chk->close();
  }

  // ── Insert ──
  if (empty($errors)) {
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt   = $conn->prepare(
      "INSERT INTO users (username, password, role, qr_token, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param("ssssi", $username, $hashed, $role, $qr_token, $is_active);

    if ($stmt->execute()) {
      $success = true;
      $old     = [];
    } else {
      $errors['db'] = 'Gagal menyimpan data: ' . $conn->error;
    }
    $stmt->close();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'toggle') {
  $id = intval($_POST['id'] ?? 0);

  if ($id > 0 && (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $id)) {
    $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
  }

  echo '<script>window.location.href="index.php?page=user-management&toggled=$id";</script>';
  exit;
}

$total_adminNew = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];

// ── Fetch existing users for the list ──
$userList = [];
$res = $conn->query("SELECT id, username, role, is_active, created_at FROM users WHERE id != '$cek_id' AND role != 'owner' ORDER BY created_at  DESC LIMIT 20");
if ($res) $userList = $res->fetch_all(MYSQLI_ASSOC);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="asset/css/user_management.css?v=<?= time(); ?>">
</head>

<div class="page">
  <!-- ============================================================
       LEFT — FORM CARD
       ============================================================ -->
  <div class="card form-card fade-in d2">

    <div class="form-card-header">
      <h2 style="color:#fff">Tambah User Baru</h2>
      <p>ADMIN · USER MANAGEMENT · <?= date('d/m/Y') ?></p>
    </div>

    <!-- Avatar preview -->
    <div class="avatar-preview" id="avatarPreview">
      <div class="avatar-circle" id="avatarCircle">?</div>
      <div class="avatar-info">
        <p id="previewName" style="color:var(--muted2)">Belum ada username</p>
        <p><span class="role-badge empty" id="previewRole">— pilih role —</span></p>
      </div>
    </div>

    <!-- Success alert -->
    <?php if ($success): ?>
      <div class="alert alert-success">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12" />
        </svg>
        <div>User berhasil ditambahkan! Akun siap digunakan.</div>
      </div>
    <?php endif; ?>

    <!-- DB error -->
    <?php if (!empty($errors['db'])): ?>
      <div class="alert alert-error">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <div><?= htmlspecialchars($errors['db']) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate id="userForm">

      <!-- Username -->
      <div class="form-group">
        <label>Username <span class="req">*</span></label>
        <div class="input-wrap">
          <span class="icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
          </span>
          <input type="text" name="username" id="username"
            placeholder="Contoh: kasir_toko1"
            value="<?= htmlspecialchars($old['username'] ?? '') ?>"
            class="<?= isset($errors['username']) ? 'is-error' : '' ?>"
            autocomplete="off">
        </div>
        <?php if (isset($errors['username'])): ?>
          <div class="field-error">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <?= htmlspecialchars($errors['username']) ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Password -->
      <div class="form-group">
        <label>Password <span class="req">*</span></label>
        <div class="input-wrap">
          <span class="icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" />
              <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
          </span>
          <input type="password" name="password" id="password"
            placeholder="Minimal 6 karakter"
            class="<?= isset($errors['password']) ? 'is-error' : '' ?>">
          <button type="button" class="pw-toggle" onclick="togglePw('password','eyeA')">
            <svg id="eyeA" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </button>
        </div>
        <div class="pw-strength">
          <div class="pw-bars">
            <div class="pw-bar" id="bar1"></div>
            <div class="pw-bar" id="bar2"></div>
            <div class="pw-bar" id="bar3"></div>
            <div class="pw-bar" id="bar4"></div>
          </div>
          <div class="pw-label" id="pwLabel">— masukkan password</div>
        </div>
        <?php if (isset($errors['password'])): ?>
          <div class="field-error">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <?= htmlspecialchars($errors['password']) ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Confirm password -->
      <div class="form-group">
        <label>Konfirmasi Password <span class="req">*</span></label>
        <div class="input-wrap">
          <span class="icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            </svg>
          </span>
          <input type="password" name="confirm" id="confirm"
            placeholder="Ulangi password"
            class="<?= isset($errors['confirm']) ? 'is-error' : '' ?>">
          <button type="button" class="pw-toggle" onclick="togglePw('confirm','eyeB')">
            <svg id="eyeB" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </button>
        </div>
        <div id="matchHint" style="font-family:var(--mono);font-size:10px;margin-top:6px;display:none"></div>
        <?php if (isset($errors['confirm'])): ?>
          <div class="field-error">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <?= htmlspecialchars($errors['confirm']) ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Role -->
      <div class="form-group">
        <label>Role <span class="req">*</span></label>
        <div class="input-wrap">
          <span class="icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </span>
          <select name="role" id="role"
            class="<?= isset($errors['role']) ? 'is-error' : '' ?>">
            <option value="" disabled <?= empty($old['role']) ? 'selected' : '' ?>>— Pilih Role —</option>
            <option value="admin" <?= ($old['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="kasir" <?= ($old['role'] ?? '') === 'kasir' ? 'selected' : '' ?>>Kasir</option>
          </select>
        </div>
        <?php if (isset($errors['role'])): ?>
          <div class="field-error">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <?= htmlspecialchars($errors['role']) ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- QR Token -->
      <div class="form-group">
        <label>QR Token <span style="color:var(--muted);font-size:8px;letter-spacing:1px">(opsional)</span></label>
        <div class="input-wrap">
          <span class="icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7" />
              <rect x="14" y="3" width="7" height="7" />
              <rect x="3" y="14" width="7" height="7" />
              <rect x="14" y="14" width="3" height="3" />
            </svg>
          </span>
          <input type="text" name="qr_token" id="qr_token"
            placeholder="Otomatis digenerate jika kosong"
            value="<?= htmlspecialchars($old['qr_token'] ?? '') ?>"
            autocomplete="off">
        </div>
        <div class="qr-hint">Biarkan kosong untuk generate token unik secara otomatis</div>
      </div>

      <!-- Is Active toggle -->
      <label class="toggle-row" for="is_active">
        <div class="toggle-row-info">
          <p style="color: white;">Status Akun</p>
          <p>Aktifkan akun agar dapat digunakan langsung</p>
        </div>
        <div class="toggle-switch">
          <input type="checkbox" name="is_active" id="is_active"
            <?= !empty($old['is_active']) || empty($old) ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
        </div>
      </label>

      <!-- Submit -->
      <button type="submit" class="btn-submit">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <line x1="19" y1="8" x2="19" y2="14" />
          <line x1="22" y1="11" x2="16" y2="11" />
        </svg>
        Tambah User
      </button>

    </form>
  </div><!-- /.form-card -->

  <!-- ============================================================
       RIGHT COLUMN
       ============================================================ -->
  <div class="right-col">

    <!-- Mini stats -->
    <?php
    $totalUsers  = count($userList);
    $totalAdmin  = count(array_filter($userList, fn($u) => $u['role'] === 'admin'));
    $totalKasir  = count(array_filter($userList, fn($u) => $u['role'] === 'kasir'));
    $totalActive = count(array_filter($userList, fn($u) => $u['is_active'] == 1));
    ?>
    <div class="mini-stats fade-in d3">
      <div class="card mini-card red">
        <div class="num"><?= $total_adminNew ?></div>
        <div class="lbl">Admin</div>
      </div>
      <div class="card mini-card green">
        <div class="num"><?= $totalKasir ?></div>
        <div class="lbl">Kasir</div>
      </div>
      <div class="card mini-card amber">
        <div class="num"><?= $totalActive ?></div>
        <div class="lbl">Aktif</div>
      </div>
    </div>

    <!-- User list -->
    <div class="card list-card fade-in d4">
      <div class="list-header">
        <div>
          <h2 style="color:white">Daftar User</h2>
          <p>20 TERBARU · SEMUA ROLE</p>
        </div>
        <span class="count-chip"><?= $totalUsers ?> user</span>
      </div>

      <!-- Search (client-side filter) -->
      <div class="search-wrap">
        <span class="icon">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
        </span>
        <input type="text" id="searchInput" placeholder="Cari username…" oninput="filterUsers()">
      </div>

      <?php if (empty($userList)): ?>
        <div class="empty-state">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </svg><br>
          Belum ada user terdaftar
        </div>
      <?php else: ?>
        <table class="user-table" id="userTable">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Status</th>
              <th>Tgl Buat</th>
              <th>Aktif/Nonaktifkan</th>
            </tr>
          </thead>
          <tbody id="userTbody">
            <?php foreach ($userList as $u): ?>
              <tr class="user-row" data-name="<?= strtolower($u['username']) ?>">
                <td>
                  <div class="user-name-cell">
                    <div class="user-avatar-sm <?= $u['role'] === 'kasir' ? 'kasir' : '' ?>">
                      <?= strtoupper(substr($u['username'], 0, 1)) ?>
                    </div>
                    <?= htmlspecialchars($u['username']) ?>
                  </div>
                </td>
                <td>
                  <span class="role-badge <?= $u['role'] ?>">
                    <?= ucfirst($u['role']) ?>
                  </span>
                </td>
                <td>
                  <span class="status-pill <?= $u['is_active'] ? 'active' : 'inactive' ?>">
                    <span class="status-dot"></span>
                    <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                  </span>
                </td>
                <td style="font-family:var(--mono);font-size:10px">
                  <?= date('d/m/y', strtotime($u['created_at'])) ?>
                </td>
                <td>
                  <form method="POST" action="index.php?page=user-management&action=toggle" class="toggle-form">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <label class="row-toggle" title="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> akun">
                      <div class="toggle-switch">
                        <input type="checkbox"
                          name="is_active"
                          <?= $u['is_active'] ? 'checked' : '' ?>
                          onchange="this.closest('form').submit()">
                        <span class="toggle-slider"></span>
                      </div>
                    </label>
                  </form>
                </td>
              <?php endforeach; ?>
          </tbody>
        </table>
        <div id="emptySearch" class="empty-state" style="display:none">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
            <line x1="8" y1="11" x2="14" y2="11" />
          </svg><br>
          Username tidak ditemukan
        </div>
      <?php endif; ?>
    </div><!-- /.list-card -->

  </div><!-- /.right-col -->

</div><!-- /.page -->

<script>
  /* ── Avatar live preview ── */
  const usernameEl = document.getElementById('username');
  const roleEl = document.getElementById('role');
  const circle = document.getElementById('avatarCircle');
  const pName = document.getElementById('previewName');
  const pRole = document.getElementById('previewRole');

  function updatePreview() {
    const u = usernameEl.value.trim();
    const r = roleEl.value;

    circle.textContent = u ? u[0].toUpperCase() : '?';
    pName.textContent = u || 'Belum ada username';
    pName.style.color = u ? 'var(--white)' : 'var(--muted2)';

    pRole.textContent = r ? r.charAt(0).toUpperCase() + r.slice(1) : '— pilih role —';
    pRole.className = 'role-badge ' + (r || 'empty');

    if (r === 'kasir') {
      circle.style.background = 'linear-gradient(135deg,#14532d 0%,#166534 100%)';
      circle.style.borderColor = 'rgba(74,222,128,0.3)';
      circle.style.boxShadow = '0 4px 16px rgba(74,222,128,0.2)';
    } else {
      circle.style.background = 'linear-gradient(135deg,#d9202a 0%,#8b0a14 100%)';
      circle.style.borderColor = 'rgba(217,32,42,0.4)';
      circle.style.boxShadow = '0 4px 16px rgba(217,32,42,0.3)';
    }
  }
  usernameEl.addEventListener('input', updatePreview);
  roleEl.addEventListener('change', updatePreview);

  /* ── Password toggle ── */
  function togglePw(fieldId, eyeId) {
    const f = document.getElementById(fieldId);
    const e = document.getElementById(eyeId);
    if (f.type === 'password') {
      f.type = 'text';
      e.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
      <line x1="1" y1="1" x2="23" y2="23"/>`;
    } else {
      f.type = 'password';
      e.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    }
  }

  /* ── Password strength ── */
  document.getElementById('password').addEventListener('input', function() {
    const val = this.value;
    const bars = [document.getElementById('bar1'), document.getElementById('bar2'),
      document.getElementById('bar3'), document.getElementById('bar4')
    ];
    const lbl = document.getElementById('pwLabel');

    bars.forEach(b => b.className = 'pw-bar');

    if (!val) {
      lbl.textContent = '— masukkan password';
      lbl.style.color = 'var(--muted)';
      return;
    }

    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
    if (/[^a-zA-Z0-9]/.test(val)) score++;

    const cfg = [{
        cls: 'active-weak',
        color: 'var(--red-light)',
        label: 'Lemah'
      },
      {
        cls: 'active-weak',
        color: 'var(--red-light)',
        label: 'Lemah'
      },
      {
        cls: 'active-medium',
        color: 'var(--amber)',
        label: 'Sedang'
      },
      {
        cls: 'active-strong',
        color: 'var(--green)',
        label: 'Kuat 🔒'
      },
      {
        cls: 'active-strong',
        color: 'var(--green)',
        label: 'Sangat Kuat 🔒'
      },
    ];
    const c = cfg[score] || cfg[0];
    for (let i = 0; i < score; i++) bars[i].classList.add(c.cls);
    lbl.textContent = c.label;
    lbl.style.color = c.color;
  });

  /* ── Confirm password match hint ── */
  document.getElementById('confirm').addEventListener('input', function() {
    const pw = document.getElementById('password').value;
    const hint = document.getElementById('matchHint');
    hint.style.display = this.value ? 'block' : 'none';
    if (this.value === pw) {
      hint.textContent = '✓ Password cocok';
      hint.style.color = 'var(--green)';
    } else {
      hint.textContent = '✗ Password tidak cocok';
      hint.style.color = 'var(--red-light)';
    }
  });

  /* ── Client-side user search ── */
  function filterUsers() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.user-row');
    const empty = document.getElementById('emptySearch');
    let shown = 0;
    rows.forEach(r => {
      const match = r.dataset.name.includes(q);
      r.style.display = match ? '' : 'none';
      if (match) shown++;
    });
    if (empty) empty.style.display = shown === 0 ? 'block' : 'none';
  }

  /* ── Init preview on load (if form has old values) ── */
  updatePreview();
</script>