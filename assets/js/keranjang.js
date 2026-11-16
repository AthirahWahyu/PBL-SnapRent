async function muatUlangMiniCart() {
    const container = document.getElementById('miniCartContainer');
    if (!container) return;

    try {
        const res = await fetch('/keranjang/isi.php');
        const html = await res.text();
        container.innerHTML = html;
    } catch (err) {
        console.error('Gagal memuat ulang keranjang:', err);
    }
}

function updateBadgeKeranjang(totalItem) {
    const badge = document.getElementById('cartBadge');
    if (badge) {
        badge.textContent = totalItem;
    }
}

async function tambahKeKeranjang(idBarang, jumlah = 1, mode = 'tambah') {
    const formData = new FormData();
    formData.append('id_barang', idBarang);
    formData.append('jumlah', jumlah);
    formData.append('mode', mode);

    try {
        const response = await fetch('/keranjang/proses_tambah.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            updateBadgeKeranjang(data.total_item);
            await muatUlangMiniCart();
            alert(data.message);
        } else {
            alert(data.message || 'Gagal menambahkan barang.');
        }
    } catch (error) {
        console.error(error);
        alert('Terjadi kesalahan koneksi saat menambah ke keranjang.');
    }
}

async function pesanSekarang(idBarang, jumlah = 1) {
    const formData = new FormData();
    formData.append('id_barang', idBarang);
    formData.append('jumlah', jumlah);
    formData.append('mode', 'tambah');

    try {
        const response = await fetch('/keranjang/proses_tambah.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            window.location.href = '/booking/checkout.php';
        } else {
            alert(data.message || 'Stok tidak mencukupi.');
        }
    } catch (error) {
        console.error(error);
        alert('Terjadi kesalahan jaringan.');
    }
}

async function ubahJumlahKeranjang(idBarang, aksi) {
    if (aksi === 'tambah') {
        await tambahKeKeranjang(idBarang, 1, 'tambah');
        return;
    }

    const formData = new FormData();
    formData.append('id_barang', idBarang);
    formData.append('aksi', 'kurangi');

    try {
        const response = await fetch('/keranjang/proses_hapus.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.status === 'success') {
            updateBadgeKeranjang(data.total_item);
            await muatUlangMiniCart();
        }
    } catch (error) {
        console.error(error);
    }
}

async function hapusItemKeranjang(idBarang) {
    const formData = new FormData();
    formData.append('id_barang', idBarang);
    formData.append('aksi', 'hapus_item');

    try {
        const response = await fetch('/keranjang/proses_hapus.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.status === 'success') {
            updateBadgeKeranjang(data.total_item);
            await muatUlangMiniCart();
        }
    } catch (error) {
        console.error(error);
    }
}

async function kosongkanKeranjang() {
    if (!confirm('Kosongkan semua item di keranjang?')) return;

    const formData = new FormData();
    formData.append('aksi', 'kosongkan');

    try {
        const response = await fetch('/keranjang/proses_hapus.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.status === 'success') {
            updateBadgeKeranjang(0);
            await muatUlangMiniCart();
        }
    } catch (error) {
        console.error(error);
    }
}