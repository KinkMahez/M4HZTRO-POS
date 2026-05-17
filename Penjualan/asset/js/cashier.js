let cart = [];
let totalBill = 0;      // subtotal sebelum diskon
let grandTotal = 0;     // total setelah diskon
let diskonPersen = 0;
const btnCheckout = document.getElementById("btn-checkout");
btnCheckout.disabled = true;


document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.getElementById("product-search");
  searchInput.focus();
});

function addToCart(product) {
  const foundIndex = cart.findIndex(
    (item) => item.id_product === product.id_product,
  );
  if (foundIndex > -1) {
    if (cart[foundIndex].qty < product.stok) {
      cart[foundIndex].qty++;
    } else {
      alert("Stok maksimal tercapai!");
    }
  } else {
    cart.push({
      ...product,
      qty: 1,
    });
  }
  renderCart();
}

function renderCart() {
  const list = document.getElementById("cart-list");
  const btnCheckout = document.getElementById("btn-checkout");
 

  if (cart.length === 0) {
    list.innerHTML =
      '<div class="empty-state"><img src="asset/img/x-cart.png" alt="">Empty</div>';
    btnCheckout.disabled = true;
    btnCheckout.innerHTML = "Add Item";

    updateTotals();
    return;
  }

  btnCheckout.disabled = false;
  btnCheckout.innerHTML = "Proceed Order";
  list.innerHTML = cart
    .map(
      (item, index) => `
        <div class="cart-item">
            <div class="item-detail">
                <p title="${item.nama_product}">${item.nama_product}</p>
                <h5>Rp ${(item.harga_jual * item.qty).toLocaleString()}</h5>
            </div>
            <div style="text-align: right">
                <div class="item-qty-controls">
                    <button type="button" class="qty-btn" onclick="updateQty(${index}, -1)">-</button>
                    <span class="mx-2" style="color: white;">${item.qty}</span>
                    <button type="button" class="qty-btn" onclick="updateQty(${index}, 1)">+</button>
                </div>
                <small style="color:red; cursor:pointer; margin-top:0.5rem; display:block;" onclick="removeItem(${index})">Hapus</small>
            </div>
        </div>
    `,
    )
    .join("");

  updateTotals();
}

function updateQty(index, delta) {
  if (!cart[index]) return;
  const newQty = cart[index].qty + delta;
  if (newQty < 1) {
    removeItem(index);
  } else if (newQty > cart[index].stok) {
    alert("Stok maksimal tercapai!");
  } else {
    cart[index].qty = newQty;
  }
  renderCart();
}

function removeItem(index) {
  cart.splice(index, 1);
  renderCart();
}

function clearCart() {
  if (confirm("Kosongkan keranjang?")) {
    cart = [];
    renderCart();
  }
}

function updateTotals() {
    totalBill = cart.reduce((sum, i) => sum + i.harga_jual * i.qty, 0);
    grandTotal = totalBill; // reset grandTotal ke subtotal dulu
    diskonPersen = 0;       // reset diskon

    document.getElementById("subtotal").innerText = "Rp " + totalBill.toLocaleString("id-ID");
    document.getElementById("grand-total").innerText = "Rp " + totalBill.toLocaleString("id-ID");
}


function openPaymentModal() {
    if (cart.length === 0) return;

    grandTotal = totalBill; // mulai dari subtotal
    diskonPersen = 0;

    document.getElementById("modal-pay-input").value = "";
    document.getElementById("modal-diskon-input").value = "";
    document.getElementById("modal-subtotal-display").innerText = "Rp " + totalBill.toLocaleString("id-ID");
    document.getElementById("modal-diskon-display").innerText = "Rp 0";
    document.getElementById("modal-grand-total").innerText = "Rp " + totalBill.toLocaleString("id-ID");
    document.getElementById("modal-change-display").innerText = "Rp 0";
    document.getElementById("btn-confirm-finish").disabled = true;

    // Generate shortcut buttons
    generateShortcuts(grandTotal);

    document.getElementById("modal-payment").style.display = "flex";
    setTimeout(() => document.getElementById("modal-pay-input").focus(), 100);
}

// Fungsi apply diskon
function applyDiskon() {
    const pct = parseFloat(document.getElementById("modal-diskon-input").value) || 0;

    if (pct < 0 || pct > 100) {
        alert("Diskon harus antara 0% - 100%");
        document.getElementById("modal-diskon-input").value = "";
        return;
    }

    diskonPersen = pct;
    grandTotal     = Math.floor(totalBill * ((100 - pct) / 100));
    const nominalDiskon = totalBill - grandTotal; 

    // Update tampilan summary
    document.getElementById("modal-subtotal-display").innerText = "Rp " + totalBill.toLocaleString("id-ID");
    document.getElementById("modal-diskon-display").innerText = pct > 0
        ? "- Rp " + nominalDiskon.toLocaleString("id-ID")
        : "Rp 0";
    document.getElementById("modal-grand-total").innerText = "Rp " + grandTotal.toLocaleString("id-ID");

    // Update shortcut sesuai grandTotal baru
    generateShortcuts(grandTotal);

    // Reset input bayar dan recalculate
    document.getElementById("modal-pay-input").value = "";
    calculateModalChange();
}

