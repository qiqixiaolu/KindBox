<?php
session_start();
require 'db.php'; // Koneksi ke database

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: halamanLogin.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

if ($item_id === 0) {
    header("Location: beranda.php?error=invalidrequest");
    exit();
}

// Check if the current user is the donor of this item
try {
    $stmt_check_donor = $conn->prepare("SELECT donor_id FROM donations WHERE id = :item_id");
    $stmt_check_donor->bindParam(':item_id', $item_id);
    $stmt_check_donor->execute();
    $item = $stmt_check_donor->fetch(PDO::FETCH_ASSOC);

    if (!$item || $item['donor_id'] != $current_user_id) {
        // Not the donor or item not found, unauthorized
        header("Location: beranda.php?error=unauthorized");
        exit();
    }

    // If request method is POST, proceed with deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
        // Delete related interests first (due to foreign key constraints if not CASCADE)
        $stmt_delete_interests = $conn->prepare("DELETE FROM interests WHERE item_id = :item_id");
        $stmt_delete_interests->bindParam(':item_id', $item_id);
        $stmt_delete_interests->execute();

        // Then delete the donation item
        $stmt_delete_item = $conn->prepare("DELETE FROM donations WHERE id = :item_id AND donor_id = :donor_id");
        $stmt_delete_item->bindParam(':item_id', $item_id);
        $stmt_delete_item->bindParam(':donor_id', $current_user_id);

        if ($stmt_delete_item->execute()) {
            header("Location: halamanProfil.php?success=itemdeleted"); // Redirect to profile or home
            exit();
        } else {
            // Handle deletion error
            header("Location: detailBarang.php?item_id=$item_id&error=deletionfailed");
            exit();
        }
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Hapus Barang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: "Poppins", sans-serif; }
    </style>
</head>
<body class="bg-white">
    <header class="bg-[#7B927B] flex items-center px-4 py-3 text-white fixed top-0 left-0 w-full z-20 md:hidden" aria-label="Mobile Navigation Header">
        <button aria-label="Back" class="mr-4 text-lg" onclick="history.back()">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="flex-1 text-center font-semibold text-lg leading-6 select-none">
            Hapus Barang
        </h1>
        <div class="w-6"></div>
    </header>

    <header class="bg-[#7B927B] hidden md:flex items-center px-6 py-4 text-white fixed top-0 left-0 w-full z-20" aria-label="Desktop Navigation Header">
        <button aria-label="Back" class="mr-6 text-xl" onclick="history.back()">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="flex-1 text-center font-semibold text-xl leading-7 select-none">
            Hapus Barang
        </h1>
        <div class="w-8"></div>
    </header>

    <div class="h-14 md:h-16"></div>

    <main class="max-w-md mx-auto p-4 md:p-6 mt-4 text-center">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold text-red-600 mb-4">Konfirmasi Penghapusan</h2>
            <p class="text-gray-700 mb-6">Anda yakin ingin menghapus barang "<?= htmlspecialchars($item_details['item_name'] ?? 'Barang ini') ?>"?</p>
            <p class="text-sm text-gray-500 mb-8">Tindakan ini tidak dapat dibatalkan. Semua data terkait, termasuk minat dari peminat, akan dihapus.</p>

            <form action="delete_item.php?item_id=<?= $item_id ?>" method="POST" class="flex justify-center gap-4">
                <button type="button" onclick="history.back()"
                        class="px-5 py-2 border border-gray-300 rounded-md shadow-sm text-base font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Batal
                </button>
                <button type="submit" name="confirm_delete" value="1"
                        class="px-5 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    Hapus
                </button>
            </form>
        </div>
    </main>
</body>
</html>