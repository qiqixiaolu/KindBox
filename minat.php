<?php
session_start();
require 'db.php'; // Ensure this file correctly connects to your database

if (!isset($_SESSION['user_id'])) {
    header("Location: halamanLogin.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

if ($item_id === 0) {
    header("Location: beranda.php?error=itemnotfound");
    exit();
}

$item_name = ''; // Initialize item_name
$display_form = true; // Flag to control form display

// --- Process Interest Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = htmlspecialchars($_POST['nama']);
    $alamat = htmlspecialchars($_POST['alamat']);
    $jumlah = intval($_POST['jumlah']);
    $item_alasan = htmlspecialchars($_POST['item_alasan']);
    $whatsapp_user_contact = htmlspecialchars($_POST['whatsapp_user_contact']);

    // Validate inputs (basic validation, add more as needed)
    if (empty($nama) || empty($alamat) || $jumlah <= 0 || empty($item_alasan) || empty($whatsapp_user_contact)) {
        $_SESSION['error_message'] = "Harap lengkapi semua bidang dan pastikan jumlah barang valid.";
    } else {
        try {
            // Check if the user has already expressed interest in this item
            $stmt_check_interest = $conn->prepare("SELECT COUNT(*) FROM interests WHERE item_id = :item_id AND user_id = :user_id");
            $stmt_check_interest->bindParam(':item_id', $item_id);
            $stmt_check_interest->bindParam(':user_id', $current_user_id);
            $stmt_check_interest->execute();
            if ($stmt_check_interest->fetchColumn() > 0) {
                $_SESSION['error_message'] = "Anda sudah mengajukan minat untuk barang ini.";
            } else {
                // Insert into interests table
                $stmt_insert_interest = $conn->prepare("
                    INSERT INTO interests (item_id, user_id, nama, alamat, jumlah, item_alasan, whatsapp_user_contact, status)
                    VALUES (:item_id, :user_id, :nama, :alamat, :jumlah, :item_alasan, :whatsapp_user_contact, 'pending')
                ");
                $stmt_insert_interest->bindParam(':item_id', $item_id);
                $stmt_insert_interest->bindParam(':user_id', $current_user_id);
                $stmt_insert_interest->bindParam(':nama', $nama);
                $stmt_insert_interest->bindParam(':alamat', $alamat);
                $stmt_insert_interest->bindParam(':jumlah', $jumlah);
                $stmt_insert_interest->bindParam(':item_alasan', $item_alasan);
                $stmt_insert_interest->bindParam(':whatsapp_user_contact', $whatsapp_user_contact);

                if ($stmt_insert_interest->execute()) {
                    $_SESSION['success_message'] = "Minat Anda berhasil diajukan! Pemberi akan segera menghubungi Anda.";
                    $display_form = false; // Hide the form after successful submission
                } else {
                    $_SESSION['error_message'] = "Gagal mengajukan minat. Silakan coba lagi.";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Terjadi kesalahan database: " . $e->getMessage();
            error_log("Minat submission error: " . $e->getMessage());
        }
    }
}

// Fetch item details to display on this page (even if form is hidden)
try {
    $stmt_item = $conn->prepare("SELECT item_name FROM donations WHERE id = :item_id");
    $stmt_item->bindParam(':item_id', $item_id);
    $stmt_item->execute();
    $item_details = $stmt_item->fetch(PDO::FETCH_ASSOC);

    if (!$item_details) {
        header("Location: beranda.php?error=itemnotfound");
        exit();
    }
    $item_name = htmlspecialchars($item_details['item_name']);
} catch (PDOException $e) {
    die("Error fetching item details: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Minat Barang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap" rel="stylesheet"/>
</head>
<body class="bg-gray-100">

    <header
        class="bg-[#7B927B] flex items-center px-4 py-3 text-white fixed top-0 left-0 w-full z-20 md:hidden"
        aria-label="Mobile Navigation Header"
    >
        <button aria-label="Back" class="mr-4 text-lg" onclick="history.back()">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="flex-1 text-center font-semibold text-lg leading-6 select-none">
            Ajukan Minat
        </h1>
        <div class="w-6"></div>
    </header>

    <header
        class="bg-[#7B927B] hidden md:flex items-center px-6 py-4 text-white fixed top-0 left-0 w-full z-20"
        aria-label="Desktop Navigation Header"
    >
        <button aria-label="Back" class="mr-6 text-xl" onclick="history.back()">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="flex-1 text-center font-semibold text-xl leading-7 select-none">
            Ajukan Minat
        </h1>
        <div class="w-8"></div>
    </header>

    <div class="h-14 md:h-16"></div> <div class="max-w-md mx-auto my-10 p-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold text-center text-[#2F4F2F] mb-6">Ajukan Minat pada Barang: <?= $item_name ?></h2>

        <?php
        // Display success or error messages
        if (isset($_SESSION['success_message'])) {
            echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Sukses!</strong>
                    <span class="block sm:inline">' . $_SESSION['success_message'] . '</span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display=\'none\';">
                        <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                    </span>
                  </div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline">' . $_SESSION['error_message'] . '</span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display=\'none\';">
                        <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                    </span>
                  </div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <?php if ($display_form): // Show form if not yet submitted successfully ?>
        <form action="" method="POST">
            <input type="hidden" name="item_id" value="<?= $item_id ?>">
            <div class="mb-4">
                <label for="nama" class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap Penerima:</label>
                <input type="text" id="nama" name="nama" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="alamat" class="block text-gray-700 text-sm font-bold mb-2">Alamat Lengkap Pengiriman:</label>
                <textarea id="alamat" name="alamat" rows="4" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required></textarea>
            </div>
            <div class="mb-4">
                <label for="jumlah" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Barang Diminta:</label>
                <input type="number" id="jumlah" name="jumlah" min="1" value="1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="item_alasan" class="block text-gray-700 text-sm font-bold mb-2">Alasan Minat:</label>
                <textarea id="item_alasan" name="item_alasan" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required></textarea>
            </div>
            <div class="mb-6">
                <label for="whatsapp_user_contact" class="block text-gray-700 text-sm font-bold mb-2">Nomor WhatsApp Anda:</label>
                <input type="text" id="whatsapp_user_contact" name="whatsapp_user_contact" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Contoh: 6281234567890" required>
            </div>
            <button type="submit" class="bg-[#4F6B4F] hover:bg-[#3e5740] text-white font-semibold rounded-lg py-2 px-4 w-full">
                Ajukan Minat
            </button>
        </form>
        <?php else: // Show only the success message and back button if form submission was successful ?>
            <p class="text-center text-green-700 font-semibold text-lg mb-4">Pengajuan minat Anda untuk <?= $item_name ?> berhasil!</p>
            <p class="text-center text-gray-600 mb-6">Pemberi akan segera menghubungi Anda melalui WhatsApp.</p>
        <?php endif; ?>

        <a href="halamanBeranda.php" class="block text-center mt-6 py-2 px-4 bg-gray-200 hover:bg-gray-300 text-[#4F6B4F] font-semibold rounded-lg">
            <i class="fas fa-home mr-2"></i> Kembali ke Beranda
        </a>
    </div>

    <footer id="mobile-footer" class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 flex justify-around py-2 md:hidden z-10">
        <button aria-label="Home" class="footer-button flex flex-col items-center text-gray-600 hover:text-[#4F6B4F]" type="button" onclick="location.href='halamanBeranda.php'">
            <i class="fas fa-home text-xl"></i>
            <span class="text-xs">Home</span>
        </button>
        <button aria-label="Upload Barang" class="footer-button flex flex-col items-center text-gray-600 hover:text-[#4F6B4F]" type="button" onclick="location.href='halamanTambah.php'">
            <i class="fas fa-plus-circle text-xl"></i>
            <span class="text-xs">Upload</span>
        </button>
        <button aria-label="Profile" class="footer-button flex flex-col items-center text-gray-600 hover:text-[#4F6B4F]" type="button" onclick="location.href='halamanProfil.php'">
            <i class="fas fa-user-circle text-xl"></i>
            <span class="text-xs">Profil</span>
        </button>
    </footer>

</body>
</html>