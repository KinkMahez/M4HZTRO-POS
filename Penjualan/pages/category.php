<?php
require_once 'config/database.php';

// Tambah kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
  $nama = trim($_POST['nama']);
  $desc = trim($_POST['desc']);
  if ($nama) {
    $stmt = $conn->prepare("INSERT INTO categories (nama_category) VALUES (?)");
    $stmt->bind_param("s", $nama);
    $stmt->execute();
  }
  echo "<script>window.location.href='index.php?page=category'</script>";
  $_SESSION['info'] = 'Category berhasil ditambahkan!';
  exit;
}

// Edit kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
  $id   = intval($_POST['id']);
  $nama = trim($_POST['nama']);
  if ($nama && $id) {
    $stmt = $conn->prepare("UPDATE categories SET nama_category = ? WHERE id_category = ?");
    $stmt->bind_param("si", $nama, $id);
    $stmt->execute();
  }
  echo "<script>window.location.href='index.php?page=category'</script>";
  $_SESSION['info'] = 'Category berhasil diperbarui!';
  exit;
}

// Hapus kategori
if (isset($_GET['hapus'])) {
  $id = intval($_GET['hapus']);
  // Cek apakah kategori masih dipakai produk
  $cek = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE id_category = ?");
  $cek->bind_param("i", $id);
  $cek->execute();
  $row = $cek->get_result()->fetch_assoc();
  if ($row['total'] > 0) {
    echo "<script>window.location.href='index.php?page=category'</script>";
    $_SESSION['info-x'] = 'Category tidak bisa dihapus karena masih digunakan oleh produk.';
  } else {
    $conn->prepare("DELETE FROM categories WHERE id_category = ?")->bind_param("i", $id) && true;
    $del = $conn->prepare("DELETE FROM categories WHERE id_category = ?");
    $del->bind_param("i", $id);
    $del->execute();
    echo "<script>window.location.href='index.php?page=category'</script>";
    $_SESSION['info'] = 'Category berhasil dihapus!';
  }
  exit;
}

// Ambil data
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchParam = "%$search%";

$stmt = $conn->prepare("
    SELECT c.id_category, c.nama_category,
           COUNT(p.id_product) as total_produk
    FROM categories c
    LEFT JOIN products p ON p.id_category = c.id_category
    WHERE c.nama_category LIKE ?
    GROUP BY c.id_category, c.nama_category
    ORDER BY c.id_category ASC
");
$stmt->bind_param("s", $searchParam);
$stmt->execute();
$categories = $stmt->get_result();
$catsArray = $categories->fetch_all(MYSQLI_ASSOC);

// Stats
$totalKat  = count($catsArray);
$totalProd = array_sum(array_column($catsArray, 'total_produk'));
$topKat    = $totalKat > 0
  ? array_reduce(
    $catsArray,
    fn($carry, $item) => (!$carry || $item['total_produk'] > $carry['total_produk']) ? $item : $carry
  )
  : null;

// Palette & emoji
$palette = [
  ['bg' => '#2a0a0a', 'color' => '#e02020'],
  ['bg' => '#0a1520', 'color' => '#4a9eff'],
  ['bg' => '#0a1a0a', 'color' => '#4ade80'],
  ['bg' => '#1a0a1a', 'color' => '#cc66ff'],
  ['bg' => '#1a1500', 'color' => '#ffaa00'],
];

function getEmoji($nama)
{
  $nama = strtolower($nama);
  if (str_contains($nama, 't-shirt') || str_contains($nama, 'tshirt') || str_contains($nama, 'kaos')) return '👕';
  if (str_contains($nama, 'hoodie')) return '🧥';
  if (str_contains($nama, 'jacket') || str_contains($nama, 'jaket')) return '🧣';
  if (str_contains($nama, 'accessories') || str_contains($nama, 'aksesoris')) return '🎩';
  return '🏷️';
}
?>
<link rel="stylesheet" href="asset/css/category.css?v=<?= time(); ?>">
<link rel="stylesheet" href="asset/css/pages.css?v=<?= time(); ?>">

<?php if (isset($_SESSION['info'])): ?>
  <div class="toast-container">
    <div id="toast" class="toast-notification">
      <div class="toast-icon">
        <i class="fas fa-check-circle"></i>
      </div>
      <div class="toast-message">
        <?= $_SESSION['info']; ?>
      </div>
    </div>
  </div>
  <?php unset($_SESSION['info']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['info-x'])): ?>
  <div class="toast-container">
    <div id="toast" class="toast-notification">
      <div class="toast-icon-warning">
        <i class="fas fa-exclamation-circle"></i>
      </div>
      <div class="toast-message">
        <?= $_SESSION['info-x']; ?>
      </div>
    </div>
  </div>
  <?php unset($_SESSION['info-x']); ?>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat-icon" style="background:#1e1e1e">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="2">
        <rect x="3" y="3" width="7" height="7" />
        <rect x="14" y="3" width="7" height="7" />
        <rect x="3" y="14" width="7" height="7" />
        <rect x="14" y="14" width="7" height="7" />
      </svg>
    </div>
    <div>
      <div class="stat-label">TOTAL KATEGORI</div>
      <div class="stat-val"><?= $totalKat ?></div>
    </div>
  </div>
  <div class="stat">
    <div class="stat-icon" style="background:#2a0a0a">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e02020" stroke-width="2">
        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
        <line x1="7" y1="7" x2="7.01" y2="7" />
      </svg>
    </div>
    <div>
      <div class="stat-label">TERBANYAK PRODUK</div>
      <div class="stat-val accent"><?= $topKat ? htmlspecialchars($topKat['nama_category']) : '—' ?></div>
    </div>
  </div>
  <div class="stat">
    <div class="stat-icon" style="background:#1e1e1e">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="2">
        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
        <line x1="3" y1="6" x2="21" y2="6" />
      </svg>
    </div>
    <div>
      <div class="stat-label">TOTAL PRODUK</div>
      <div class="stat-val"><?= $totalProd ?></div>
    </div>
  </div>
