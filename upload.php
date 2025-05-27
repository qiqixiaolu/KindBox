<?php
session_start();
require 'db.php'; // Koneksi ke database

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: halamanLogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_username = '';
$user_location = '';

try {
    // Fetch donor's username and location from the database
    $stmt_user = $conn->prepare("SELECT username, full_name, location FROM users WHERE id = :user_id");
    $stmt_user->bindParam(':user_id', $user_id);
    $stmt_user->execute();
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($user_info) {
        $user_username = $user_info['username'];
        $user_location = $user_info['location'];
    } else {
        session_destroy();
        header("Location: halamanLogin.php");
        exit();
    }
} catch (PDOException $e) {
    // Log error and redirect to an error page or back to form
    error_log("Database error fetching user info in upload.php: " . $e->getMessage());
    header("Location: halamanTambah.php?error_message=" . urlencode("Terjadi kesalahan sistem saat mengambil data pengguna."));
    exit();
}

$upload_success = false; // Flag to determine if the upload and DB insert were successful
$upload_message = ""; // Message for success/error
$item_details_for_display = []; // Data to pass to the success HTML

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = $_POST['judul'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $lokasi = $_POST['lokasi'] ?? '';
    $jumlah = intval($_POST['jumlah'] ?? 1);
    $kategori = $_POST['kategori'] ?? '';
    $kondisi = $_POST['kondisi'] ?? '';
    $whatsapp_contact = $_POST['whatsapp'] ?? ''; // NEW: Get WhatsApp number

    // Basic validation for required fields (including new whatsapp_contact)
    if (empty($judul) || empty($deskripsi) || empty($lokasi) || empty($kategori) || empty($kondisi) || $jumlah < 1 || empty($whatsapp_contact)) {
        header("Location: halamanTambah.php?error_message=" . urlencode("Semua kolom wajib diisi dan jumlah harus minimal 1."));
        exit();
    }

    // Server-side validation for WhatsApp number format
    // Matches the client-side pattern: starts with 628, followed by 8 to 11 digits
    if (!preg_match('/^628[0-9]{8,11}$/', $whatsapp_contact)) {
        header("Location: halamanTambah.php?error_message=" . urlencode("Format Nomor WhatsApp tidak valid. Harap masukkan format 628xxxxxxxxxx."));
        exit();
    }

    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true);
    }

    // --- Handling Single File Upload ---
    $uploadedFileUrl = null; // Will store the URL of the single uploaded image
    $rejectedFiles = []; // Not used for single file rejection here, but kept for consistency

    // File input name is 'foto' (not 'foto[]') for single upload
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) { // Check for successful upload
        $file = $_FILES['foto']; // Get the single file array

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

        if (in_array($file['type'], $allowedTypes)) {
            $fileName = $file['name'];
            $tmpName = $file['tmp_name'];

            $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueName = uniqid() . '.' . $fileExt;
            $targetPath = "uploads/" . $uniqueName;

            if (move_uploaded_file($tmpName, $targetPath)) {
                $uploadedFileUrl = $targetPath; // Store the single uploaded file URL
            } else {
                header("Location: halamanTambah.php?error_message=" . urlencode("Gagal mengunggah file " . htmlspecialchars($fileName) . ". Mohon coba lagi."));
                exit();
            }
        } else {
            $rejectedFiles[] = $file['name']; // File type not allowed
            header("Location: halamanTambah.php?error_message=" . urlencode("File yang diunggah bukan format gambar yang didukung. Hanya JPG, JPEG, PNG, GIF diperbolehkan."));
            exit();
        }
    } else {
        // Handle no file uploaded or other upload errors
        $error_code = $_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error_message_upload = "Tidak ada file yang diunggah atau terjadi kesalahan upload.";
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message_upload = "Ukuran file terlalu besar.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message_upload = "File hanya terunggah sebagian.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message_upload = "Minimal upload 1 foto.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message_upload = "Folder temp tidak ditemukan.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message_upload = "Gagal menyimpan file di disk.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message_upload = "Ekstensi PHP menghentikan unggahan file.";
                break;
        }
        header("Location: halamanTambah.php?error_message=" . urlencode($error_message_upload));
        exit();
    }

    // Ensure one image was successfully uploaded
    if (empty($uploadedFileUrl)) {
        header("Location: halamanTambah.php?error_message=" . urlencode("Tidak ada foto yang berhasil diunggah."));
        exit();
    }

    // Now, insert data into database
    try {
        // NEW: Added whatsapp_contact to INSERT statement
        $stmt = $conn->prepare("INSERT INTO donations (donor_id, item_name, item_description, item_image_url, status, donor_username, donor_location, item_count, category, item_condition, whatsapp_contact) VALUES (:donor_id, :item_name, :item_description, :item_image_url, 'Available', :donor_username, :donor_location, :item_count, :category, :item_condition, :whatsapp_contact)");

        $stmt->bindParam(':donor_id', $user_id);
        $stmt->bindParam(':item_name', $judul);
        $stmt->bindParam(':item_description', $deskripsi);
        $stmt->bindParam(':item_image_url', $uploadedFileUrl); // Single uploaded file URL
        $stmt->bindParam(':donor_username', $user_username);
        $stmt->bindParam(':donor_location', $user_location);
        $stmt->bindParam(':item_count', $jumlah);
        $stmt->bindParam(':category', $kategori);
        $stmt->bindParam(':item_condition', $kondisi);
        $stmt->bindParam(':whatsapp_contact', $whatsapp_contact); // Bind WhatsApp contact

        $stmt->execute();

        $upload_success = true;
        $upload_message = "Barang Berhasil Diupload!";
        // Collect data for display on the success page
        $item_details_for_display = [
            'judul' => $judul,
            'deskripsi' => $deskripsi,
            'lokasi' => $lokasi,
            'jumlah' => $jumlah,
            'kategori' => $kategori,
            'kondisi' => $kondisi,
            'whatsapp_contact' => $whatsapp_contact,
            'image_urls' => [$uploadedFileUrl], // Wrap single URL in an array for consistency with display loop
            'rejected_files' => $rejectedFiles // Still includes potential rejected files if any (though only one allowed now)
        ];

    } catch (PDOException $e) {
        error_log("Database error on upload: " . $e->getMessage()); // Log the error
        header("Location: halamanTambah.php?error_message=" . urlencode("Terjadi kesalahan database: " . $e->getMessage()));
        exit();
    }
}

