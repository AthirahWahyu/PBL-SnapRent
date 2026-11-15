/**
 * assets/js/katalog.js
 * Interaksi Halaman Katalog Pelanggan (Tahap C2):
 * 1. Validasi rentang waktu di sisi browser (waktu mulai tidak di masa lalu, selesai > mulai)
 * 2. Penerapan filter kategori, pencarian nama, dan rentang tanggal tanpa reload via fetch JSON
 * 3. Penanganan keadaan memuat (loading), keadaan kosong (empty state), dan tombol "Muat lebih banyak" (24 kartu/halaman)
 */

(function (window, document) {
    'use strict';

    function initKatalog() {
        const formFilter         = document.getElementById('formFilterKatalog');
        if (!formFilter) return;

        const inputMulai         = document.getElementById('filterMulai');
        const inputSelesai       = document.getElementById('filterSelesai');
        const selectKategori     = document.getElementById('filterKategori');
        const inputCari          = document.getElementById('filterCari');
        const btnReset           = document.getElementById('btnResetFilter');
        const btnResetKosong     = document.getElementById('btnResetDariKosong');
        const btnMuatLebih       = document.getElementById('btnMuatLebihBanyak');
        const wadahMuatLebih     = document.getElementById('wadahMuatLebihBanyak');

        const kotakInfoJadwal    = document.getElementById('kotakInfoJadwal');
        const teksInfoJadwal     = document.getElementById('teksInfoJadwal');
        const kotakErrorValidasi = document.getElementById('kotakErrorValidasi');
        const indikatorMemuat    = document.getElementById('indikatorMemuatKatalog');
        const wadahHasil         = document.getElementById('wadahHasilKatalog');
        const gridKatalog        = document.getElementById('gridKatalog');
        const keadaanKosong      = document.getElementById('keadaanKosongKatalog');

        const elAngkaDitampilkan = document.getElementById('angkaDitampilkan');
        const elAngkaTotal       = document.getElementById('angkaTotalBarang');
        const elAngkaTersedia    = document.getElementById('angkaTotalTersedia');

        let timerDebounceCari = null;
        let sedangMemuat      = false;

        /**
         * Tampilkan atau sembunyikan pesan validasi tanggal di bawah baris filter.
         */
        function setPesanErrorValidasi(pesan) {
            if (!kotakErrorValidasi) return;
            if (pesan && String(pesan).trim() !== '') {
                kotakErrorValidasi.textContent = String(pesan);
                kotakErrorValidasi.classList.remove('hidden');
            } else {
                kotakErrorValidasi.textContent = '';
                kotakErrorValidasi.classList.add('hidden');
            }
        }

        /**
         * Perbarui visibilitas tombol "Reset Filter".
         */
        function perbaruiTombolReset() {
            if (!btnReset) return;
            const adaIsi = Boolean(
                (inputMulai && inputMulai.value.trim() !== '') ||
                (inputSelesai && inputSelesai.value.trim() !== '') ||
                (selectKategori && selectKategori.value !== '') ||
                (inputCari && inputCari.value.trim() !== '')
            );
            btnReset.classList.toggle('hidden', !adaIsi);
        }

        /**
         * Validasi rentang waktu di sisi browser.
         * Mengembalikan { valid: boolean, pesan: string|null, lengkap: boolean }
         */
        function validasiRentangBrowser() {
            const valMulai   = inputMulai ? inputMulai.value.trim() : '';
            const valSelesai = inputSelesai ? inputSelesai.value.trim() : '';

            // Sinkronkan atribut min pada input selesai
            if (inputMulai && inputSelesai && valMulai !== '') {
                inputSelesai.min = valMulai;
            }

            if (valMulai === '' && valSelesai === '') {
                setPesanErrorValidasi(null);
                return { valid: true, pesan: null, lengkap: false };
            }

            if (valMulai === '' || valSelesai === '') {
                const pesan = 'Silakan lengkapi Tanggal & Jam Mulai serta Tanggal & Jam Selesai secara berpasangan untuk mengecek ketersediaan kamera.';
                setPesanErrorValidasi(pesan);
                return { valid: false, pesan: pesan, lengkap: false };
            }

            const tsMulai   = Date.parse(valMulai);
            const tsSelesai = Date.parse(valSelesai);

            if (Number.isNaN(tsMulai) || Number.isNaN(tsSelesai)) {
                const pesan = 'Format tanggal dan jam tidak valid. Pilih tanggal dan jam melalui kalender input.';
                setPesanErrorValidasi(pesan);
                return { valid: false, pesan: pesan, lengkap: false };
            }

            // Toleransi 5 menit dari waktu sekarang
            const batasSekarang = Date.now() - (5 * 60 * 1000);
            if (tsMulai < batasSekarang) {
                const pesan = 'Waktu mulai sewa tidak boleh berada di masa lalu. Silakan pilih tanggal dan jam mulai mulai dari waktu sekarang atau setelahnya.';
                setPesanErrorValidasi(pesan);
                return { valid: false, pesan: pesan, lengkap: false };
            }

            if (tsSelesai <= tsMulai) {
                const pesan = 'Waktu selesai sewa harus lebih besar dari waktu mulai sewa. Silakan pilih tanggal atau jam kembali setelah waktu mulai.';
                setPesanErrorValidasi(pesan);
                return { valid: false, pesan: pesan, lengkap: false };
            }

            setPesanErrorValidasi(null);
            return { valid: true, pesan: null, lengkap: true };
        }

        /**
         * Muat data katalog dari server menggunakan fetch tanpa reload halaman.
         *
         * @param {object} opsi { halaman: number, append: boolean, resetJadwal: boolean }
         */
        async function muatKatalog(opsi = {}) {
            const halaman     = opsi.halaman || 1;
            const modeAppend  = Boolean(opsi.append);
            const resetJadwal = Boolean(opsi.resetJadwal);

            const cekTanggal = validasiRentangBrowser();
            perbaruiTombolReset();

            // Jika pengguna baru mengisi separuh tanggal atau tanggal tidak valid,
            // jangan kirim rentang tanggal yang salah ke server kecuali pengguna mengganti kategori/pencarian
            const params = new URLSearchParams();
            params.set('ajax', '1');
            params.set('page', String(halaman));

            if (resetJadwal) {
                params.set('reset_jadwal', '1');
            } else if (cekTanggal.valid) {
                params.set('mulai', inputMulai ? inputMulai.value.trim() : '');
                params.set('selesai', inputSelesai ? inputSelesai.value.trim() : '');
            } else if (opsi.dariSubmitTanggal) {
                // Hentikan request bila pengguna menekan submit dengan tanggal yang tidak valid
                return;
            }

            if (selectKategori && selectKategori.value !== '') {
                params.set('kategori', selectKategori.value);
            }
            if (inputCari && inputCari.value.trim() !== '') {
                params.set('q', inputCari.value.trim());
            }

            sedangMemuat = true;
            if (indikatorMemuat) indikatorMemuat.classList.remove('hidden');
            if (wadahHasil && !modeAppend) wadahHasil.classList.add('katalog-sedang-memuat');
            if (btnMuatLebih) btnMuatLebih.disabled = true;

            const urlFetch = (window.SnapRent ? window.SnapRent.baseUrl('index.php') : 'index.php') + '?' + params.toString();
            const res = window.SnapRent
                ? await window.SnapRent.fetchJson(urlFetch, { method: 'GET' })
                : { status: 'error', message: 'Utilitas aplikasi belum siap.' };

            sedangMemuat = false;
            if (indikatorMemuat) indikatorMemuat.classList.add('hidden');
            if (wadahHasil) wadahHasil.classList.remove('katalog-sedang-memuat');
            if (btnMuatLebih) btnMuatLebih.disabled = false;

            if (!res || res.status !== 'ok' || !res.data) {
                if (window.SnapRent && window.SnapRent.toast) {
                    window.SnapRent.toast((res && res.message) || 'Gagal memuat katalog.', 'error');
                }
                return;
            }

            const data = res.data;

            // Tampilkan error validasi server bila ada (atau pertahankan pesan validasi browser bila tanggal belum lengkap)
            if (data.validasi_error) {
                setPesanErrorValidasi(data.validasi_error);
            } else if (cekTanggal.valid) {
                setPesanErrorValidasi(null);
            }

            // Perbarui kotak info jadwal
            if (teksInfoJadwal && typeof data.teks_info_jadwal === 'string') {
                teksInfoJadwal.textContent = data.teks_info_jadwal;
            }
            if (kotakInfoJadwal) {
                kotakInfoJadwal.classList.toggle('info-jadwal-katalog--aktif', Boolean(data.rentang_aktif));
            }

            // Perbarui kartu di grid
            if (gridKatalog) {
                if (modeAppend) {
                    gridKatalog.insertAdjacentHTML('beforeend', data.html_kartu || '');
                } else {
                    gridKatalog.innerHTML = data.html_kartu || '';
                }
                gridKatalog.dataset.halamanSekarang = String(data.halaman_sekarang || 1);
                gridKatalog.dataset.totalHalaman = String(data.total_halaman || 1);
                gridKatalog.classList.toggle('hidden', Number(data.total_barang) === 0);
            }

            // Keadaan kosong
            if (keadaanKosong) {
                keadaanKosong.classList.toggle('hidden', Number(data.total_barang) > 0);
            }

            // Perbarui angka ringkasan
            if (elAngkaDitampilkan) elAngkaDitampilkan.textContent = String(data.ditampilkan_sampai ?? 0);
            if (elAngkaTotal)       elAngkaTotal.textContent       = String(data.total_barang ?? 0);
            if (elAngkaTersedia)    elAngkaTersedia.textContent    = String(data.total_tersedia ?? 0);

            // Tombol "Muat lebih banyak"
            if (wadahMuatLebih && btnMuatLebih) {
                if (data.ada_lagi) {
                    wadahMuatLebih.classList.remove('hidden');
                    btnMuatLebih.dataset.nextPage = String((Number(data.halaman_sekarang) || 1) + 1);
                } else {
                    wadahMuatLebih.classList.add('hidden');
                }
            }

            // Perbarui URL di address bar tanpa reload
            if (window.history && typeof window.history.replaceState === 'function') {
                const urlBersih = new URLSearchParams();
                if (cekTanggal.valid && cekTanggal.lengkap) {
                    urlBersih.set('mulai', inputMulai.value.trim());
                    urlBersih.set('selesai', inputSelesai.value.trim());
                }
                if (selectKategori && selectKategori.value !== '') {
                    urlBersih.set('kategori', selectKategori.value);
                }
                if (inputCari && inputCari.value.trim() !== '') {
                    urlBersih.set('q', inputCari.value.trim());
                }
                const qs = urlBersih.toString();
                const pathBaru = window.location.pathname + (qs ? '?' + qs : '');
                window.history.replaceState(null, '', pathBaru);
            }
        }

        // 1. Submit form filter
        formFilter.addEventListener('submit', function (ev) {
            ev.preventDefault();
            const cek = validasiRentangBrowser();
            if (!cek.valid) return;
            muatKatalog({ halaman: 1, append: false, dariSubmitTanggal: true });
        });

        // 2. Perubahan input tanggal mulai / selesai
        [inputMulai, inputSelesai].forEach(function (el) {
            if (!el) return;
            el.addEventListener('change', function () {
                const cek = validasiRentangBrowser();
                perbaruiTombolReset();
                if (cek.valid) {
                    muatKatalog({
                        halaman: 1,
                        append: false,
                        resetJadwal: !cek.lengkap
                    });
                }
            });
        });

        // 3. Perubahan dropdown kategori
        if (selectKategori) {
            selectKategori.addEventListener('change', function () {
                muatKatalog({ halaman: 1, append: false });
            });
        }

        // 4. Input pencarian nama barang (debounce 280ms)
        if (inputCari) {
            inputCari.addEventListener('input', function () {
                perbaruiTombolReset();
                if (timerDebounceCari) clearTimeout(timerDebounceCari);
                timerDebounceCari = setTimeout(function () {
                    muatKatalog({ halaman: 1, append: false });
                }, 280);
            });
        }

        // 5. Tombol Reset Filter & Reset dari Keadaan Kosong
        function lakukanResetFilter() {
            if (inputMulai)     inputMulai.value = '';
            if (inputSelesai)   inputSelesai.value = '';
            if (selectKategori) selectKategori.value = '';
            if (inputCari)      inputCari.value = '';
            setPesanErrorValidasi(null);
            perbaruiTombolReset();
            muatKatalog({ halaman: 1, append: false, resetJadwal: true });
        }

        if (btnReset) {
            btnReset.addEventListener('click', lakukanResetFilter);
        }
        if (btnResetKosong) {
            btnResetKosong.addEventListener('click', lakukanResetFilter);
        }

        // 6. Tombol "Muat lebih banyak" (24 kartu berikutnya)
        if (btnMuatLebih) {
            btnMuatLebih.addEventListener('click', function () {
                if (sedangMemuat) return;
                const nextPage = parseInt(btnMuatLebih.dataset.nextPage || '2', 10) || 2;
                muatKatalog({ halaman: nextPage, append: true });
            });
        }

        // 7. Pastikan keranjang.js dimuat dan sambungkan tombol "Detail & Sewa" (Tahap C3)
        if (!window.SnapRentKeranjang && !document.querySelector('script[src*="assets/js/keranjang.js"]')) {
            const skripKeranjang = document.createElement('script');
            skripKeranjang.src = window.SnapRent ? window.SnapRent.baseUrl('assets/js/keranjang.js') : 'assets/js/keranjang.js';
            skripKeranjang.defer = true;
            document.head.appendChild(skripKeranjang);
        }

        if (gridKatalog) {
            gridKatalog.addEventListener('click', function (ev) {
                const btnDetail = ev.target.closest('.btn-detail-sewa[data-id]');
                if (!btnDetail) return;
                ev.preventDefault();
                const idBrg = btnDetail.getAttribute('data-id');
                if (window.SnapRentKeranjang && typeof window.SnapRentKeranjang.bukaDetail === 'function') {
                    window.SnapRentKeranjang.bukaDetail(idBrg, btnDetail);
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initKatalog);
    } else {
        initKatalog();
    }
})(window, document);
