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
    // Fetch user's username and location to potentially pre-fill or use in upload.php
    $stmt = $conn->prepare("SELECT username, location FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_info) {
        $user_username = htmlspecialchars($user_info['username']);
        $user_location = htmlspecialchars($user_info['location']);
    } else {
        // If user data not found, something is wrong, redirect to logout
        session_destroy();
        header("Location: halamanLogin.php");
        exit();
    }
} catch (PDOException $e) {
    // Tangani error database
    die("Error mengambil data user: " . $e->getMessage());
}

$form_error_message = '';
if (isset($_GET['error_message'])) {
    $form_error_message = htmlspecialchars($_GET['error_message']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Form Donasi Barang</title>
    <link rel="stylesheet" href="style.css" /> 
    <style>
        /* Add some basic styling for the form if style.css is not enough */
        body {
            font-family: Arial, sans-serif;
            background-color: #E4F0D6; /* Matches your profile background */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 900px;
            box-sizing: border-box;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo img {
            width: 60px;
            height: 60px;
            margin-bottom: 10px;
            border-radius: 50%;
        }
        .title {
            font-size: 2em;
            color: #333;
            margin: 0;
        }
        .form-layout {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .form-left, .form-right {
            flex: 1;
            min-width: 300px; /* Adjust as needed */
        }
        .input-group {
            margin-bottom: 20px;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        .input-group input[type="text"],
        .input-group input[type="number"],
        .input-group textarea,
        .input-group select,
        .input-group input[type="tel"] { /* Added type="tel" here */
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .input-group textarea {
            resize: vertical;
        }
        .input-group input[type="file"] {
            padding: 10px 0;
        }
        .help-text {
            font-size: 0.85em;
            color: #777;
            margin-top: 5px;
        }
        .error-message {
            color: #d9534f;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .image-preview-container {
            display: flex;
            flex-wrap: wrap; /* Keep flex-wrap for preview-item styling, even if only one item */
            gap: 10px;
            margin-top: 15px;
        }
        .preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background-color: #f9f9f9;
        }
        .preview-image {
            max-width: 100%;
            max-height: 80%;
            object-fit: contain;
            display: block; /* Ensure it respects max-height */
        }
        .preview-filename {
            font-size: 0.7em;
            color: #555;
            padding: 0 5px;
            word-break: break-all;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            box-sizing: border-box;
        }
        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: rgba(255, 0, 0, 0.7);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.2em;
            cursor: pointer;
            line-height: 1;
        }
        .submit-btn {
            background-color: #6B8569; /* Matches your header color */
            color: white;
            padding: 15px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        .submit-btn:hover {
            background-color: #5A7058;
        }
        .alert-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="https://storage.googleapis.com/a1aa/image/ba6cfd8e-3595-41ca-6af3-146bbdb8fc60.jpg" alt="KindBox Logo">
            <h1 class="title">KindBox</h1>
        </div>
        <?php if (!empty($form_error_message)): ?>
            <div class="alert-message">
                <?= $form_error_message ?>
            </div>
        <?php endif; ?>
        <form action="upload.php" method="POST" enctype="multipart/form-data" class="form-layout" onsubmit="return validateFileCount()">
            <div class="form-left">
                <div class="input-group">
                    <label for="judul">Judul</label>
                    <input type="text" id="judul" name="judul" placeholder="Nama Barang" required>
                </div>
                <div class="input-group">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" placeholder="Ceritakan tentang barangnya..." rows="4" required></textarea>
                </div>
                <div class="input-group">
                    <label for="lokasi">Lokasi</label>
                    <input type="text" id="lokasi" name="lokasi" placeholder="Lokasi Barang" value="<?= $user_location ?>" required>
                </div>
                <div class="input-group">
                    <label for="jumlah">Jumlah</label>
                    <input type="number" id="jumlah" name="jumlah" min="1" value="1" required>
                </div>
                <div class="input-group">
                    <label for="whatsapp">Nomor WhatsApp (628xxxxxxxxxx)</label>
                    <input type="tel" id="whatsapp" name="whatsapp" placeholder="6281234567890" pattern="^628[0-9]{8,11}$" required>
                    <small class="help-text">Format: Dimulai dengan 628, tanpa spasi atau karakter lain.</small>
                </div>
            </div>
            <div class="form-right">
                <div class="input-group">
                    <label for="kategori">Kategori</label>
                    <select id="kategori" name="kategori" required>
                        <option value="">-- Pilih Kategori --</option>
                        <option value="Pakaian">Pakaian</option>
                        <option value="Elektronik">Elektronik</option>
                        <option value="Buku">Buku</option>
                        <option value="Perabotan">Perabotan</option>
                        <option value="Mainan">Mainan</option>
                        <option value="Lain-lain">Lain-lain</option>
                    </select>
                </div>
                <div class="input-group">
                    <label for="kondisi">Kondisi Barang</label>
                    <select id="kondisi" name="kondisi" required>
                        <option value="">-- Pilih Kondisi --</option>
                        <option value="Baru">Baru</option>
                        <option value="Bekas (Baik)">Bekas (Baik)</option>
                        <option value="Bekas (Wajar)">Bekas (Wajar)</option>
                    </select>
                </div>
                <div class="input-group">
                    <label for="foto">Foto Barang </label> <input type="file" id="foto" name="foto" onchange="previewImages()"> <p class="help-text">Pilih maksimal 1 foto (hanya JPG, JPEG, PNG, GIF)</p> <div id="fileTypeError" class="error-message" style="display: none;"></div>
                    <div id="imagePreviewContainer" class="image-preview-container"></div>
                    <div id="fileCountError" class="error-message" style="display: none;"></div>
                </div>
                <button type="submit" class="submit-btn">KIRIM</button>
            </div>
        </form>
    </div>
    <script>
        // Changed previousFiles to currentFile to manage a single file
        let currentFile = null; 
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

        function previewImages() {
            const preview = document.getElementById('imagePreviewContainer');
            const fileInput = document.getElementById('foto');
            const errorElement = document.getElementById('fileCountError');
            const fileTypeError = document.getElementById('fileTypeError');

            errorElement.style.display = 'none';
            fileTypeError.style.display = 'none';
            fileTypeError.innerHTML = '';

            const newFiles = Array.from(fileInput.files); // Still an array, but will only contain one file from single input

            if (newFiles.length === 0) { // If user clears the selection
                currentFile = null;
                renderPreview();
                return;
            }

            const file = newFiles[0]; // Get the single file

            if (!allowedTypes.includes(file.type)) {
                fileTypeError.innerHTML = 'File ini bukan format gambar yang didukung. Hanya JPG, JPEG, PNG, GIF diperbolehkan.';
                fileTypeError.style.display = 'block';
                currentFile = null; // Clear invalid file
                renderPreview();
                return;
            }

            currentFile = file; // Store the valid file
            renderPreview(); // Render the preview
        }

        function removeImage() { // No index needed, as it's a single file
            currentFile = null;
            renderPreview();
        }

        function renderPreview() {
            const preview = document.getElementById('imagePreviewContainer');
            preview.innerHTML = ''; // Clear existing previews

            if (currentFile) { // If there's a file to preview
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const imgContainer = document.createElement('div');
                    imgContainer.className = 'preview-item';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.title = currentFile.name;
                    img.className = 'preview-image';

                    const filename = document.createElement('p');
                    filename.className = 'preview-filename';
                    filename.textContent = currentFile.name.length > 15 ? currentFile.name.substring(0, 15) + '...' : currentFile.name;

                    const removeBtn = document.createElement('span');
                    removeBtn.className = 'remove-image';
                    removeBtn.innerHTML = '×';
                    removeBtn.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeImage(); // Call removeImage to clear the single file
                    };

                    imgContainer.appendChild(img);
                    imgContainer.appendChild(filename);
                    imgContainer.appendChild(removeBtn);
                    preview.appendChild(imgContainer);
                };
                reader.readAsDataURL(currentFile);
            }
            updateFileInput(); // Update the native file input element
            validateFileCount(); // Re-validate after rendering
        }

        function updateFileInput() {
            const fileInput = document.getElementById('foto');
            const dataTransfer = new DataTransfer();
            if (currentFile) { // Add file to DataTransfer object if available
                dataTransfer.items.add(currentFile);
            }
            fileInput.files = dataTransfer.files; // Assign the DataTransfer object to the input's files
        }

        function validateFileCount() {
            const errorElement = document.getElementById('fileCountError');
            errorElement.style.display = 'none'; // Hide on validation start

            if (!currentFile) { // Check if a file is present (required for upload)
                errorElement.textContent = 'Minimal upload 1 foto.';
                errorElement.style.display = 'block';
                return false;
            }
            
            // No need for previousFiles.length > 5 check here as input is single file,
            // and `currentFile` implicitly handles only one.

            const whatsappInput = document.getElementById('whatsapp');
            // Check validity based on the pattern set in HTML
            if (!whatsappInput.checkValidity()) {
                alert("Format Nomor WhatsApp tidak valid. Harap masukkan nomor yang dimulai dengan 628 dan memiliki 11-14 digit (misal: 6281234567890).");
                whatsappInput.focus();
                return false;
            }

            return true; // All validations passed
        }
    </script>
</body>
</html>