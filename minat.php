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

if ($item_id === 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect if no item_id is provided on initial load
    header("Location: beranda.php");
    exit();
}

$item_details = null;
$donor_whatsapp = '';
$form_submit_success = false; // Flag for successful form submission
$error_message = ''; // For displaying form errors

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $jumlah_minat = intval($_POST['jumlah'] ?? 0); // Renamed to avoid conflict with item_count
    $alasan = trim($_POST['alasan'] ?? '');
    $whatsapp_user_contact = trim($_POST['whatsapp'] ?? '');
    $submitted_item_id = intval($_POST['item_id'] ?? 0); // Get item_id from hidden field

    // Server-side validation
    if (empty($nama) || empty($alamat) || $jumlah_minat <= 0 || empty($alasan) || empty($whatsapp_user_contact) || $submitted_item_id === 0) {
        $error_message = "Semua kolom wajib diisi dengan benar.";
    } elseif (!preg_match('/^628[0-9]{8,11}$/', $whatsapp_user_contact)) {
        $error_message = "Format Nomor WhatsApp tidak valid. Harap masukkan nomor yang dimulai dengan 628 dan memiliki 11-14 digit (misal: 6281234567890).";
    } else {
        try {
            // Fetch item details again to get donor's info and item_count for comparison
            $stmt_item_check = $conn->prepare("SELECT donor_id, item_count, whatsapp_contact FROM donations WHERE id = :item_id");
            $stmt_item_check->bindParam(':item_id', $submitted_item_id);
            $stmt_item_check->execute();
            $item_data_check = $stmt_item_check->fetch(PDO::FETCH_ASSOC);

            if (!$item_data_check) {
                $error_message = "Barang yang Anda minati tidak ditemukan.";
            } elseif ($item_data_check['item_count'] < $jumlah_minat) {
                $error_message = "Jumlah barang yang Anda minati melebihi jumlah yang tersedia (" . $item_data_check['item_count'] . ").";
            } elseif ($item_data_check['donor_id'] == $current_user_id) {
                 $error_message = "Anda tidak bisa mengajukan minat pada barang Anda sendiri.";
            } else {
                // Check if user has already expressed interest in this item
                $stmt_check_interest = $conn->prepare("SELECT id FROM interests WHERE item_id = :item_id AND user_id = :user_id AND status = 'pending'");
                $stmt_check_interest->bindParam(':item_id', $submitted_item_id);
                $stmt_check_interest->bindParam(':user_id', $current_user_id);
                $stmt_check_interest->execute();
                if ($stmt_check_interest->rowCount() > 0) {
                    $error_message = "Anda sudah mengajukan minat untuk barang ini. Mohon tunggu konfirmasi dari pemberi.";
                } else {
                    // Insert into interests table
                    $stmt_insert = $conn->prepare("
                        INSERT INTO interests (item_id, user_id, nama, alamat, jumlah, item_alasan, whatsapp_user_contact, status)
                        VALUES (:item_id, :user_id, :nama, :alamat, :jumlah, :alasan, :whatsapp_user_contact, 'pending')
                    ");
                    $stmt_insert->bindParam(':item_id', $submitted_item_id);
                    $stmt_insert->bindParam(':user_id', $current_user_id);
                    $stmt_insert->bindParam(':nama', $nama);
                    $stmt_insert->bindParam(':alamat', $alamat);
                    $stmt_insert->bindParam(':jumlah', $jumlah_minat);
                    $stmt_insert->bindParam(':alasan', $alasan);
                    $stmt_insert->bindParam(':whatsapp_user_contact', $whatsapp_user_contact);
                    $stmt_insert->execute();

                    $form_submit_success = true;
                    $donor_whatsapp = htmlspecialchars($item_data_check['whatsapp_contact'] ?? 'N/A');
                }
            }
        } catch (PDOException $e) {
            error_log("Database error on minat form submission: " . $e->getMessage());
            $error_message = "Terjadi kesalahan sistem. Mohon coba lagi nanti.";
        }
    }
}

