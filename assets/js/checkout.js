document.addEventListener('DOMContentLoaded', () => {
    const inputMulai     = document.getElementById('waktu_mulai_sewa');
    const inputKembali   = document.getElementById('waktu_rencana_kembali');
    const selectSkema    = document.getElementById('skema_pembayaran');
    const hargaPerHariEl = document.getElementById('harga_per_hari_total');
    const previewDurasi  = document.getElementById('preview_durasi');
    const previewTotal   = document.getElementById('preview_total');
    const previewBayar   = document.getElementById('preview_bayar_awal');
    const inputBukti     = document.getElementById('bukti_pembayaran');
    const formCheckout   = document.getElementById('formCheckout');

    const formatRupiah = (angka) => {
        return 'Rp ' + Math.round(angka).toLocaleString('id-ID');
    };

    function hitungTotalSewa() {
        const hargaHarian = parseFloat(hargaPerHariEl?.value || 0);
        let jumlahHari = 1;

        if (inputMulai.value && inputKembali.value) {
            const tMulai = new Date(inputMulai.value);
            const tKembali = new Date(inputKembali.value);
            const selisihMs = tKembali - tMulai;

            if (selisihMs > 0) {
                const jam = selisihMs / (1000 * 60 * 60);
                jumlahHari = Math.max(1, Math.ceil(jam / 24));
            } else {
                previewDurasi.textContent = 'Jadwal tidak valid!';
                previewDurasi.classList.replace('text-primary', 'text-danger');
                return;
            }
        }

        previewDurasi.classList.replace('text-danger', 'text-primary');
        previewDurasi.textContent = jumlahHari + ' Hari';

        const totalTagihan = hargaHarian * jumlahHari;
        const skema = selectSkema ? selectSkema.value : 'lunas';
        const bayarAwal = (skema === 'dp_50') ? (totalTagihan * 0.5) : totalTagihan;

        previewTotal.textContent = formatRupiah(totalTagihan);
        previewBayar.textContent = formatRupiah(bayarAwal);
    }

    if (inputMulai) inputMulai.addEventListener('change', hitungTotalSewa);
    if (inputKembali) inputKembali.addEventListener('change', hitungTotalSewa);
    if (selectSkema) selectSkema.addEventListener('change', hitungTotalSewa);

    // Validasi client-side file bukti pembayaran (JPG/PNG/WEBP, maks 2MB)
    if (inputBukti) {
        inputBukti.addEventListener('change', () => {
            const file = inputBukti.files[0];
            if (!file) return;

            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Format bukti pembayaran harus JPG, PNG, atau WEBP.');
                inputBukti.value = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file maksimal 2MB.');
                inputBukti.value = '';
            }
        });
    }

    if (formCheckout) {
        formCheckout.addEventListener('submit', (e) => {
            if (inputMulai.value && inputKembali.value) {
                if (new Date(inputKembali.value) <= new Date(inputMulai.value)) {
                    e.preventDefault();
                    alert('Tanggal & jam kembali harus setelah tanggal & jam mulai sewa.');
                }
            }
        });
    }

    hitungTotalSewa();
});