</div>

<div class="toolbar">
  <span style="color:white;font-family: 'Jakarta', sans-serif;font-size: 1.27rem;margin-left: 0.25rem;">Daftar Category</span>
  <button class="btn-red" onclick="openAdd()">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
      <line x1="12" y1="5" x2="12" y2="19" />
      <line x1="5" y1="12" x2="19" y2="12" />
    </svg>
    Tambah Kategori
  </button>
</div>

<div class="cat-grid">
  <?php if (empty($catsArray)): ?>
    <div class="empty">Tidak ada kategori ditemukan.</div>
  <?php else: ?>
    <?php foreach ($catsArray as $i => $c): ?>
      <?php $p = $palette[$i % count($palette)]; ?>
      <div class="cat-card">
        <div class="cat-top">
          <div class="cat-header">
            <div class="cat-icon" style="background:<?= $p['bg'] ?>"><?= getEmoji($c['nama_category']) ?></div>
            <div class="cat-actions">
              <button class="act-btn" onclick="openEdit(<?= $c['id_category'] ?>, '<?= addslashes($c['nama_category']) ?>')" title="Edit">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z" />
                </svg>
              </button>
              <button class="act-btn del" onclick="openDelete(<?= $c['id_category'] ?>, '<?= addslashes($c['nama_category']) ?>')" title="Hapus">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6" />
                  <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                  <path d="M10 11v6M14 11v6" />
                </svg>
              </button>
            </div>
          </div>
          <div class="cat-name"><?= htmlspecialchars($c['nama_category']) ?></div>
          <div class="cat-desc">Klik edit untuk menambahkan deskripsi.</div>
        </div>
        <div class="cat-bottom">
          <span class="prod-count" style="background:<?= $p['bg'] ?>;color:<?= $p['color'] ?>"><?= $c['total_produk'] ?> Produk</span>
          <a href="index.php?page=data-product&category=<?= urlencode($c['nama_category']) ?>" class="cat-link" style="color:<?= $p['color'] ?>">
            Lihat Produk
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="5" y1="12" x2="19" y2="12" />
              <polyline points="12 5 19 12 12 19" />
            </svg>
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal-bg" id="formModal" onclick="if(event.target===this)closeForm()">
  <div class="cat-modal">
    <div class="modal-header">
      <div class="modal-title" id="formTitle">Tambah Kategori</div>
      <button class="modal-close" onclick="closeForm()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
    </div>
    <form method="POST" id="catForm">
      <input type="hidden" name="id" id="fId">
      <span class="field-label">NAMA KATEGORI</span>
      <input class="field" type="text" name="nama" id="fNama" placeholder="Contoh: T-Shirt" required>
      <div class="modal-footer">
        <button type="submit" class="btn-save">Simpan</button>
        <button type="button" class="btn-cancel" onclick="closeForm()">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Hapus -->
<div class="del-modal" id="delModal">
  <div class="del-box">
    <div class="del-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e02020" stroke-width="2">
        <polyline points="3 6 5 6 21 6" />
        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
        <path d="M10 11v6M14 11v6" />
      </svg>
    </div>
    <div class="del-title">Hapus Kategori?</div>
    <div class="del-sub" id="delSub">Kategori ini akan dihapus permanen.</div>
    <div class="del-actions">
      <a class="btn-del" id="delConfirmBtn" href="#">Ya, Hapus</a>
      <button class="btn-cancel" onclick="closeDelete()">Batal</button>
    </div>
  </div>
</div>

<script>
  function openAdd() {
    document.getElementById('formTitle').textContent = 'Tambah Kategori';
    document.getElementById('fId').value = '';
    document.getElementById('fNama').value = '';
    document.getElementById('catForm').querySelector('[name=tambah]')?.remove();
    const h = document.createElement('input');
    h.type = 'hidden';
    h.name = 'tambah';
    h.value = '1';
    document.getElementById('catForm').appendChild(h);
    document.getElementById('formModal').classList.add('active');
  }

  function openEdit(id, nama) {
    document.getElementById('formTitle').textContent = 'Edit Kategori';
    document.getElementById('fId').value = id;
    document.getElementById('fNama').value = nama;
    document.getElementById('catForm').querySelector('[name=tambah]')?.remove();
    document.getElementById('catForm').querySelector('[name=edit]')?.remove();
    const h = document.createElement('input');
    h.type = 'hidden';
    h.name = 'edit';
    h.value = '1';
    document.getElementById('catForm').appendChild(h);
    document.getElementById('formModal').classList.add('active');
  }

  function closeForm() {
    document.getElementById('formModal').classList.remove('active');
  }

  function openDelete(id, nama) {
    document.getElementById('delSub').textContent = `Kategori "${nama}" akan dihapus permanen dan tidak bisa dikembalikan.`;
    document.getElementById('delConfirmBtn').href = `http://localhost/Penjualan/index.php?page=category&hapus=${id}`;
    document.getElementById('delModal').classList.add('active');
  }

  function closeDelete() {
    document.getElementById('delModal').classList.remove('active');
  }
</script>
<script src="asset/js/products.js?v=<?= time(); ?>"></script>