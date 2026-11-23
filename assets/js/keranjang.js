(function (window, document) {
    'use strict';

    /**
     * Pastikan elemen <dialog id="dialogDetailProduk"> tersedia di DOM.
     */
    function pastikanDialogDetail() {
        let dialog = document.getElementById('dialogDetailProduk');
        if (dialog) return dialog;

        dialog = document.createElement('dialog');
        dialog.id = 'dialogDetailProduk';
        dialog.className = 'dialog-snaprent dialog-modal';
        dialog.setAttribute('aria-labelledby', 'judulDialogDetailProduk');
        dialog.innerHTML =
            '<div class="dialog-panel">' +
                '<div class="dialog-header">' +
                    '<h2 id="judulDialogDetailProduk" class="dialog-judul">Detail Peralatan</h2>' +
                    '<button type="button" class="btn-tutup-dialog" data-tutup-dialog="dialogDetailProduk" aria-label="Tutup detail peralatan">' +
                        '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>' +
                        '</svg>' +
                    '</button>' +
                '</div>' +
                '<div id="isiDialogDetailProduk" class="dialog-body scroll-halus"></div>' +
            '</div>';
        document.body.appendChild(dialog);
        return dialog;
    }

    /**
     * Ambil nilai rentang waktu dari input katalog bila sedang berada di halaman katalog.
     */
    function ambilRentangDariInputKatalog() {
        const elMulai   = document.getElementById('filterMulai');
        const elSelesai = document.getElementById('filterSelesai');
        return {
            mulai:   elMulai ? elMulai.value.trim() : '',
            selesai: elSelesai ? elSelesai.value.trim() : ''
        };
    }

    /**
     * Update label estimasi harga dan badge durasi di pop-up detail produk.
     */
    function updateEstimasiDetail() {
        const inpDur = document.getElementById('detailInputDurasi');
        const badgeDur = document.getElementById('badgeDurasiDetail');
        const lblEstimasi = document.getElementById('labelEstimasiBiayaDetail');
        const btnKurang = document.getElementById('btnKurangDurasiDetail');
        if (!inpDur || !lblEstimasi) return;

        let hari = parseInt(inpDur.value, 10) || 1;
        if (hari < 1) hari = 1;
        if (hari > 60) hari = 60;
        inpDur.value = String(hari);

        if (badgeDur) {
            badgeDur.textContent = hari + ' Hari';
        }
        if (btnKurang) {
            btnKurang.disabled = (hari <= 1);
        }

        const hargaPerHari = parseFloat(lblEstimasi.getAttribute('data-harga-per-hari') || '0');
        const total = hargaPerHari * hari;
        if (window.SnapRent && window.SnapRent.formatRupiah) {
            lblEstimasi.textContent = window.SnapRent.formatRupiah(total);
        } else {
            lblEstimasi.textContent = 'Rp ' + total.toLocaleString('id-ID');
        }
    }

    /**
     * Buka pop-up <dialog> Detail Produk dan muat konten HTML dari booking/detail.php?id=
     *
     * @param {number|string} idBarang ID peralatan
     * @param {HTMLElement|null} pemicu Tombol pemicu untuk pengembalian fokus
     */
    async function bukaDetail(idBarang, pemicu = null) {
        const id = parseInt(idBarang, 10);
        if (!id || id <= 0) return;

        const dialog = pastikanDialogDetail();
        const wadahIsi = document.getElementById('isiDialogDetailProduk');
        if (!wadahIsi) return;

        wadahIsi.innerHTML =
            '<div class="indikator-memuat-katalog py-10">' +
                '<span class="spinner-katalog" aria-hidden="true"></span>' +
                '<span>Memuat spesifikasi peralatan...</span>' +
            '</div>';

        if (window.SnapRent && window.SnapRent.bukaDialog) {
            window.SnapRent.bukaDialog(dialog, pemicu);
        }

        const rentang = ambilRentangDariInputKatalog();
        const params = new URLSearchParams();
        params.set('id', String(id));
        if (rentang.mulai !== '' && rentang.selesai !== '') {
            params.set('mulai', rentang.mulai);
            params.set('selesai', rentang.selesai);
        }

        const url = (window.SnapRent ? window.SnapRent.baseUrl('booking/detail.php') : 'booking/detail.php') + '?' + params.toString();

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const html = await response.text();
            wadahIsi.innerHTML = html;
        } catch (_err) {
            wadahIsi.innerHTML =
                '<div class="keadaan-kosong">' +
                    '<p class="keadaan-kosong__judul">Gagal memuat detail</p>' +
                    '<p class="keadaan-kosong__teks">Periksa koneksi jaringan Anda lalu coba lagi.</p>' +
                '</div>';
        }
    }

    /**
     * Muat ulang potongan HTML isi keranjang dari keranjang/isi.php ke dalam #isiDialogKeranjang.
     */
    async function muatIsiKeranjang() {
        const wadahIsi = document.getElementById('isiDialogKeranjang');
        if (!wadahIsi) return;

        wadahIsi.innerHTML =
            '<div class="indikator-memuat-katalog py-10">' +
                '<span class="spinner-katalog" aria-hidden="true"></span>' +
                '<span>Memeriksa isi keranjang &amp; ketersediaan...</span>' +
            '</div>';

        const url = window.SnapRent ? window.SnapRent.baseUrl('keranjang/isi.php') : 'keranjang/isi.php';

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const html = await response.text();
            wadahIsi.innerHTML = html;

            // Sinkronkan badge keranjang di header dengan jumlah aktual hasil validasi server
            const rootKeranjang = wadahIsi.querySelector('[data-jumlah-keranjang]');
            if (rootKeranjang && window.SnapRent && window.SnapRent.perbaruiBadgeKeranjang) {
                const jml = parseInt(rootKeranjang.getAttribute('data-jumlah-keranjang') || '0', 10) || 0;
                window.SnapRent.perbaruiBadgeKeranjang(jml);
            }
        } catch (_err) {
            wadahIsi.innerHTML =
                '<div class="keadaan-kosong">' +
                    '<p class="keadaan-kosong__judul">Gagal memuat keranjang</p>' +
                    '<p class="keadaan-kosong__teks">Periksa koneksi jaringan Anda lalu coba lagi.</p>' +
                '</div>';
        }
    }

    /**
     * Buka drawer <dialog id="dialogKeranjang"> dan muat isi terbarunya.
     *
     * @param {HTMLElement|null} pemicu Elemen tombol pemicu
     */
    async function bukaKeranjang(pemicu = null) {
        if (window.SnapRent && window.SnapRent.bukaDialog) {
            window.SnapRent.bukaDialog('dialogKeranjang', pemicu);
        }
        await muatIsiKeranjang();
    }

    /**
     * Tambahkan barang ke keranjang lewat POST + CSRF ke keranjang/proses_tambah.php.
     *
     * @param {number|string} idBarang ID peralatan
     * @param {HTMLElement|null} tombol Tombol yang diklik
     * @param {boolean} bukaKeranjangSetelahnya Buka drawer keranjang bila berhasil
     * @param {boolean} langsungCheckout Langsung alihkan ke halaman booking/checkout.php
     */
    async function tambahKeKeranjang(idBarang, tombol = null, bukaKeranjangSetelahnya = false, langsungCheckout = false) {
        const id = parseInt(idBarang, 10);
        if (!id || id <= 0 || !window.SnapRent) return;

        let rentang = ambilRentangDariInputKatalog();

        // Cek apakah ada input tanggal & durasi di dalam pop-up detail produk
        const inpTglDetail = document.getElementById('detailInputTanggal');
        const inpDurDetail = document.getElementById('detailInputDurasi');
        let durasiKirim = null;

        if (inpTglDetail && inpTglDetail.value) {
            rentang.mulai = inpTglDetail.value.trim();
        }
        if (inpDurDetail && inpDurDetail.value) {
            durasiKirim = parseInt(inpDurDetail.value, 10) || 1;
        }

        if (tombol) tombol.disabled = true;

        const payload = {
            id_barang: id,
            mulai: rentang.mulai,
            selesai: rentang.selesai
        };
        if (durasiKirim !== null) {
            payload.durasi_hari = durasiKirim;
            if (rentang.mulai) {
                payload.tanggal_mulai = rentang.mulai;
            }
        }

        const url = window.SnapRent.baseUrl('keranjang/proses_tambah.php');
        const res = await window.SnapRent.fetchJson(url, {
            method: 'POST',
            data: payload
        });

        if (tombol) tombol.disabled = false;

        if (!res || res.status !== 'ok' || !res.data) {
            window.SnapRent.toast((res && res.message) || 'Gagal menambahkan barang ke keranjang.', 'error');
            return;
        }

        window.SnapRent.perbaruiBadgeKeranjang(res.data.jumlah_item);

        const dialogDetail = document.getElementById('dialogDetailProduk');
        if (dialogDetail && dialogDetail.open) {
            window.SnapRent.tutupDialog(dialogDetail);
        }

        if (langsungCheckout) {
            // Langsung arahkan ke halaman checkout
            window.location.href = window.SnapRent.baseUrl('booking/checkout.php');
            return;
        }

        window.SnapRent.toast(res.data.message || 'Barang ditambahkan ke keranjang.', 'sukses');

        if (bukaKeranjangSetelahnya) {
            await bukaKeranjang(document.getElementById('btnBukaKeranjang'));
        }
    }

    /**
     * Hapus satu barang dari keranjang lewat POST + CSRF ke keranjang/proses_hapus.php.
     *
     * @param {number|string} idBarang ID peralatan
     * @param {HTMLElement|null} tombol Tombol hapus yang diklik
     */
    async function hapusDariKeranjang(idBarang, tombol = null) {
        const id = parseInt(idBarang, 10);
        if (!id || id <= 0 || !window.SnapRent) return;

        if (tombol) tombol.disabled = true;

        const url = window.SnapRent.baseUrl('keranjang/proses_hapus.php');
        const res = await window.SnapRent.fetchJson(url, {
            method: 'POST',
            data: { id_barang: id }
        });

        if (tombol) tombol.disabled = false;

        if (!res || res.status !== 'ok' || !res.data) {
            window.SnapRent.toast((res && res.message) || 'Gagal menghapus barang dari keranjang.', 'error');
            return;
        }

        window.SnapRent.perbaruiBadgeKeranjang(res.data.jumlah_item);
        window.SnapRent.toast(res.data.message || 'Barang dihapus dari keranjang.', 'info');
        await muatIsiKeranjang();
    }

    /**
     * Kosongkan seluruh isi keranjang lewat POST + CSRF ke keranjang/proses_hapus.php.
     */
    async function kosongkanKeranjang(tombol = null) {
        if (!window.SnapRent) return;
        if (tombol) tombol.disabled = true;

        const url = window.SnapRent.baseUrl('keranjang/proses_hapus.php');
        const res = await window.SnapRent.fetchJson(url, {
            method: 'POST',
            data: { aksi: 'kosongkan' }
        });

        if (tombol) tombol.disabled = false;

        if (!res || res.status !== 'ok' || !res.data) {
            window.SnapRent.toast((res && res.message) || 'Gagal mengosongkan keranjang.', 'error');
            return;
        }

        window.SnapRent.perbaruiBadgeKeranjang(0);
        window.SnapRent.toast(res.data.message || 'Keranjang dikosongkan.', 'info');
        await muatIsiKeranjang();
    }

    /**
     * Perbarui tanggal ambil (Hari-H) langsung dari dalam Keranjang.
     */
    async function perbaruiJadwalKeranjang() {
        const elTanggal = document.getElementById('keranjangInputTanggalMulai');
        if (!elTanggal || !window.SnapRent) return;

        const tanggalMulai = elTanggal.value.trim();

        const url = window.SnapRent.baseUrl('keranjang/proses_tambah.php');
        const res = await window.SnapRent.fetchJson(url, {
            method: 'POST',
            data: {
                aksi: 'atur_jadwal',
                tanggal_mulai: tanggalMulai
            }
        });

        if (!res || res.status !== 'ok') {
            window.SnapRent.toast((res && res.message) || 'Gagal memperbarui tanggal ambil.', 'error');
            return;
        }

        await muatIsiKeranjang();
    }

    /**
     * Ubah durasi hari sewa khusus 1 barang di dalam keranjang.
     */
    async function ubahDurasiItem(idBarang, durasiBaru, tombol = null) {
        const id   = parseInt(idBarang, 10);
        const hari = parseInt(durasiBaru, 10);
        if (!id || id <= 0 || Number.isNaN(hari) || !window.SnapRent) return;

        if (tombol) tombol.disabled = true;

        const url = window.SnapRent.baseUrl('keranjang/proses_tambah.php');
        const res = await window.SnapRent.fetchJson(url, {
            method: 'POST',
            data: {
                aksi: 'ubah_durasi_item',
                id_barang: id,
                durasi_hari: Math.max(1, Math.min(60, hari))
            }
        });

        if (tombol) tombol.disabled = false;

        if (!res || res.status !== 'ok') {
            window.SnapRent.toast((res && res.message) || 'Gagal memperbarui durasi barang.', 'error');
            return;
        }

        await muatIsiKeranjang();
    }

    /**
     * Ubah jumlah unit barang di dalam keranjang sesuai sisa stok.
     */
    async function ubahJumlahUnit(idBarang, jumlahBaru, tombol = null) {
        const id  = parseInt(idBarang, 10);
        const qty = parseInt(jumlahBaru, 10);
        if (!id || id <= 0 || Number.isNaN(qty) || !window.SnapRent) return;

        if (tombol) tombol.disabled = true;

        const url = window.SnapRent.baseUrl('keranjang/proses_tambah.php');
        const res = await window.SnapRent.fetchJson(url, {
            method: 'POST',
            data: {
                aksi: 'ubah_jumlah',
                id_barang: id,
                jumlah: qty
            }
        });

        if (tombol) tombol.disabled = false;

        if (!res || res.status !== 'ok' || !res.data) {
            window.SnapRent.toast((res && res.message) || 'Gagal mengubah jumlah unit.', 'error');
            return;
        }

        window.SnapRent.perbaruiBadgeKeranjang(res.data.jumlah_item);
        await muatIsiKeranjang();
    }

    /**
     * Pasang listener delegasi klik untuk keranjang & pop-up detail.
     */
    function initKeranjang() {
        pastikanDialogDetail();

        const btnHeaderKeranjang = document.getElementById('btnBukaKeranjang');
        if (btnHeaderKeranjang) {
            btnHeaderKeranjang.dataset.ditanganiKeranjangJs = '1';
        }

        document.addEventListener('change', function (ev) {
            if (ev.target && ev.target.id === 'keranjangInputTanggalMulai') {
                perbaruiJadwalKeranjang();
            }
        });

        // Event listener delegasi input durasi di pop-up detail produk
        document.addEventListener('input', function (ev) {
            if (ev.target && ev.target.id === 'detailInputDurasi') {
                updateEstimasiDetail();
            }
        });

        document.addEventListener('click', function (ev) {
            // Tombol +/- durasi di dalam pop-up detail
            if (ev.target && ev.target.id === 'btnKurangDurasiDetail') {
                ev.preventDefault();
                const inp = document.getElementById('detailInputDurasi');
                if (inp) {
                    inp.value = String(Math.max(1, (parseInt(inp.value, 10) || 1) - 1));
                    updateEstimasiDetail();
                }
                return;
            }
            if (ev.target && ev.target.id === 'btnTambahDurasiDetail') {
                ev.preventDefault();
                const inp = document.getElementById('detailInputDurasi');
                if (inp) {
                    inp.value = String(Math.min(60, (parseInt(inp.value, 10) || 1) + 1));
                    updateEstimasiDetail();
                }
                return;
            }

            // Klik tombol "Pesan Sekarang" (Langsung ke checkout)
            const btnPesanLangsung = ev.target.closest('[data-aksi="pesan-langsung"][data-id]');
            if (btnPesanLangsung) {
                ev.preventDefault();
                if (btnPesanLangsung.disabled) return;
                const idBrg = btnPesanLangsung.getAttribute('data-id');
                tambahKeKeranjang(idBrg, btnPesanLangsung, false, true);
                return;
            }
            // Tombol +/- durasi hari sewa khusus 1 barang di keranjang
            const btnDurasiItem = ev.target.closest('[data-ubah-durasi-item]');
            if (btnDurasiItem) {
                ev.preventDefault();
                if (btnDurasiItem.disabled) return;
                const idBrg        = btnDurasiItem.getAttribute('data-id');
                const hariSekarang = parseInt(btnDurasiItem.getAttribute('data-hari-sekarang') || '1', 10) || 1;
                const delta        = parseInt(btnDurasiItem.getAttribute('data-ubah-durasi-item') || '0', 10) || 0;
                ubahDurasiItem(idBrg, hariSekarang + delta, btnDurasiItem);
                return;
            }
            // Tombol +/- jumlah unit barang di dalam keranjang
            const btnQty = ev.target.closest('[data-ubah-qty]');
            if (btnQty) {
                ev.preventDefault();
                if (btnQty.disabled) return;
                const idBrg       = btnQty.getAttribute('data-id');
                const qtySekarang = parseInt(btnQty.getAttribute('data-qty-sekarang') || '1', 10) || 1;
                const delta       = parseInt(btnQty.getAttribute('data-ubah-qty') || '0', 10) || 0;
                ubahJumlahUnit(idBrg, qtySekarang + delta, btnQty);
                return;
            }

            // 1. Klik tombol ikon keranjang di header
            const btnBuka = ev.target.closest('#btnBukaKeranjang, [data-buka-keranjang]');
            if (btnBuka) {
                ev.preventDefault();
                bukaKeranjang(btnBuka);
                return;
            }

            // 2. Klik tombol "Tambah ke Keranjang" (baik di kartu katalog maupun di dalam pop-up detail)
            const btnTambah = ev.target.closest('[data-aksi="tambah-keranjang"][data-id]');
            if (btnTambah) {
                ev.preventDefault();
                if (btnTambah.disabled) return;
                const idBrg = btnTambah.getAttribute('data-id');
                const bukaSetelah = btnTambah.getAttribute('data-buka-setelah-tambah') === '1';
                tambahKeKeranjang(idBrg, btnTambah, bukaSetelah);
                return;
            }

            // 3. Klik tombol hapus 1 barang di dalam pop-up keranjang
            const btnHapus = ev.target.closest('[data-hapus-keranjang]');
            if (btnHapus) {
                ev.preventDefault();
                const idBrg = btnHapus.getAttribute('data-hapus-keranjang');
                hapusDariKeranjang(idBrg, btnHapus);
                return;
            }

            // 4. Klik tombol kosongkan seluruh keranjang
            const btnKosongkan = ev.target.closest('[data-kosongkan-keranjang]');
            if (btnKosongkan) {
                ev.preventDefault();
                kosongkanKeranjang(btnKosongkan);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initKeranjang);
    } else {
        initKeranjang();
    }

    window.SnapRentKeranjang = {
        bukaDetail: bukaDetail,
        bukaKeranjang: bukaKeranjang,
        muatIsiKeranjang: muatIsiKeranjang,
        tambahKeKeranjang: tambahKeKeranjang,
        hapusDariKeranjang: hapusDariKeranjang,
        kosongkanKeranjang: kosongkanKeranjang
    };
})(window, document);