// Fetch item details (for initial page load or if form submission failed)
try {
    // Only fetch if not already successful and item_id is valid
    if (!$form_submit_success && $item_id !== 0) {
        $stmt_item = $conn->prepare("SELECT whatsapp_contact FROM donations WHERE id = :item_id");
        $stmt_item->bindParam(':item_id', $item_id);
        $stmt_item->execute();
        $item_details_raw = $stmt_item->fetch(PDO::FETCH_ASSOC);
        if ($item_details_raw) {
            $donor_whatsapp = htmlspecialchars($item_details_raw['whatsapp_contact'] ?? 'N/A');
        } else {
            // Item not found on initial load or after failed submission
            header("Location: beranda.php?error=itemnotfound");
            exit();
        }
    }
} catch (PDOException $e) {
    die("Error fetching item details: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Isi Data Diri</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #E4F0D6; /* Default body background for the form */
            min-height: 100vh;
            display: flex;
            flex-direction: column; /* Ensure body is flex column */
            margin: 0;
        }
        /* Basic styling for header and form elements */
        header {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Subtle shadow for fixed header */
        }
        .container-form { /* New class for the form container */
            max-width: 600px;
            margin: 20px auto; /* Center the form */
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        input[type="text"], input[type="number"], input[type="tel"], textarea {
            border: 1px solid #d1d5db;
            padding: 0.75rem;
            border-radius: 0.375rem;
            background-color: #f3f4f6;
            transition: border-color 0.2s;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #4F6B4F;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .error-message-inline {
            color: #ef4444; /* red-500 */
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        /* Styles for the success info screen (modified) */
        .info-screen-wrapper { 
            flex-grow: 1; /* Make it take all available space */
            display: flex;
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically */
            /* No background here, let body provide the white background */
            padding: 20px; /* Some padding around the content */
            box-sizing: border-box; /* Include padding in element's total width and height */
        }
        .info-screen-message { /* The actual message content box */
            max-width: 400px; /* Max width for the text */
            padding: 20px; /* Inner padding */
            text-align: center; /* Center text */
            color: #4F6B4F; /* Text color */
        }
        .info-screen-message h2 {
            font-size: 1.5rem; /* Larger title */
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .info-screen-message p {
            font-size: 1rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }
        .info-screen-message a {
            font-size: 1rem;
        }
    </style>
</head>
<body class="bg-white min-h-screen flex flex-col">

    <header class="bg-[#7B927B] text-white flex items-center p-4">
        <button onclick="window.history.back()" class="text-lg mr-4">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="text-center font-semibold text-lg flex-1">Isi Data Diri</h1>
    </header>

    <?php if ($form_submit_success): // Display success screen ?>
        <div id="infoScreen" class="info-screen-wrapper">
            <div class="info-screen-message">
                <h2 class="text-xl font-semibold mb-4">Informasi</h2>
                <p class="mb-6">
                    Barang yang Anda minati sedang dalam peninjauan oleh pemberi. Tunggu pesan dari pemberi atau hubungi pemberi.
                </p>
                <a id="waLink" target="_blank" class="inline-block bg-[#4F6B4F] text-white px-6 py-3 rounded-md"
                   href="https://wa.me/<?= $donor_whatsapp ?>" rel="noopener noreferrer">
                    Hubungi Pemberi
                </a>
            </div>
        </div>
    <?php else: // Display form or error message ?>
        <div class="container-form">
            <?php if (!empty($error_message)): ?>
                <div class="alert-message bg-red-100 text-red-700 border-red-400 p-3 rounded-md mb-4 text-center">
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

            <form id="minatForm" action="minat.php?item_id=<?= htmlspecialchars($item_id) ?>" method="POST" class="flex flex-col gap-4 text-[#4F6B4F]">
                <input type="hidden" name="item_id" value="<?= htmlspecialchars($item_id) ?>">

                <div>
                    <label class="text-sm font-medium">Nama</label>
                    <input type="text" name="nama" required placeholder="Nama Penerima" class="w-full p-3 rounded-md bg-[#E8F2D9] border border-[#4F6B4F]" />
                </div>

                <div>
                    <label class="text-sm font-medium">Alamat</label>
                    <textarea name="alamat" required rows="3" placeholder="Tuliskan alamat secara lengkap untuk keperluan pengiriman" class="w-full p-3 rounded-md bg-[#E8F2D9] border border-[#4F6B4F]"></textarea>
                </div>

                <div>
                    <label class="text-sm font-medium">Jumlah</label>
                    <input type="number" name="jumlah" required placeholder="Jumlah Barang" class="w-full p-3 rounded-md bg-[#E8F2D9] border border-[#4F6B4F]" />
                </div>

                <div>
                    <label class="text-sm font-medium">Untuk Apa Barang ini Akan dimanfaatkan</label>
                    <textarea name="alasan" required rows="3" placeholder="Ceritakan apa yang akan kamu lakukan apabila mendapatkan barang ini" class="w-full p-3 rounded-md bg-[#E8F2D9] border border-[#4F6B4F]"></textarea>
                </div>

                <div>
                    <label class="text-sm font-medium">Nomor WhatsApp yang bisa Dihubungi</label>
                    <input type="tel" name="whatsapp" required pattern="^628[0-9]{8,11}$" placeholder="Nomor WhatsApp (628xxxxxxxxxx)" class="w-full p-3 rounded-md bg-[#E8F2D9] border border-[#4F6B4F]" />
                    <p class="text-xs text-gray-500 mt-1">Format: 628xxxxxxxxxx (11-14 digit)</p>
                </div>

                <button type="submit" class="bg-[#4F6B4F] text-white font-semibold py-3 rounded-md mt-4">
                    Minat
                </button>
            </form>
        </div>
    <?php endif; ?>

    <script>
        const form = document.getElementById('minatForm');
        if (form) { // Check if form exists (it won't on success screen)
            form.addEventListener('submit', function (e) {
                const whatsappInput = form.whatsapp;
                // Client-side pattern check on submit
                if (!whatsappInput.checkValidity()) {
                    e.preventDefault(); // Stop form submission if invalid
                    alert("Nomor WhatsApp tidak valid. Harap masukkan nomor yang dimulai dengan 628 dan memiliki 11-14 digit.");
                    whatsappInput.focus();
                }
            });
        }
    </script>
</body>
</html>