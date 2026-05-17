const scannerInput = document.getElementById("scannerInput");
const scannerBox = document.getElementById("scannerBox");
const scannerIcon = document.getElementById("scannerIcon");
const scannerStatus = document.getElementById("scannerStatus");
const scannerDot = document.getElementById("scannerDot");
const qrForm = document.getElementById("qrForm");

// Tambah progress bar ke scanner box
const progressBar = document.createElement("div");
progressBar.className = "scan-progress";
scannerBox.appendChild(progressBar);

// Focus management
// Deteksi fokus/blur
scannerInput.addEventListener("focus", () => {
  scannerBox.classList.add("focused");
  scannerStatus.textContent = "Siap scan — Scan ID Card";
});
scannerInput.addEventListener("blur", () => {
  scannerBox.classList.remove("focused");
  scannerStatus.textContent = "Klik area scan untuk mengaktifkan";
  scannerDot.classList.add("inactive");
});
scannerInput.addEventListener("focus", () => {
  scannerDot.classList.remove("inactive");
});

// Deteksi input dari scanner
let scanBuffer = "";
let scanTimer = null;

scannerInput.addEventListener("input", () => {
  const val = scannerInput.value;
  scanBuffer = val;

  // Update progress bar saat mengetik
  const progress = Math.min(val.length * 8, 100);
  progressBar.style.width = progress + "%";

  // Reset timer
  clearTimeout(scanTimer);
  scanTimer = setTimeout(() => {
    if (scanBuffer.length > 0) {
      triggerScan();
    }
  }, 300); // Submit otomatis 300ms setelah input berhenti
});

function triggerScan() {
  // Animasi scanning
  scannerStatus.textContent = "Memproses...";
  scannerBox.classList.remove("focused");

  progressBar.style.width = "100%";
  progressBar.style.transition = "width .1s ease";

  setTimeout(() => {
    progressBar.style.width = "0";
    progressBar.style.transition = "width .3s ease";
    qrForm.submit();
  }, 300);
}

const forceFocus = () => {
  const input = document.getElementById("scannerInput");
  if (input) input.focus();
};

window.addEventListener("load", forceFocus);
document.addEventListener("click", forceFocus);
