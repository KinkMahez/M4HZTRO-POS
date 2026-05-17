// DATA-PRODUCTS.JS
function toggleModal(show) {
  document.getElementById("productModal").style.display = show
    ? "block"
    : "none";
}
function previewImage(input) {
  const preview = document.getElementById("preview-box");
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function (e) {
      preview.innerHTML = `<img src="${e.target.result}">`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function filterProducts() {
  // 1. Ambil value dari input search dan ubah ke lowercase agar tidak case-sensitive
  const input = document.getElementById("product-search");
  const filter = input.value.toLowerCase();

  // 2. Ambil elemen tabel dan semua baris (tr) di dalam tbody
  const table = document.querySelector(".styled-table");
  const tr = table.getElementsByTagName("tr");

  for (let i = 0; i < tr.length; i++) {
    // Ambil kolom yang ingin dicari (Barcode, Nama Product, dan Category)
    const barcodeCell = tr[i].getElementsByTagName("td")[1]; // Kolom Barcode
    const nameCell = tr[i].getElementsByTagName("td")[2]; // Kolom Nama
    const categoryCell = tr[i].getElementsByTagName("td")[3]; // Kolom Category

    if (barcodeCell || nameCell || categoryCell) {
      const barcodeText = barcodeCell.textContent || barcodeCell.innerText;
      const nameText = nameCell.textContent || nameCell.innerText;
      const categoryText = categoryCell.textContent || categoryCell.innerText;

      // 4. Cek apakah kata kunci ada di salah satu kolom tersebut
      if (
        nameText.toLowerCase().indexOf(filter) > -1 ||
        barcodeText.toLowerCase().indexOf(filter) > -1 ||
        categoryText.toLowerCase().indexOf(filter) > -1
      ) {
       
        tr[i].style.display = "";
      } else {
   
        tr[i].style.display = "none";
      }
    }
  }
}
function previewImage(input) {
  const preview = document.getElementById("preview-box");
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function (e) {
      preview.innerHTML = `<img src="${e.target.result}" style="max-height:100px; border-radius: 8px;">`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// Fungsi Tutup Modal
function closeModalUpdate() {
  document.getElementById("productModal").style.display = "none";
}

// Fungsi Membuka Modal untuk EDIT
function openEditModal(data) {
  console.log("Opening edit for:", data); // Debugging
  const modal = document.getElementById("productModal");

  // 1. Ubah Judul & Nama Button
  document.getElementById("modalTitle").innerText = "Update Product";
  document.getElementById("btnSave").name = "update_product";
  document.getElementById("btnSave").innerText = "Update Product";

  // 2. Isi data ke dalam field input
  document.getElementById("modal_id_product").value = data.id_product;
  document.getElementById("modal_barcode").value = data.barcode;
  document.getElementById("modal_nama_product").value = data.nama_product;
  document.getElementById("modal_harga_beli").value = data.harga_beli;
  document.getElementById("modal_harga_jual").value = data.harga_jual;
  document.getElementById("modal_kategori").value = data.id_category;

  // 3. Preview Gambar jika ada
  const preview = document.getElementById("preview-box");
  if (data.gambar) {
    preview.innerHTML = `<img src="asset/img/products/${data.gambar}" style="max-height:95px; border-radius: 8px;">`;
  } else {
    preview.innerHTML = "<span>No Image Available</span>";
  }

  // Tampilkan Modal
  modal.style.display = "flex";
}

// Fungsi Konfirmasi Hapus
function confirmDelete(id, name) {
  if (confirm(`Apakah Anda yakin ingin menghapus produk "${name}"?`)) {
    window.location.href = `auth/process_product.php?delete_id=${id}`;
  }
}

window.onclick = function (event) {
  const modal = document.getElementById("productModal");
  if (event.target == modal) {
    closeModal();
  }
};

document.addEventListener("DOMContentLoaded", () => {
  const toast = document.getElementById("toast");
  const sound = new Audio('asset/js/success.mp3'); // Pastikan file success.mp3 ada di folder yang benar
  if (toast) {
    // Hilangkan secara otomatis setelah 3.5 detik
    
    sound.play();
    setTimeout(() => {
      toast.classList.add("fade-out");
      setTimeout(() => {
        toast.parentElement.remove(); // Hapus container
      }, 500);
    }, 2700);
  }
});

function previewImageAdd(input) {
        const previewBox = document.getElementById('preview-box');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewBox.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    window.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const category = params.get('category');
    if (category) {
        const input = document.getElementById('product-search');
        input.value = category;
        
        input.dispatchEvent(new Event('keyup'));
    }
});
