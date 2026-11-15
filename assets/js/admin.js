(function () {
  "use strict";

  function getElements() {
    return {
      sidebar: document.getElementById("sidebar"),
      overlay: document.getElementById("overlay"),
      btnBuka: document.getElementById("btnBukaSidebar"),
    };
  }

  /**
   * Buka sidebar responsif pada layar mobile.
   */
  window.bukaSidebar = function () {
    const { sidebar, overlay } = getElements();
    if (sidebar) sidebar.classList.remove("-translate-x-full");
    if (overlay) overlay.classList.remove("hidden");
    document.body.style.overflow = "hidden"; // Kunci scroll di belakang overlay
  };

  /**
   * Tutup sidebar responsif dan sembunyikan overlay.
   */
  window.tutupSidebar = function () {
    const { sidebar, overlay } = getElements();
    if (sidebar) sidebar.classList.add("-translate-x-full");
    if (overlay) overlay.classList.add("hidden");
    document.body.style.overflow = "";
  };

  document.addEventListener("DOMContentLoaded", function () {
    const { btnBuka, overlay } = getElements();

    // Pasang event listener tombol buka & overlay
    if (btnBuka) {
      btnBuka.addEventListener("click", window.bukaSidebar);
    }
    if (overlay) {
      overlay.addEventListener("click", window.tutupSidebar);
    }

    // Delegasi event untuk tombol close flash message
    document.addEventListener("click", function (e) {
      const btnTutup = e.target.closest('[data-dismiss="flash"]');
      if (btnTutup) {
        const flashBox = btnTutup.closest(".flash-message");
        if (flashBox) {
          flashBox.remove();
        }
      }
    });
  });

  // Otomatis tutup sidebar mobile saat lebar layar berubah ke mode desktop (>= 1024px)
  window.addEventListener("resize", function () {
    if (window.innerWidth >= 1024) {
      window.tutupSidebar();
    }
  });
})();
