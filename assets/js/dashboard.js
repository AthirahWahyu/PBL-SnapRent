/**
 * assets/js/dashboard.js
 * Fitur auto-refresh berkala (60 detik) untuk dasbor admin SnapRent.
 * Mengambil ringkasan statistik dan data transaksi hari ini tanpa reload halaman.
 */

(function () {
    'use strict';

    /**
     * Escape karakter HTML untuk mencegah kerentanan XSS.
     */
    function escHtml(teks) {
        const div = document.createElement('div');
        div.textContent = teks == null ? '' : String(teks);
        return div.innerHTML;
    }

    /**
     * Helper untuk mengubah teks elemen DOM berdasarkan ID.
     */
    function setEl(id, val) {
        const el = document.getElementById(id);
        if (el && val !== undefined) {
            el.textContent = String(val);
        }
    }

    /**
     * Render ulang isi tabel Rincian Hari Ini dari data API.
     */
    function renderTabelHariIni(listHariIni, tbody) {
        if (!tbody) return;

        if (listHariIni.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="py-16 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <svg class="w-8 h-8 text-gray-400 mb-2.5" viewBox="0 0 24 24" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.478 5.559A1.5 1.5 0 016.912 4.5H9A.75.75 0 009 3H6.912a3 3 0 00-2.868 2.118l-2.411 7.838a3 3 0 00-.133.882V18a3 3 0 003 3h15a3 3 0 003-3v-4.162c0-.299-.045-.596-.133-.882l-2.412-7.838A3 3 0 0017.088 3H15a.75.75 0 000 1.5h2.088a1.5 1.5 0 011.434 1.059l2.213 7.191H17.89a3 3 0 00-2.684 1.658l-.256.513a1.5 1.5 0 01-1.342.829h-3.218a1.5 1.5 0 01-1.342-.83l-.256-.512a3 3 0 00-2.684-1.658H3.265l2.213-7.191z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-xs text-gray-400">Belum ada transaksi sewa untuk hari ini.</p>
                        </div>
                    </td>
                </tr>`;
            return;
        }

        const badgeMap = {
            menunggu_verifikasi: 'badge-menunggu',
            menunggu_diambil: 'badge-disetujui',
            disewa: 'badge-disewa',
            selesai: 'badge-selesai',
            dibatalkan: 'badge-dibatalkan'
        };

        tbody.innerHTML = listHariIni.map((r, idx) => {
            const st = String(r.status_transaksi || '');
            const kelasBaris = r.level_reminder === 'merah'
                ? 'bg-rose-50/70'
                : (r.mendekati_batas ? 'bg-amber-50/70' : 'border-b border-gray-100 hover:bg-gray-50/80');
            
            const kelasBadge = badgeMap[st] || 'badge-normal';
            const labelSt = st.replace(/_/g, ' ').charAt(0).toUpperCase() + st.replace(/_/g, ' ').slice(1);
            const btnKembali = st === 'disewa'
                ? `<a href="${escHtml(r.url_kembali)}" class="btn-utama text-xs py-1.5 px-3.5 mr-1">Kembali</a>`
                : '';

            return `
                <tr class="${kelasBaris} transition-colors">
                    <td class="py-3.5 px-4 font-semibold text-gray-500">${idx + 1}</td>
                    <td class="py-3.5 px-4 font-mono font-bold text-[#111111] whitespace-nowrap">${escHtml(r.kode_transaksi)}</td>
                    <td class="py-3.5 px-4 max-w-xs truncate font-medium text-gray-900">${escHtml(r.daftar_barang)}</td>
                    <td class="py-3.5 px-4">
                        <div class="font-semibold text-gray-900">${escHtml(r.nama_penyewa)}</div>
                        <div class="text-[11px] text-gray-400">${escHtml(r.no_telepon)}</div>
                    </td>
                    <td class="py-3.5 px-4 whitespace-nowrap text-gray-700">${escHtml(r.waktu_mulai_sewa)}</td>
                    <td class="py-3.5 px-4 whitespace-nowrap text-gray-700">${escHtml(r.waktu_rencana_kembali)}</td>
                    <td class="py-3.5 px-4 whitespace-nowrap">
                        <span class="badge ${kelasBadge}">${escHtml(labelSt)}</span>
                    </td>
                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                        ${btnKembali}
                        <a href="${escHtml(r.url_detail)}" class="btn-sekunder text-xs py-1.5 px-3.5">Detail</a>
                    </td>
                </tr>`;
        }).join('');
    }

    /**
     * Panggil API data_dashboard.php dan sinkronkan data ke tampilan.
     */
    async function muatUlangDashboard() {
        if (typeof window.URL_DATA_DASHBOARD === 'undefined' || !window.URL_DATA_DASHBOARD) {
            return;
        }

        try {
            const res = await fetch(window.URL_DATA_DASHBOARD, {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });

            if (!res.ok) return;

            const payload = await res.json();
            if (!payload || payload.status !== 'ok' || !payload.data) return;

            const d = payload.data;
            const k = d.kartu || {};

            // 1. Perbarui 4 Kartu Ringkasan
            setEl('statTotalDisewa', k.total_disewa);
            setEl('statSedangDisewa', k.sedang_disewa);
            setEl('statPendapatanHari', k.pendapatan_hari_penuh);
            setEl('statPelangganBaru', k.pelanggan_baru);

            // 2. Perbarui Tabel Rincian Hari Ini
            const listHariIni = Array.isArray(d.rincian_hari_ini) ? d.rincian_hari_ini : [];
            const tbody = document.getElementById('tbodyRincianHariIni');
            renderTabelHariIni(listHariIni, tbody);

        } catch (err) {
            console.error('Gagal memperbarui dashboard:', err);
        }
    }

    // Jalankan timer refresh setiap 60 detik (60.000 ms)
    setInterval(muatUlangDashboard, 60000);
})();
