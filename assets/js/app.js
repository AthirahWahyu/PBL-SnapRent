/**
 * assets/js/app.js
 * Utilitas JavaScript bersama untuk Sisi Pelanggan SnapRent:
 * 1. Pembungkus fetch JSON + penanganan error jaringan & CSRF otomatis
 * 2. Helper aksesibilitas <dialog> (buka, tutup, manajemen fokus, klik backdrop, Esc)
 * 3. Toast notifikasi mengambang
 * 4. Pembaruan badge jumlah item keranjang di navbar
 * 5. Toggle menu lipat ponsel (navbar responsif)
 */

(function (window, document) {
    'use strict';

    /**
     * Ambil token CSRF dari meta tag atau input hidden.
     */
    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }
        const input = document.querySelector('input[name="_csrf_token"]');
        return input ? input.value : '';
    }

    /**
     * Bentuk URL lengkap berdasarkan meta[name="base-url"].
     */
    function baseUrl(path = '') {
        const meta = document.querySelector('meta[name="base-url"]');
        const base = meta && meta.content ? meta.content.replace(/\/+$/, '') : '';
        if (!path) return base;
        if (/^https?:\/\//i.test(path)) return path;
        return base + '/' + String(path).replace(/^\/+/, '');
    }

    /**
     * Escape karakter HTML untuk mencegah XSS saat menyusun string di JS.
     */
    function escapeHtml(nilai) {
        const div = document.createElement('div');
        div.textContent = nilai == null ? '' : String(nilai);
        return div.innerHTML;
    }

    /**
     * Format angka menjadi format Rupiah Indonesia ("Rp 350.000").
     */
    function formatRupiah(angka) {
        const num = Number(angka);
        if (!Number.isFinite(num)) return 'Rp 0';
        return 'Rp ' + Math.round(num).toLocaleString('id-ID');
    }

    /**
     * Pembungkus fetch yang membaca JSON {"status":"ok","data":...} atau {"status":"error","message":...}
     * serta menangani kegagalan jaringan dan menyisipkan token CSRF pada request POST.
     *
     * @param {string} url     URL tujuan
     * @param {object} options Opsi fetch tambahan (method, data, body, headers)
     * @returns {Promise<{status: string, data?: any, message?: string, httpCode?: number}>}
     */
    async function fetchJson(url, options = {}) {
        const metode = (options.method || (options.data || options.body ? 'POST' : 'GET')).toUpperCase();
        const headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }, options.headers || {});

        let body = options.body || null;

        if (metode === 'POST') {
            const token = csrfToken();
            if (options.data instanceof FormData) {
                body = options.data;
                if (token && !body.has('_csrf_token')) {
                    body.append('_csrf_token', token);
                }
            } else if (body instanceof FormData) {
                if (token && !body.has('_csrf_token')) {
                    body.append('_csrf_token', token);
                }
            } else if (options.data && typeof options.data === 'object') {
                const params = new URLSearchParams();
                Object.entries(options.data).forEach(([k, v]) => {
                    if (v !== undefined && v !== null) {
                        params.append(k, String(v));
                    }
                });
                if (token && !params.has('_csrf_token')) {
                    params.append('_csrf_token', token);
                }
                body = params;
            } else if (!body) {
                const params = new URLSearchParams();
                if (token) {
                    params.append('_csrf_token', token);
                }
                body = params;
            }
        }

        try {
            const response = await fetch(url, {
                method: metode,
                headers: headers,
                body: metode === 'GET' ? undefined : body,
                credentials: 'same-origin'
            });

            const teks = await response.text();
            let hasil = null;
            try {
                hasil = JSON.parse(teks);
            } catch (_errParse) {
                return {
                    status: 'error',
                    message: response.ok
                        ? 'Respons server tidak berformat JSON yang valid.'
                        : 'Terjadi kesalahan pada server (HTTP ' + response.status + ').',
                    httpCode: response.status
                };
            }

            if (!hasil || typeof hasil !== 'object') {
                return {
                    status: 'error',
                    message: 'Respons server kosong atau tidak dikenali.',
                    httpCode: response.status
                };
            }

            if (!response.ok && hasil.status !== 'error') {
                return {
                    status: 'error',
                    message: hasil.message || ('Permintaan gagal (HTTP ' + response.status + ').'),
                    httpCode: response.status
                };
            }

            return hasil;
        } catch (_errJaringan) {
            return {
                status: 'error',
                message: 'Gagal terhubung ke server. Periksa koneksi jaringan Anda.'
            };
        }
    }

    /**
     * Ambil elemen <dialog> dari ID string atau objek HTMLElement.
     */
    function ambilElemenDialog(dialogOrId) {
        if (!dialogOrId) return null;
        if (typeof dialogOrId === 'string') {
            return document.getElementById(dialogOrId);
        }
        return dialogOrId instanceof HTMLElement ? dialogOrId : null;
    }

    /**
     * Pasang listener aksesibilitas pada elemen <dialog> (sekali per elemen).
     */
    function ikatEventDialog(dialog) {
        if (!dialog || dialog.dataset.dialogTerikat === '1') return;
        dialog.dataset.dialogTerikat = '1';

        // Kembalikan fokus ke elemen pemicu ketika dialog ditutup (termasuk saat tekan Esc)
        dialog.addEventListener('close', function () {
            const pemicu = dialog._fokusSebelumnya;
            dialog._fokusSebelumnya = null;
            if (pemicu && typeof pemicu.focus === 'function' && document.contains(pemicu)) {
                pemicu.focus();
            }
        });

        // Klik area backdrop (di luar .dialog-panel) menutup dialog
        dialog.addEventListener('click', function (ev) {
            if (ev.target === dialog) {
                tutupDialog(dialog);
            }
        });
    }

    /**
     * Buka jendela <dialog> dengan manajemen fokus aksesibel.
     *
     * @param {string|HTMLDialogElement} dialogOrId ID atau elemen <dialog>
     * @param {HTMLElement|null} pemicu Elemen yang memicu pembukaan dialog
     */
    function bukaDialog(dialogOrId, pemicu = null) {
        const dialog = ambilElemenDialog(dialogOrId);
        if (!dialog) return;

        ikatEventDialog(dialog);
        dialog._fokusSebelumnya = pemicu || document.activeElement;

        if (!dialog.open) {
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', 'open');
            }
        }

        // Pindahkan fokus ke elemen interaktif pertama di dalam dialog
        const fokusPertama = dialog.querySelector(
            '[data-fokus-awal], button:not([disabled]), [href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );
        if (fokusPertama && typeof fokusPertama.focus === 'function') {
            fokusPertama.focus();
        } else if (typeof dialog.focus === 'function') {
            dialog.focus();
        }
    }

    /**
     * Tutup jendela <dialog> dan kembalikan fokus ke elemen pemicu.
     *
     * @param {string|HTMLDialogElement} dialogOrId ID atau elemen <dialog>
     */
    function tutupDialog(dialogOrId) {
        const dialog = ambilElemenDialog(dialogOrId);
        if (!dialog || !dialog.open) return;

        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
            dialog.dispatchEvent(new Event('close'));
        }
    }

    /**
     * Tampilkan pesan toast mengambang.
     *
     * @param {string} pesan  Isi pesan notifikasi
     * @param {string} tipe   'sukses' | 'error' | 'info'
     * @param {number} durasi Waktu tampil dalam milidetik (default 3500ms)
     */
    function tampilkanToast(pesan, tipe = 'info', durasi = 3500) {
        if (!pesan) return;

        let wadah = document.getElementById('toastContainer');
        if (!wadah) {
            wadah = document.createElement('div');
            wadah.id = 'toastContainer';
            wadah.className = 'toast-wadah';
            wadah.setAttribute('role', 'status');
            wadah.setAttribute('aria-live', 'polite');
            document.body.appendChild(wadah);
        }

        const kelasTipe = tipe === 'sukses'
            ? 'toast-item--sukses'
            : (tipe === 'error' ? 'toast-item--error' : 'toast-item--info');

        const item = document.createElement('div');
        item.className = 'toast-item ' + kelasTipe;

        const teksSpan = document.createElement('span');
        teksSpan.textContent = String(pesan);

        const btnTutup = document.createElement('button');
        btnTutup.type = 'button';
        btnTutup.className = 'toast-item__tutup';
        btnTutup.setAttribute('aria-label', 'Tutup notifikasi');
        btnTutup.textContent = '×';
        btnTutup.addEventListener('click', function () {
            item.remove();
        });

        item.appendChild(teksSpan);
        item.appendChild(btnTutup);
        wadah.appendChild(item);

        if (durasi > 0) {
            setTimeout(function () {
                if (item.parentElement) {
                    item.remove();
                }
            }, durasi);
        }
    }

    /**
     * Perbarui angka badge keranjang di navbar.
     *
     * @param {number} jumlah Jumlah item di keranjang
     */
    function perbaruiBadgeKeranjang(jumlah) {
        const angka = Math.max(0, parseInt(jumlah, 10) || 0);
        const badges = document.querySelectorAll('#badgeKeranjang, [data-keranjang-badge]');
        badges.forEach(function (el) {
            el.textContent = String(angka);
            if (angka === 0) {
                el.classList.add('keranjang-badge--kosong');
            } else {
                el.classList.remove('keranjang-badge--kosong');
            }
        });

        const btnKeranjang = document.getElementById('btnBukaKeranjang');
        if (btnKeranjang) {
            btnKeranjang.setAttribute('aria-label', 'Keranjang penyewaan, ' + angka + ' barang');
        }
    }

    /**
     * Inisialisasi interaksi umum pada header/dialog saat DOM siap.
     */
    function inisialisasiUmum() {
        // 1. Pasang listener pada semua elemen <dialog> yang ada di halaman
        document.querySelectorAll('dialog').forEach(ikatEventDialog);

        // 2. Delegasi klik untuk tombol pembuka & penutup dialog berbasis atribut data
        document.addEventListener('click', function (ev) {
            const btnTutup = ev.target.closest('[data-tutup-dialog]');
            if (btnTutup) {
                ev.preventDefault();
                const idTarget = btnTutup.getAttribute('data-tutup-dialog');
                tutupDialog(idTarget || btnTutup.closest('dialog'));
                return;
            }

            const btnBuka = ev.target.closest('[data-buka-dialog]');
            if (btnBuka) {
                ev.preventDefault();
                const idTarget = btnBuka.getAttribute('data-buka-dialog');
                if (idTarget) {
                    bukaDialog(idTarget, btnBuka);
                }
                return;
            }

            // Tombol ikon keranjang di navbar membuka dialogKeranjang bila belum ditangani keranjang.js
            const btnKeranjang = ev.target.closest('#btnBukaKeranjang');
            if (btnKeranjang && !btnKeranjang.dataset.ditanganiKeranjangJs) {
                ev.preventDefault();
                bukaDialog('dialogKeranjang', btnKeranjang);
            }
        });

        // 3. Toggle menu lipat ponsel
        const btnMenuMobile = document.getElementById('btnMenuMobile');
        const menuMobile = document.getElementById('menuMobile');
        const ikonBuka = document.getElementById('ikonMenuBuka');
        const ikonTutup = document.getElementById('ikonMenuTutup');

        if (btnMenuMobile && menuMobile) {
            function setStatusMenuMobile(terbuka) {
                menuMobile.classList.toggle('terbuka', terbuka);
                btnMenuMobile.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
                btnMenuMobile.setAttribute('aria-label', terbuka ? 'Tutup menu navigasi' : 'Buka menu navigasi');
                if (ikonBuka && ikonTutup) {
                    ikonBuka.classList.toggle('hidden', terbuka);
                    ikonTutup.classList.toggle('hidden', !terbuka);
                }
            }

            btnMenuMobile.addEventListener('click', function () {
                const sedangTerbuka = menuMobile.classList.contains('terbuka');
                setStatusMenuMobile(!sedangTerbuka);
            });

            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && menuMobile.classList.contains('terbuka')) {
                    setStatusMenuMobile(false);
                    btnMenuMobile.focus();
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inisialisasiUmum);
    } else {
        inisialisasiUmum();
    }

    // Ekspos utilitas ke namespace global window.SnapRent
    window.SnapRent = {
        csrfToken: csrfToken,
        baseUrl: baseUrl,
        escapeHtml: escapeHtml,
        formatRupiah: formatRupiah,
        fetchJson: fetchJson,
        bukaDialog: bukaDialog,
        tutupDialog: tutupDialog,
        toast: tampilkanToast,
        tampilkanToast: tampilkanToast,
        perbaruiBadgeKeranjang: perbaruiBadgeKeranjang
    };
})(window, document);
