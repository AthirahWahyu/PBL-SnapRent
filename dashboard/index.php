<?php
session_start();

// Pastikan admin sudah login sebelum mengakses halaman ini
if (!isset($_SESSION['admin'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Ambil informasi admin yang sedang login
$adminSession = $_SESSION['admin'];
$namaAdmin = !empty($adminSession['nama_lengkap']) ? $adminSession['nama_lengkap'] : 'Administrator';
$usernameAdmin = !empty($adminSession['username']) ? $adminSession['username'] : 'admin';

// Inisialisasi variabel data metrik & tabel
$totalPesanan = 0;
$barangDisewa = 0;
$pendapatanHariIni = 0;
$pelangganBaru = 0;
$rincianHariIni = [];
$dbError = null;

// Panggil koneksi dan logika backend dari data_dashboard.php
try {
    require_once '../includes/koneksi.php';
    require_once 'data_dashboard.php';

    if (isset($pdo)) {
        $totalPesanan = getTotalPesananHariIni($pdo);
        $barangDisewa = getBarangSedangDisewa($pdo);
        $pendapatanHariIni = getPendapatanHariIni($pdo);
        $pelangganBaru = getPelangganBaruHariIni($pdo);
        $rincianHariIni = getRincianPenyewaanHariIni($pdo);
    }
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// Fungsi bantu format tanggal ke d/m/Y 
function formatTgl($str) {
    if (!$str) return '-';
    $timestamp = strtotime($str);
    return date('j/n/Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SnapRent</title>

    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        navyDark: '#1a1f26',
                        sidebarBg: '#e8edf3',
                        sidebarActive: '#cfd8e3',
                        contentBg: '#d6dde6',
                        tableHeader: '#2f3b4c',
                    }
                }
            }
        }
    </script>

    <!-- FontAwesome ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #d6dde6;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 7px;
            height: 7px;
        }
        ::-webkit-scrollbar-track {
            background: #d6dde6;
        }
        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased selection:bg-neutral-800 selection:text-white">

    <!-- 1. TOP NAVBAR -->
    <header class="bg-[#000000] text-white h-16 sticky top-0 z-40 px-5 sm:px-7 flex items-center justify-between shadow-md">
        
<!--1a1f26 -->
        <!-- Bagian Kiri: SnapRent Logo & Hamburger Menu Button -->
        <div class="flex items-center gap-5">
            <a href="index.php" class="flex items-center gap-2">
                <span class="text-2xl font-bold tracking-tight text-white">SnapRent</span>
            </a>
            
            <!-- Tombol Hamburger Baris 3 -->
            <button 
                id="sidebarToggleBtn" 
                class="text-white hover:text-white p-1.5 rounded-lg hover:bg-neutral-800/80 transition-colors focus:outline-none"
                title="Buka / Tutup Sidebar"
                aria-label="Toggle Sidebar"
            >
                <i class="fa-solid fa-bars text-xl"></i>
            </button>
        </div>

        <!-- Bagian Kanan: Admin Profile -->
        <div class="relative">
            <button 
                id="adminProfileBtn" 
                class="flex items-center gap-3 text-right hover:opacity-90 transition-opacity focus:outline-none select-none"
                aria-haspopup="true"
                aria-expanded="false"
            >
                <div class="text-right leading-tight">
                    <p class="text-sm font-semibold text-white tracking-wide"><?= htmlspecialchars($usernameAdmin); ?></p>
                    <p class="text-[11px] text-neutral-400 font-normal">Profile</p>
                </div>
                <!-- Avatar Profil Melingkar -->
                <div class="w-9 h-9 rounded-full border-2 border-white flex items-center justify-center bg-black overflow-hidden shadow-sm">
                    <i class="fa-solid fa-user text-white text-base"></i>
                </div>
            </button>

            <!-- Dropdown Menu Profil Admin -->
            <div 
                id="adminDropdownMenu" 
                class="hidden absolute right-0 mt-3 w-52 bg-white text-neutral-800 rounded-2xl shadow-xl border border-neutral-200 py-2 z-50 transition-all duration-200"
            >
                <div class="px-4 py-2.5 border-b border-neutral-100">
                    <p class="text-xs font-bold text-neutral-900 truncate"><?= htmlspecialchars($namaAdmin); ?></p>
                    <p class="text-[11px] text-neutral-500">Administrator SnapRent</p>
                </div>
                <a href="../pengaturan/index.php" class="flex items-center gap-2.5 px-4 py-2 text-xs text-neutral-700 hover:bg-neutral-100 transition-colors">
                    <i class="fa-solid fa-gear text-neutral-400 text-sm"></i>
                    <span>Pengaturan Akun</span>
                </a>
                <a href="../index.php" class="flex items-center gap-2.5 px-4 py-2 text-xs text-neutral-700 hover:bg-neutral-100 transition-colors">
                    <i class="fa-solid fa-camera text-neutral-400 text-sm"></i>
                    <span>Katalog Customer</span>
                </a>
                <div class="border-t border-neutral-100 my-1"></div>
                <a href="../auth/logout.php" class="flex items-center gap-2.5 px-4 py-2 text-xs text-red-600 hover:bg-red-50 font-medium transition-colors">
                    <i class="fa-solid fa-arrow-right-from-bracket text-red-500 text-sm"></i>
                    <span>Keluar Sistem</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Wrapper Body : Sidebar + Main content area -->
    <div class="flex-1 flex overflow-hidden">

        <!-- 2. Sidebar Navigasi (Dipanggil dari komponen includes/sidebar.php) -->
        <?php 
        $current_page = 'dashboard';
        include_once '../includes/sidebar.php'; 
        ?>

        <!-- 3. Main Dashboard Content -->
        <main class="flex-1 overflow-y-auto p-5 sm:p-8 bg-[#d6dde6]">
            <div class="max-w-6xl mx-auto space-y-6">

                <!-- Judul Halaman Dashboard -->
                <div class="pt-1">
                    <h1 class="text-3xl font-extrabold text-neutral-900 tracking-tight">
                        Dashboard
                    </h1>
                </div>

                <!-- 4 ikon bulat -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                    
                    <!-- Card 1: Total Disewa (Ikon Biru) -->
                    <div class="bg-white rounded-[22px] p-5 sm:p-6 shadow-sm flex items-center justify-between hover:shadow-md transition-shadow">
                        <div>
                            <p class="text-sm font-medium text-neutral-700">Total Disewa</p>
                            <p class="text-3xl font-extrabold text-neutral-900 mt-2">
                                <?= number_format($totalPesanan, 0, ',', '.'); ?>
                            </p>
                        </div>
                        <!-- Lingkaran Ikon Biru -->
                        <div class="w-14 h-14 rounded-full bg-[#3b82f6] text-white flex items-center justify-center text-2xl shadow-sm shrink-0">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>

                    <!-- Card 2: Sedang Disewa (Ikon Oranye / Kuning) -->
                    <div class="bg-white rounded-[22px] p-5 sm:p-6 shadow-sm flex items-center justify-between hover:shadow-md transition-shadow">
                        <div>
                            <p class="text-sm font-medium text-neutral-700">Sedang Disewa</p>
                            <p class="text-3xl font-extrabold text-neutral-900 mt-2">
                                <?= number_format($barangDisewa, 0, ',', '.'); ?>
                            </p>
                        </div>
                        <!-- Lingkaran Ikon Oranye / Kamera -->
                        <div class="w-14 h-14 rounded-full bg-[#f59e0b] text-white flex items-center justify-center text-2xl shadow-sm shrink-0">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                    </div>

                    <!-- Card 3: Pendapatan Hari Ini (Ikon Hijau Teal / Dompet) -->
                    <div class="bg-white rounded-[22px] p-5 sm:p-6 shadow-sm flex items-center justify-between hover:shadow-md transition-shadow">
                        <div>
                            <p class="text-sm font-medium text-neutral-700">Pendapatan Hari Ini</p>
                            <p class="text-xl sm:text-2xl font-extrabold text-neutral-900 mt-2 whitespace-nowrap">
                                Rp <?= number_format($pendapatanHariIni, 0, ',', '.'); ?>
                            </p>
                        </div>
                        <!-- Lingkaran Ikon Hijau / Dompet -->
                        <div class="w-14 h-14 rounded-full bg-[#10b981] text-white flex items-center justify-center text-2xl shadow-sm shrink-0">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                    </div>

                    <!-- Card 4: Pelanggan Baru (Ikon Ungu / User Plus) -->
                    <div class="bg-white rounded-[22px] p-5 sm:p-6 shadow-sm flex items-center justify-between hover:shadow-md transition-shadow">
                        <div>
                            <p class="text-sm font-medium text-neutral-700">Pelanggan Baru</p>
                            <p class="text-3xl font-extrabold text-neutral-900 mt-2">
                                <?= number_format($pelangganBaru, 0, ',', '.'); ?>
                            </p>
                        </div>
                        <!-- Lingkaran Ikon Ungu / User Plus -->
                        <div class="w-14 h-14 rounded-full bg-[#8b5cf6] text-white flex items-center justify-center text-2xl shadow-sm shrink-0">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                    </div>

                </div>

                <!-- Section tabel : Rincian Hari Ini -->
                <div class="bg-white rounded-[24px] p-6 sm:p-7 shadow-sm">
                    
                    <!-- Judul Card Tabel -->
                    <h2 class="text-lg font-bold text-neutral-900 mb-5">
                        Rincian Hari Ini
                    </h2>

                    <!-- Wrapper Tabel Responsif -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[700px]">
                            
                            <!-- Header Tabel -->
                            <thead>
                                <tr class="bg-[#000000] text-white text-xs font-semibold">
                                    <th class="py-3 px-3 text-center rounded-l-xl w-14">NO</th>
                                    <th class="py-3 px-4 text-center">Order ID</th>
                                    <th class="py-3 px-5 text-center">Item</th>
                                    <th class="py-3 px-4 text-center">Pelanggan</th>
                                    <th class="py-3 px-4 text-center">Mulai</th>
                                    <th class="py-3 px-4 text-center">Selesai</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-center rounded-r-xl">Tindakan</th>
                                </tr>
                            </thead>

                            <!-- Baris Data Transaksi -->
                            <tbody class="text-xs sm:text-sm divide-y divide-neutral-100">
                                <?php if (!empty($rincianHariIni)): ?>
                                    <?php $no = 1; foreach ($rincianHariIni as $row): ?>
                                        <tr class="hover:bg-neutral-50/70 transition-colors">
                                            
                                            <!-- NO -->
                                            <td class="py-4 px-3 text-center font-medium text-neutral-800">
                                                <?= $no++; ?>
                                            </td>

                                            <!-- Order ID -->
                                            <td class="py-4 px-4 text-center font-medium text-neutral-900 whitespace-nowrap">
                                                <?= htmlspecialchars($row['id_transaksi']); ?>
                                            </td>

                                            <!-- Item: Box Icon Kamera Abu-Abu & Nama Barang -->
                                            <td class="py-4 px-5 text-center">
                                                <div class="flex flex-col items-center justify-center">
                                                    <!-- Thumbnail Kamera Kotak Lembut (#cfd8e3) -->
                                                    <div class="w-12 h-12 bg-[#cfd8e3] rounded-xl flex items-center justify-center overflow-hidden mb-1.5 shadow-2xs">
                                                        <?php if (!empty($row['foto_barang']) && file_exists('../uploads/barang/' . $row['foto_barang'])): ?>
                                                            <img src="../uploads/barang/<?= htmlspecialchars($row['foto_barang']); ?>" alt="<?= htmlspecialchars($row['nama_barang']); ?>" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <i class="fa-solid fa-camera text-neutral-700 text-lg"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <!-- Nama Barang -->
                                                    <span class="font-medium text-xs text-neutral-900 max-w-[140px] truncate leading-tight">
                                                        <?= htmlspecialchars($row['nama_barang']); ?>
                                                    </span>
                                                </div>
                                            </td>

                                            <!-- Pelanggan -->
                                            <td class="py-4 px-4 text-center font-medium text-neutral-800">
                                                <?= htmlspecialchars($row['nama_pelanggan']); ?>
                                            </td>

                                            <!-- Mulai -->
                                            <td class="py-4 px-4 text-center text-neutral-700 whitespace-nowrap">
                                                <?= formatTgl($row['waktu_mulai']); ?>
                                            </td>

                                            <!-- Selesai -->
                                            <td class="py-4 px-4 text-center text-neutral-700 whitespace-nowrap">
                                                <?= formatTgl($row['waktu_selesai']); ?>
                                            </td>

                                            <!-- Status Pill Hijau (Selesai) / Kuning (Disewa) -->
                                            <td class="py-4 px-4 text-center whitespace-nowrap">
                                                <?php 
                                                    $statusLower = strtolower($row['status']);
                                                    if ($statusLower === 'selesai'): 
                                                ?>
                                                    <span class="inline-block px-3.5 py-1 rounded-full text-xs font-medium bg-[#bbf7d0] text-[#15803d]">
                                                        Selesai
                                                    </span>
                                                <?php elseif ($statusLower === 'disewa'): ?>
                                                    <span class="inline-block px-3.5 py-1 rounded-full text-xs font-medium bg-[#fef08a] text-[#854d0e]">
                                                        Disewa
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-block px-3.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <?= htmlspecialchars($row['status']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Tombol Tindakan: Pill Detail -->
                                            <td class="py-4 px-4 text-center whitespace-nowrap">
                                                <a 
                                                    href="../transaksi/detail.php?id=<?= urlencode($row['id_transaksi']); ?>" 
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-blue-400 text-blue-600 bg-white hover:bg-blue-50 text-xs font-semibold transition-all shadow-2xs"
                                                >
                                                    <i class="fa-regular fa-eye text-blue-500 text-xs"></i>
                                                    <span>Detail</span>
                                                </a>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="py-10 text-center text-neutral-400 text-sm">
                                            <i class="fa-solid fa-inbox text-3xl mb-2 block"></i>
                                            Belum ada transaksi sewa untuk hari ini.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginasi Bersih -->
                    <div class="mt-6 flex justify-center items-center gap-2 text-xs sm:text-sm text-neutral-500 font-medium select-none">
                        <button class="hover:text-black px-1.5 py-0.5 rounded transition-colors">&lt;</button>
                        <button class="text-neutral-900 font-bold px-1.5 py-0.5 rounded">1</button>
                        <button class="hover:text-black px-1.5 py-0.5 rounded transition-colors">2</button>
                        <button class="hover:text-black px-1.5 py-0.5 rounded transition-colors">3</button>
                        <button class="hover:text-black px-1.5 py-0.5 rounded transition-colors">4</button>
                        <button class="hover:text-black px-1.5 py-0.5 rounded transition-colors">5</button>
                        <button class="hover:text-black px-1.5 py-0.5 rounded transition-colors">6</button>
                        <button class="hover:text-black px-1.5 py-0.5 rounded transition-colors">&gt;</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript: Toggle Sidebar & Profil Dropdown -->
    <script>
        // 1. Toggle Sidebar Navigasi
        const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
        const sidebarNav = document.getElementById('sidebarNav');

        sidebarToggleBtn.addEventListener('click', function () {
            sidebarNav.classList.toggle('-ml-60');
        });

        // 2. Toggle Dropdown Menu Profil Admin
        const adminProfileBtn = document.getElementById('adminProfileBtn');
        const adminDropdownMenu = document.getElementById('adminDropdownMenu');

        adminProfileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            adminDropdownMenu.classList.toggle('hidden');
        });

        // Tutup dropdown jika klik di luar
        document.addEventListener('click', function (e) {
            if (!adminProfileBtn.contains(e.target) && !adminDropdownMenu.contains(e.target)) {
                adminDropdownMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