function formatCurrencyInput(input) {
  // Ambil angka murni
  let value = input.value.replace(/[^0-9]/g, "");

  // Tampilkan format ribuan di input
  if (value !== "") {
    input.value = parseInt(value).toLocaleString("id-ID");
  } else {
    input.value = "";
  }

  // Jalankan kalkulasi kembalian
  calculateModalChange();
}

function closeReceiptModal() {
  document.getElementById("modal-receipt").style.display = "none";
  location.reload(); // Reload setelah struk ditutup
}

// Generate tombol shortcut bayar
function generateShortcuts(total) {
    const roundUp = (n, to) => Math.ceil(n / to) * to;

    const shortcuts = [
        { label: "Uang Pas", val: total },
        { label: formatRpShort(roundUp(total, 5000)), val: roundUp(total, 5000) },
        { label: formatRpShort(roundUp(total, 10000)), val: roundUp(total, 10000) },
    ];

    // Hapus duplikat
    const unique = shortcuts.filter((s, i, arr) => 
        arr.findIndex(x => x.val === s.val) === i
    );

    const wrap = document.getElementById("shortcut-wrap");
    if (!wrap) return;
    wrap.innerHTML = unique.map(s =>
        `<button type="button" class="shortcut-btn" onclick="setPayInput(${s.val})">${s.label}</button>`
    ).join("");
}

function setPayInput(val) {
    document.getElementById("modal-pay-input").value = parseInt(val).toLocaleString("id-ID");
    calculateModalChange();
}

function formatRpShort(n) {
    if (n >= 1000000) return (n / 1000000).toFixed(1).replace(".0", "") + "jt";
    if (n >= 100000)  return (n / 1000).toFixed(0) + "rb";
    if (n >= 1000)    return (n / 1000).toFixed(1).replace(".0", "") + "rb";
    return n.toString();
}

// Update calculateModalChange 
function calculateModalChange() {
    const rawValue = document.getElementById("modal-pay-input").value.replace(/[^0-9]/g, "");
    const pay = parseInt(rawValue) || 0;
    const change = pay - grandTotal;
    const btnFinish = document.getElementById("btn-confirm-finish");
    const displayChange = document.getElementById("modal-change-display");

    if (change >= 0 && pay > 0 && grandTotal > 0) {
        displayChange.innerText = "Rp " + change.toLocaleString("id-ID");
        displayChange.style.color = "#10b981";
        btnFinish.disabled = false;
        btnFinish.style.background = "#d72437";
        btnFinish.style.cursor = "pointer";
    } else {
        displayChange.innerText = change < 0 
            ? "- Rp " + Math.abs(change).toLocaleString("id-ID") 
            : "Rp 0";
        displayChange.style.color = "#d72437";
        btnFinish.disabled = true;
        btnFinish.style.background = "#cbd5e1";
        btnFinish.style.cursor = "not-allowed";
    }
}

function closePaymentModal() {
  const modal = document.getElementById("modal-payment");
  modal.style.display = "none";

  document.getElementById("modal-pay-input").value = "";
  calculateModalChange();
}

//finishTransaction
async function finishTransaction() {
    if (cart.length === 0) return;

    const rawPay    = document.getElementById("modal-pay-input").value.replace(/[^0-9]/g, "");
    const payAmount = parseInt(rawPay);

    if (payAmount < grandTotal) {
        showToast('Pembayaran Kurang', 'Uang pembayaran tidak mencukupi', 'error');
        return;
    }

    const btnConfirm = document.getElementById("btn-confirm-finish");
    if (btnConfirm.disabled) return;

    btnConfirm.disabled = true;
    btnConfirm.innerHTML = "Memproses...";

    const nominalDiskon = Math.round(totalBill * (diskonPersen / 100));

    try {
        const response = await fetch("auth/process_transaction.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                cart,
                subtotal:       totalBill,
                diskon_persen:  diskonPersen,
                diskon_nominal: nominalDiskon,
                total_bill:     grandTotal,
                pay_amount:     payAmount,
                change_amount:  payAmount - grandTotal,
            }),
        });

        const rawText = await response.text();
        const data    = JSON.parse(rawText.trim()); // ← satu variabel konsisten

        if (data.success) { // ← pakai 'data', bukan 'result'
            closePaymentModal();
            showReceipt(data.receipt); // ← pakai 'data'
            cart = [];
            renderCart();
            showToast('Transaksi Berhasil', 'Data telah tersimpan', 'success');
        } else {
            showToast('Transaksi Gagal', data.message, 'error');
            btnConfirm.disabled = false;
            btnConfirm.innerText = "Konfirmasi";
        }

    } catch (error) {
        console.error("Parse error:", error);
        showToast('Gangguan Koneksi', 'Gagal menghubungi server', 'error');
        btnConfirm.disabled = false;
        btnConfirm.innerText = "Konfirmasi";
    }
}

