<?php
/**
 * Komponen Sidebar Admin - SnapRent
 * File: includes/sidebar.php
 
 * Penggunaan di halaman admin:
 * <?php 
 *   $current_page = 'dashboard'; // Pilihan: 'dashboard', 'inventaris', 'transaksi', 'laporan', 'pengaturan'
 *   include_once '../includes/sidebar.php'; 
 * ?>
 */

// Tentukan base path relatif jika belum didefinisikan (default: '..' karena halaman admin ada di subfolder)
$base_path = $base_path ?? '..';

// Deteksi halaman aktif otomatis berdasarkan URL jika belum diset
if (!isset($current_page)) {
    $currentUri = $_SERVER['PHP_SELF'] ?? '';
    if (strpos($currentUri, '/dashboard/') !== false) {
        $current_page = 'dashboard';
    } elseif (strpos($currentUri, '/barang/') !== false) {
        $current_page = 'inventaris';
    } elseif (strpos($currentUri, '/transaksi/') !== false) {
        $current_page = 'transaksi';
    } elseif (strpos($currentUri, '/laporan/') !== false) {
        $current_page = 'laporan';
    } elseif (strpos($currentUri, '/pengaturan/') !== false) {
        $current_page = 'pengaturan';
    } else {
        $current_page = 'dashboard';
    }
}

// Ambil badge jumlah barang sedang disewa jika fungsi dari data_dashboard.php tersedia
$badgeSedangDisewa = 0;
if (function_exists('getBarangSedangDisewa') && isset($pdo)) {
    try {
        $badgeSedangDisewa = getBarangSedangDisewa($pdo);
    } catch (Exception $e) {
        $badgeSedangDisewa = 0;
    }
}
?>

<!-- SIDEBAR NAVIGASI (Komponen Terpisah - SnapRent Admin) -->
<aside 
    id="sidebarNav" 
    class="w-60 bg-[#e8edf3] shrink-0 flex flex-col transition-all duration-300 z-30 select-none border-r border-[#d2dae4]"
>
    <div class="p-4 sm:p-5 flex-1 flex flex-col gap-2 overflow-y-auto">
        
        <!-- Menu 1: Dashboard -->
        <a 
            href="<?= $base_path; ?>/dashboard/index.php" 
            class="flex items-center gap-3.5 px-4 py-3 rounded-full <?= ($current_page === 'dashboard') ? 'bg-[#cfd8e3] text-neutral-900 font-bold shadow-2xs' : 'text-neutral-700 hover:text-black hover:bg-[#dce3ea] font-medium'; ?> text-sm transition-all"
        >
            <i class="fa-solid fa-house text-base <?= ($current_page === 'dashboard') ? 'text-neutral-900' : 'text-neutral-700'; ?>"></i>
            <span>Dashboard</span>
        </a>

        <!-- Menu 2: Inventaris -->
        <a 
            href="<?= $base_path; ?>/barang/list.php" 
            class="flex items-center gap-3.5 px-4 py-2.5 rounded-full <?= ($current_page === 'inventaris') ? 'bg-[#cfd8e3] text-neutral-900 font-bold shadow-2xs' : 'text-neutral-700 hover:text-black hover:bg-[#dce3ea] font-medium'; ?> text-sm transition-all"
        >
            <i class="fa-solid fa-box text-base <?= ($current_page === 'inventaris') ? 'text-neutral-900' : 'text-neutral-700'; ?>"></i>
            <span>Inventaris</span>
        </a>

        <!-- Menu 3: List barang disewa -->
        <a 
            href="<?= $base_path; ?>/transaksi/list.php" 
            class="flex items-center justify-between px-4 py-2.5 rounded-full <?= ($current_page === 'transaksi') ? 'bg-[#cfd8e3] text-neutral-900 font-bold shadow-2xs' : 'text-neutral-700 hover:text-black hover:bg-[#dce3ea] font-medium'; ?> text-sm transition-all"
        >
            <div class="flex items-center gap-3.5">
                <i class="fa-solid fa-table-list text-base <?= ($current_page === 'transaksi') ? 'text-neutral-900' : 'text-neutral-700'; ?>"></i>
                <span>List barang disewa</span>
            </div>
            <?php if ($badgeSedangDisewa > 0): ?>
                <span class="bg-[#3b82f6] text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-2xs" title="<?= $badgeSedangDisewa; ?> unit sedang disewa">
                    <?= $badgeSedangDisewa; ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Menu 4: Rekapan Bulanan -->
        <a 
            href="<?= $base_path; ?>/laporan/index.php" 
            class="flex items-center gap-3.5 px-4 py-2.5 rounded-full <?= ($current_page === 'laporan') ? 'bg-[#cfd8e3] text-neutral-900 font-bold shadow-2xs' : 'text-neutral-700 hover:text-black hover:bg-[#dce3ea] font-medium'; ?> text-sm transition-all"
        >
            <i class="fa-solid fa-chart-column text-base <?= ($current_page === 'laporan') ? 'text-neutral-900' : 'text-neutral-700'; ?>"></i>
            <span>Rekapan Bulanan</span>
        </a>

        <!-- Menu 5: Pengaturan -->
        <a 
            href="<?= $base_path; ?>/pengaturan/index.php" 
            class="flex items-center gap-3.5 px-4 py-2.5 rounded-full <?= ($current_page === 'pengaturan') ? 'bg-[#cfd8e3] text-neutral-900 font-bold shadow-2xs' : 'text-neutral-700 hover:text-black hover:bg-[#dce3ea] font-medium'; ?> text-sm transition-all"
        >
            <i class="fa-solid fa-gear text-base <?= ($current_page === 'pengaturan') ? 'text-neutral-900' : 'text-neutral-700'; ?>"></i>
            <span>Pengaturan</span>
        </a>

        <!-- Tombol Logout di bagian paling bawah -->
        <div class="mt-auto pt-6 border-t border-neutral-300/60">
            <a 
                href="<?= $base_path; ?>/auth/logout.php" 
                class="flex items-center gap-3.5 px-4 py-2.5 rounded-full text-red-600 hover:bg-red-100/60 text-xs font-semibold transition-colors"
            >
                <i class="fa-solid fa-arrow-right-from-bracket text-sm"></i>
                <span>Keluar (Logout)</span>
            </a>
        </div>
    </div>
</aside>  