// --- HTML Output Section (Only if upload_success is true) ---
if ($upload_success) :
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barang Berhasil Diupload!</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #E4F0D6; /* Matches your current theme */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .upload-result-box {
            background-color: #DCE9C9; /* Lighter shade, similar to your image */
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px; /* Adjust as needed */
            text-align: left;
            box-sizing: border-box;
        }
        h2 {
            color: #2F4A27; /* Dark green similar to your theme */
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.8em;
            font-weight: bold;
        }
        p {
            margin-bottom: 8px;
            color: #333;
            line-height: 1.5;
        }
        p span {
            font-weight: bold;
            color: #555;
            margin-right: 5px;
        }
        .photo-preview {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px; /* Increased gap for better spacing */
            margin-top: 30px; /* More space from text above */
            padding-top: 20px;
            border-top: 1px dashed #A3B3A7; /* Dotted line like in image */
        }
        .photo-preview img {
            max-width: 150px; /* Adjust size of preview images */
            height: auto;
            border: 1px solid #A3B3A7; /* Border color from your theme */
            border-radius: 4px;
            object-fit: cover; /* Ensure images fill their box */
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .error-message-inline { /* Renamed to avoid conflict with top-level error logic */
            color: #d9534f;
            font-weight: bold;
            margin-top: 20px;
            text-align: center;
        }
        .error-message-inline small {
            display: block;
            margin-top: 10px;
            font-weight: normal;
            font-size: 0.85em;
        }
        .back-button {
            display: inline-block;
            background: #6B8569; /* Button color from your header */
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s;
            margin-top: 30px; /* Space from photos/errors */
            text-align: center;
            width: fit-content;
            cursor: pointer;
            border: none;
            font-size: 1em;
        }
        .back-button:hover {
            background: #5A7058; /* Darker shade on hover */
        }
        .button-container {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="upload-result-box">
        <h2>Barang Berhasil Diupload!</h2>

        <p><span>Judul:</span> <?= htmlspecialchars($item_details_for_display['judul'] ?? 'N/A') ?></p>
        <p><span>Deskripsi:</span> <?= htmlspecialchars($item_details_for_display['deskripsi'] ?? 'N/A') ?></p>
        <p><span>Lokasi:</span> <?= htmlspecialchars($item_details_for_display['lokasi'] ?? 'N/A') ?></p>
        <p><span>Jumlah:</span> <?= htmlspecialchars($item_details_for_display['jumlah'] ?? 'N/A') ?></p>
        <p><span>Kategori:</span> <?= htmlspecialchars($item_details_for_display['kategori'] ?? 'N/A') ?></p>
        <p><span>Kondisi:</span> <?= htmlspecialchars($item_details_for_display['kondisi'] ?? 'N/A') ?></p>
        <p><span>WhatsApp:</span> <?= htmlspecialchars($item_details_for_display['whatsapp_contact'] ?? 'N/A') ?></p>


        <?php if (!empty($item_details_for_display['image_urls'])) : ?>
            <div class="photo-preview">
                <?php foreach ($item_details_for_display['image_urls'] as $url) : ?>
                    <img src="<?= htmlspecialchars($url) ?>" alt="Foto Barang">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($item_details_for_display['rejected_files'])) : ?>
            <div class="error-message-inline">
                File berikut tidak diproses karena format tidak didukung:<br>
                <?php foreach ($item_details_for_display['rejected_files'] as $file) : ?>
                    - <?= htmlspecialchars($file) ?><br>
                <?php endforeach; ?>
                <small>Hanya file gambar (JPG, JPEG, PNG, GIF) yang diunggah.</small>
            </div>
        <?php endif; ?>

        <div class="button-container">
            <button class="back-button" onclick="location.href='profile.php'">Kembali ke Profil</button>
        </div>
    </div>
</body>
</html>
<?php
// End the script after displaying the success page
exit();

else : // This else block should only be reached if an unexpected error occurs before redirection
    echo "<p>Terjadi kesalahan yang tidak terduga. Mohon kembali dan coba lagi.</p>";
    echo '<div style="text-align: center; margin-top: 20px;"><a href="halamanTambah.php" style="display: inline-block; background: #059669; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; transition: background 0.3s;">Kembali ke Form</a></div>';
    exit();
endif;
?>