// Update showReceipt — tampilkan diskon di receipt modal
function showReceipt(data) {
    document.getElementById("receipt-id").innerText = "#" + String(data.id).padStart(4, "0");
    document.getElementById("receipt-date").innerText = data.tanggal;
    document.getElementById("receipt-subtotal").innerText = "Rp " + data.subtotal.toLocaleString("id-ID");
    document.getElementById("receipt-total").innerText = "Rp " + data.total.toLocaleString("id-ID");
    document.getElementById("receipt-pay").innerText = "Rp " + data.bayar.toLocaleString("id-ID");
    document.getElementById("receipt-change").innerText = "Rp " + data.kembalian.toLocaleString("id-ID");
    document.getElementById("receipt-cashier").innerText = data.username;

    // Tampilkan baris diskon kalau ada
    const diskonRow = document.getElementById("receipt-diskon-row");
    if (diskonRow) {
        if (data.diskon_persen > 0) {
            diskonRow.style.display = "flex";
            document.getElementById("receipt-diskon").innerText =
                `- Rp ${data.diskon_nominal.toLocaleString("id-ID")}`;
        } else {
            diskonRow.style.display = "none";
        }
    }

    const itemsHtml = data.items.map(item => `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;font-size:0.85rem;gap:10px;">
            <div style="flex:1;min-width:0;">
                <p style="margin:0;color:#f2f2f7;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${item.nama_product}">
                    ${item.nama_product}
                </p>
                <p style="margin:0;color:#636366;font-size:0.75rem;">
                    ${item.qty} x Rp ${parseInt(item.harga_jual).toLocaleString("id-ID")}
                </p>
            </div>
            <span style="color:#dadada;font-weight:500;white-space:nowrap;flex-shrink:0;">
                Rp ${(item.qty * item.harga_jual).toLocaleString("id-ID")}
            </span>
        </div>
    `).join("");

    document.getElementById("receipt-items").innerHTML = itemsHtml;
    document.getElementById("modal-receipt").style.display = "flex";
}

