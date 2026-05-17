document.addEventListener("DOMContentLoaded", function () {
  // --- 1. LOGIKA ACTIVE STATE (Menjaga Dropdown Tetap Terbuka) ---
  function initActiveState() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = urlParams.get("page");

    if (currentPage) {
      // Cari sub-item yang href-nya mengandung nama page saat ini
      const activeSubItem = document.querySelector(
        `.submenu a[href*="page=${currentPage}"]`,
      );

      if (activeSubItem) {
        // Tambahkan class active ke link itu sendiri
        activeSubItem.classList.add("active");

        // Cari elemen induk (submenu) dan trigger-nya
        const submenu = activeSubItem.closest(".submenu");
        const trigger = submenu.previousElementSibling; // dropdown-trigger

        if (submenu && trigger) {
          // Buka submenu secara otomatis
          trigger.classList.add("open");
          submenu.classList.add("show");
          submenu.style.maxHeight = submenu.scrollHeight + "px";
        }
      }
    }
  }

  // --- 2. FUNGSI DROPDOWN SIDEBAR (Klik Manual) ---
  const dropdowns = document.querySelectorAll(".dropdown-trigger");

  dropdowns.forEach((dropdown) => {
    dropdown.addEventListener("click", () => {
      const submenu = dropdown.nextElementSibling;
      const isOpen = dropdown.classList.contains("open");

      // Tutup semua dropdown lain (Accordion effect)
      dropdowns.forEach((other) => {
        if (other !== dropdown) {
          other.classList.remove("open");
          const otherSub = other.nextElementSibling;
          if (otherSub) {
            otherSub.classList.remove("show");
            otherSub.style.maxHeight = null;
          }
        }
      });

      // Toggle menu yang diklik
      if (submenu && submenu.classList.contains("submenu")) {
        if (!isOpen) {
          dropdown.classList.add("open");
          submenu.classList.add("show");
          submenu.style.maxHeight = submenu.scrollHeight + "px";
        } else {
          dropdown.classList.remove("open");
          submenu.classList.remove("show");
          submenu.style.maxHeight = null;
        }
      }
    });
  });

  // --- 3. FUNGSI UPDATE TANGGAL/JAM ---
  function updateDate() {
    const dateElement = document.getElementById("current-date");
    if (dateElement) {
      const now = new Date();
      const options = {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
      };
      // Format Indonesia
      dateElement.innerText = now.toLocaleDateString("id-ID", options);
    }
  }

  // --- 4. FUNGSI PROFILE (Safe Check) ---
  const profileDropdown = document.getElementById("profileDropdown");
  const profileMenu = document.getElementById("profileMenu");

  if (profileDropdown && profileMenu) {
    profileDropdown.addEventListener("click", (e) => {
      e.preventDefault();
      profileMenu.classList.toggle("show");
    });
  }

  // --- 5. SIDEBAR COLLAPSE LOGIC ---
  const sidebar = document.querySelector(".sidebar");
  const toggleBtn = document.getElementById("toggleSidebar");

  function setSidebarState(collapsed) {
    if (!sidebar) return;

    if (collapsed) {
        sidebar.classList.add('collapsed');
        localStorage.setItem('sidebarCollapsed', 'true');

        document.querySelectorAll('.submenu').forEach(sub => {
            sub.classList.remove('show');
            sub.style.maxHeight = null;
        });
        document.querySelectorAll('.dropdown-trigger').forEach(trigger => {
            trigger.classList.remove('open');
        });
    } else {
        sidebar.classList.remove('collapsed');
        localStorage.setItem('sidebarCollapsed', 'false');
    }

    document.documentElement.classList.remove('sidebar-will-collapse');
}


if (localStorage.getItem('sidebarCollapsed') === 'true') {
    setSidebarState(true);
}

  // Toggle button click
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", function () {
      const isCollapsed = sidebar.classList.contains("collapsed");
      setSidebarState(!isCollapsed);
      toggleBtn.classList.toggle('collapsed');
    });
  }

  // Restore state saat reload
  if (localStorage.getItem("sidebarCollapsed") === "true") {
    setSidebarState(true);
  }

  // Jalankan inisialisasi
  updateDate();
  setInterval(updateDate, 1000);
  initActiveState(); // Jalankan pengecekan halaman aktif

  console.log("M4HZTRO Logic Loaded Successfully with Active State Support");
});
