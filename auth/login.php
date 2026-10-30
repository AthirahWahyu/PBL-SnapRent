<?php
session_start();

// Jika admin sudah login, langsung arahkan ke dashboard
if (isset($_SESSION['admin'])) {
    header("Location: ../dashboard/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SnapRent</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Menambahkan FontAwesome untuk ikon user dan gembok -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-black flex items-center justify-center h-screen">

    <div class="relative bg-white w-80 rounded-2xl shadow-lg p-8 mt-12">
        <!-- Ikon Avatar di bagian atas form -->
        <div class="absolute -top-10 left-1/2 transform -translate-x-1/2 bg-white rounded-full p-2">
            <div class="bg-black rounded-full h-16 w-16 flex items-center justify-center">
                <i class="fa-solid fa-user text-white text-3xl"></i>
            </div>
        </div>

        <h2 class="text-center text-2xl font-bold text-black mt-8 mb-6 italic">Admin Login</h2>

        <!-- Pesan Error jika login gagal -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm mb-4 text-center">
                <?= $_SESSION['error']; ?>
            </div>
            <?php unset($_SESSION['error']); // Hapus pesan error setelah ditampilkan ?>
        <?php endif; ?>

        <!-- Form dikirim ke proses_login.php -->
        <form action="proses_login.php" method="POST" class="space-y-4">
            
            <!-- Input Username -->
            <div class="relative border-2 border-black rounded flex items-center p-2">
                <i class="fa-regular fa-user text-gray-500 mr-2 ml-1"></i>
                <input type="text" name="username" placeholder="Username / Email" class="w-full focus:outline-none text-gray-700" required>
            </div>

            <!-- Input Password -->
            <div class="relative border-2 border-black rounded flex items-center p-2">
                <i class="fa-solid fa-lock text-gray-500 mr-2 ml-1"></i>
                <input type="password" name="password" placeholder="Password" class="w-full focus:outline-none text-gray-700" required>
            </div>

            <!-- Checkbox Ingat Saya & Lupa Password -->
            <div class="flex justify-between items-center text-sm mt-2">
                <label class="flex items-center space-x-2 text-black font-semibold">
                    <input type="checkbox" class="w-4 h-4 border-2 border-black rounded-none">
                    <span>Ingat Saya</span>
                </label>
                <a href="#" class="text-blue-500 hover:underline">Lupa Password?</a>
            </div>

            <!-- Tombol Login -->
            <button type="submit" class="w-full bg-black text-white font-semibold py-2 rounded mt-6 hover:bg-gray-800 transition duration-200">
                Login
            </button>
        </form>
    </div>

</body>
</html>