// Update printReceipt — tambahkan baris diskon di struk
function printReceipt() {
    const id = document.getElementById("receipt-id").innerText;
    const date = document.getElementById("receipt-date").innerText;
    const subtotal = document.getElementById("receipt-subtotal").innerText;
    const total = document.getElementById("receipt-total").innerText;
    const bayar = document.getElementById("receipt-pay").innerText;
    const kembalian = document.getElementById("receipt-change").innerText;
    const kasir = document.getElementById("receipt-cashier").innerText;

    const nama_toko = document.getElementById("store-name").innerText;
    const alamat = document.getElementById("addres").innerText;

    const diskonRow = document.getElementById("receipt-diskon-row");
    const diskonHtml = diskonRow && diskonRow.style.display !== "none"
        ? `<div style="display:flex;justify-content:space-between;font-size:11px;padding:3px 0;color:#000;font-weight:600;">
               <span>Diskon</span>
               <span>${document.getElementById("receipt-diskon").innerText}</span>
           </div>`
        : "";

    const itemElements = Array.from(document.querySelectorAll("#receipt-items > div"));
    const itemsHtml = itemElements.map(el => {
        const name  = el.querySelector("p:first-child")?.innerText || "";
        const sub   = el.querySelector("p:last-child")?.innerText || "";
        const price = el.querySelector("span")?.innerText || "";
        return `
            <div style="padding:5px 0;border-bottom:1px dotted #000;">
                <div style="font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;">${name}</div>
                <div style="display:flex;justify-content:space-between;font-size:10.5px;margin-top:2px;">
                    <span style="color:#000;font-weight:600;">${sub}</span>
                    <span style="font-weight:600;color:#000;">${price}</span>
                </div>
            </div>`;
    }).join("");

    const printArea = document.getElementById("print-area");
    printArea.innerHTML = `
    <div style="width:100%;max-width:80mm;background:#f5f0e8;font-family:'Courier New',Courier,monospace;padding:6mm 5mm;color:#000;box-sizing:border-box;">

        <div style="text-align:center;margin-bottom:12px;margin-top:1rem;">
            <div style="font-size:31px;font-family:Georgia,serif;font-style:italic;font-weight:800;letter-spacing:-1px;line-height:1;transform:scaleY(1.28);">${nama_toko}</div>
        </div>

        <div style="text-align:center;font-size:10px;letter-spacing:.11em;margin-bottom:4px;font-weight:700;">${alamat}</div>

        <div style="border-top:1px solid #000;border-bottom:1px solid #000;margin:10px 0;padding:4px 0 1px 0;text-align:center;font-size:10px;font-weight:700;letter-spacing:.1em;">
            ${date} &nbsp;&nbsp; INV: ${id}
            <div style="text-align:center;font-size:9px;letter-spacing:.11em;margin-top:5px;font-weight:700;">Kasir: ${kasir}</div>
        </div>

        

        <div style="font-size:11.5px;letter-spacing:.1em;margin-bottom:8px;font-weight:700;">ITEM PEMBELIAN</div>

        <div style="border-top:1px dashed #000;padding:8px 0;border-bottom:1px dashed #000;margin-bottom:10px;">
            ${itemsHtml}
        </div>

        <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;font-size:11px;padding:3px 0;font-weight:600;">
                <span>Subtotal</span><span>${subtotal}</span>
            </div>
            ${diskonHtml}
            <div style="display:flex;justify-content:space-between;font-size:11px;padding:3px 0;font-weight:600;">
                <span>Bayar</span><span>${bayar}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:700;padding:5px 0;border-top:1px solid #000;margin-top:2px;">
                <span>KEMBALI</span><span>${kembalian}</span>
            </div>
        </div>

        <div style="border-top:1px solid #000;border-bottom:1px solid #000;padding:4px 0;text-align:center;font-size:12px;letter-spacing:.08em;margin-bottom:12px;font-weight:800;">
            TOTAL &nbsp;&nbsp; ${total}
        </div>

        <div style="text-align:center;margin-bottom:6px;">
            <div style="text-align:center;margin-bottom:3px;">
                <img src="asset/img/brand-ico.png" alt="logo" style="width:27px;height:auto;filter:grayscale(100%);" />
            </div>
            <div style="font-size:12.5px;letter-spacing:.1em;font-weight:800;">- WEAR IT LIKE A MAESTRO -</div>
        </div>

        <div style="text-align:center;border-top:1px dashed #000;padding-top:10px;margin-top:6px;">
            <img src="asset/img/barcode-le.png" alt="brcd" style="width:100%;max-width:100%;height:50px;object-fit:fill;filter:grayscale(100%);" />
            <div style="font-size:9px;white-space:nowrap;font-weight:600;">Collect this receipt for your records.</div>
        </div>

    </div>`;

    setTimeout(() => window.print(), 250);
}

//search and filter
document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.getElementById("product-search");
  const buttons = document.querySelectorAll(".cat-btn");
  const cards = document.querySelectorAll(".product-card");

  function applyAllFilters() {

  const query = searchInput.value.toLowerCase().trim();
  const activeBtn = document.querySelector(".cat-btn.active");
  const activeCategory = activeBtn ? activeBtn.getAttribute("data-category") : "all";

  cards.forEach((card) => {
  
    const productName = card.querySelector(".p-name").innerText.toLowerCase();
    const productBarcode = (card.getAttribute("data-barcode") || "").toLowerCase();
    const cardCategory = card.getAttribute("data-category");
    const matchesSearch = productName.includes(query) || productBarcode.includes(query);
    const matchesCategory = activeCategory === "all" || activeCategory === cardCategory;
    
    if (matchesSearch && matchesCategory) {
      card.classList.remove("hidden");
    } else {
      card.classList.add("hidden");
    }
  });
}

  searchInput.addEventListener("input", applyAllFilters);

  // Event saat tombol kategori diklik
  buttons.forEach((button) => {
    button.addEventListener("click", function () {
      buttons.forEach((btn) => btn.classList.remove("active"));
      this.classList.add("active");
      applyAllFilters();
    });
  });
});

const searchInput = document.getElementById('product-search');
if (searchInput) {
    searchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault(); 

            const visibleCards = document.querySelectorAll('.product-card:not(.hidden)');

            if (visibleCards.length === 1) {
                // Jika hanya 1 produk yang muncul 
                console.log("Barcode ditemukan! Menambahkan ke keranjang...");
                visibleCards[0].click();
                this.value = '';
      
                this.style.borderColor = "#22c55e"; 
                setTimeout(() => this.style.borderColor = "", 500);
            } else if (visibleCards.length > 1) {
                console.warn("Hasil pencarian lebih dari satu, silakan pilih manual atau scan lebih spesifik.");
            } else {
                console.error("Produk tidak ditemukan.");
                this.style.borderColor = "#ef4444";
                setTimeout(() => this.style.borderColor = "", 500);
            }
        }
    });
}

function filterProducts() {
    if (typeof applyAllFilters === 'function') {
        applyAllFilters();
    }
}


