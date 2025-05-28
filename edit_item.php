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
$item_details = null;
$error = '';
$success = '';

if ($item_id === 0) {
    header("Location: beranda.php?error=itemnotfound");
    exit();
}

try {
    // Fetch item details to pre-fill the form and verify donor
    $stmt = $conn->prepare("SELECT * FROM donations WHERE id = :item_id AND donor_id = :donor_id");
    $stmt->bindParam(':item_id', $item_id);
    $stmt->bindParam(':donor_id', $current_user_id);
    $stmt->execute();
    $item_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item_details) {
        // Item not found or current user is not the donor
        header("Location: beranda.php?error=unauthorized_or_item_not_found");
        exit();
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $item_name = trim($_POST['item_name']);
        $item_description = trim($_POST['item_description']);
        $item_count = intval($_POST['item_count']);
        $category = trim($_POST['category']);
        $item_condition = trim($_POST['item_condition']);
        $whatsapp_contact = trim($_POST['whatsapp_contact']);

        // Basic validation
        if (empty($item_name) || empty($item_description) || $item_count <= 0) {
            $error = "Nama, deskripsi, dan jumlah barang tidak boleh kosong.";
        } else {
            // Update donation in database
            $stmt_update = $conn->prepare("
                UPDATE donations
                SET item_name = :item_name,
                    item_description = :item_description,
                    item_count = :item_count,
                    category = :category,
                    item_condition = :item_condition,
                    whatsapp_contact = :whatsapp_contact
                WHERE id = :item_id AND donor_id = :donor_id
            ");
            $stmt_update->bindParam(':item_name', $item_name);
            $stmt_update->bindParam(':item_description', $item_description);
            $stmt_update->bindParam(':item_count', $item_count);
            $stmt_update->bindParam(':category', $category);
            $stmt_update->bindParam(':item_condition', $item_condition);
            $stmt_update->bindParam(':whatsapp_contact', $whatsapp_contact);
            $stmt_update->bindParam(':item_id', $item_id);
            $stmt_update->bindParam(':donor_id', $current_user_id);

            if ($stmt_update->execute()) {
                $success = "Barang berhasil diperbarui.";
                // Optionally redirect back to detail page or profile
                header("Location: detailBarang.php?item_id=$item_id&success=updated");
                exit();
            } else {
                $error = "Gagal memperbarui barang.";
            }
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
    <title>Edit Barang - <?= htmlspecialchars($item_details['item_name'] ?? 'Barang') ?></title>
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
            Edit Barang
        </h1>
        <div class="w-6"></div>
    </header>

    <header class="bg-[#7B927B] hidden md:flex items-center px-6 py-4 text-white fixed top-0 left-0 w-full z-20" aria-label="Desktop Navigation Header">
        <button aria-label="Back" class="mr-6 text-xl" onclick="history.back()">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="flex-1 text-center font-semibold text-xl leading-7 select-none">
            Edit Barang
        </h1>
        <div class="w-8"></div>
    </header>

    <div class="h-14 md:h-16"></div>

    <main class="max-w-xl mx-auto p-4 md:p-6 mt-4">
        <h2 class="text-2xl font-bold text-[#2F4F2F] mb-6">Edit Barang Donasi</h2>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <?= $error ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <form action="edit_item.php?item_id=<?= $item_id ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label for="item_name" class="block text-sm font-medium text-gray-700">Nama Barang</label>
                <input type="text" id="item_name" name="item_name" value="<?= htmlspecialchars($item_details['item_name']) ?>" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="item_description" class="block text-sm font-medium text-gray-700">Deskripsi Barang</label>
                <textarea id="item_description" name="item_description" rows="4" required
                          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"><?= htmlspecialchars($item_details['item_description']) ?></textarea>
            </div>

            <div>
                <label for="item_count" class="block text-sm font-medium text-gray-700">Jumlah</label>
                <input type="number" id="item_count" name="item_count" value="<?= htmlspecialchars($item_details['item_count']) ?>" min="1" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="category" class="block text-sm font-medium text-gray-700">Kategori</label>
                <input type="text" id="category" name="category" value="<?= htmlspecialchars($item_details['category']) ?>"
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="item_condition" class="block text-sm font-medium text-gray-700">Kondisi Barang</label>
                <select id="item_condition" name="item_condition" required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="Baru" <?= ($item_details['item_condition'] == 'Baru') ? 'selected' : '' ?>>Baru</option>
                    <option value="Bekas (Sangat Baik)" <?= ($item_details['item_condition'] == 'Bekas (Sangat Baik)') ? 'selected' : '' ?>>Bekas (Sangat Baik)</option>
                    <option value="Bekas (Baik)" <?= ($item_details['item_condition'] == 'Bekas (Baik)') ? 'selected' : '' ?>>Bekas (Baik)</option>
                    <option value="Bekas (Cukup)" <?= ($item_details['item_condition'] == 'Bekas (Cukup)') ? 'selected' : '' ?>>Bekas (Cukup)</option>
                </select>
            </div>

            <div>
                <label for="whatsapp_contact" class="block text-sm font-medium text-gray-700">Nomor WhatsApp Donor</label>
                <input type="text" id="whatsapp_contact" name="whatsapp_contact" value="<?= htmlspecialchars($item_details['whatsapp_contact']) ?>"
                       placeholder="Contoh: 6281234567890"
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-500">Gunakan format internasional tanpa tanda + atau spasi (misal: 628xxxxxxxxxx)</p>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="history.back()"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Batal
                </button>
                <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </main>

</body>
</html>