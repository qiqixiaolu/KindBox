<?php
session_start();
require 'db.php'; // Koneksi ke database

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: halamanLogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = ''; // Untuk pesan sukses atau error

// Ambil data user yang ada saat ini
$user_data = null;
try {
    $stmt = $conn->prepare("SELECT full_name, username, email, location, profile_picture_url FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        session_destroy();
        header("Location: halamanLogin.php");
        exit();
    }
} catch (PDOException $e) {
    $message = "Terjadi kesalahan sistem saat mengambil data profil: " . $e->getMessage();
}

// Proses jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_full_name = trim($_POST['full_name']);
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_location = trim($_POST['location']);

    $update_fields = [];
    $update_params = [':user_id' => $user_id];

    // Periksa apakah username atau email sudah digunakan oleh user lain
    try {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :user_id");
        $check_stmt->bindParam(':username', $new_username);
        $check_stmt->bindParam(':email', $new_email);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            $message = "Username atau Email sudah digunakan oleh pengguna lain.";
        } else {
            // Update Full Name
            if ($new_full_name !== $user_data['full_name']) {
                $update_fields[] = 'full_name = :full_name';
                $update_params[':full_name'] = $new_full_name;
            }

            // Update Username
            if ($new_username !== $user_data['username']) {
                $update_fields[] = 'username = :username';
                $update_params[':username'] = $new_username;
            }

            // Update Email
            if ($new_email !== $user_data['email']) {
                $update_fields[] = 'email = :email';
                $update_params[':email'] = $new_email;
            }

            // Update Location
            if ($new_location !== $user_data['location']) {
                $update_fields[] = 'location = :location';
                $update_params[':location'] = $new_location;
            }

            // Tangani Upload Foto Profil
            $profile_picture_upload_dir = 'uploads/profile_pictures/';
            if (!file_exists($profile_picture_upload_dir)) {
                mkdir($profile_picture_upload_dir, 0777, true);
            }

            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['profile_picture']['tmp_name'];
                $file_name = $_FILES['profile_picture']['name'];
                $file_size = $_FILES['profile_picture']['size'];
                $file_type = $_FILES['profile_picture']['type'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $max_file_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($file_ext, $allowed_extensions)) {
                    $message = "Hanya file JPG, JPEG, PNG, dan GIF yang diizinkan untuk foto profil.";
                } elseif ($file_size > $max_file_size) {
                    $message = "Ukuran file foto profil terlalu besar (maks 5MB).";
                } else {
                    $unique_file_name = uniqid('profile_', true) . '.' . $file_ext;
                    $target_file_path = $profile_picture_upload_dir . $unique_file_name;

                    if (move_uploaded_file($file_tmp_name, $target_file_path)) {
                        // Hapus foto lama jika ada dan bukan default
                        if (!empty($user_data['profile_picture_url']) && strpos($user_data['profile_picture_url'], 'https://') === false && file_exists($user_data['profile_picture_url'])) {
                            unlink($user_data['profile_picture_url']);
                        }
                        $update_fields[] = 'profile_picture_url = :profile_picture_url';
                        $update_params[':profile_picture_url'] = $target_file_path;
                    } else {
                        $message = "Gagal mengunggah foto profil.";
                    }
                }
            }

            // Lanjutkan update jika tidak ada error upload atau validasi
            if (empty($message) && !empty($update_fields)) {
                $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :user_id";
                $update_stmt = $conn->prepare($sql);
                $update_stmt->execute($update_params);

                // Perbarui data di sesi setelah update berhasil
                $_SESSION['full_name'] = $new_full_name;
                $_SESSION['username'] = $new_username;
                $_SESSION['email'] = $new_email;

                // Perbarui $user_data untuk tampilan form
                foreach ($update_params as $key => $value) {
                    $clean_key = ltrim($key, ':');
                    if (isset($user_data[$clean_key])) {
                        $user_data[$clean_key] = $value;
                    }
                }
                $message = "Profil berhasil diperbarui!";
            } elseif (empty($message)) {
                $message = "Tidak ada perubahan yang disimpan.";
            }
        }
    } catch (PDOException $e) {
        $message = "Terjadi kesalahan sistem saat memperbarui profil: " . $e->getMessage();
    }
}

// Data yang akan ditampilkan di form (setelah update atau saat pertama kali load)
$display_full_name = htmlspecialchars($user_data['full_name'] ?? '');
$display_username = htmlspecialchars($user_data['username'] ?? '');
$display_email = htmlspecialchars($user_data['email'] ?? '');
$display_location = htmlspecialchars($user_data['location'] ?? '');
$display_profile_picture_url = htmlspecialchars($user_data['profile_picture_url'] ?? 'https://storage.googleapis.com/a1aa/image/bd3a933d-2733-4863-bea1-4a32e05e398e.jpg'); // Default avatar
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&amp;display=swap" rel="stylesheet"/>
    <style>
        body { font-family: "Poppins", sans-serif; }
        .error-message, .success-message {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: bold;
            text-align: center;
        }
        .error-message {
            background: #fee2e2;
            color: #b91c1c;
        }
        .success-message {
            background: #d1fae5;
            color: #065f46;
        }
        .input-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
            color: #0f2a1d;
        }
        .input-group input {
            width: 100%;
            padding: 10px;
            background: #d1fae5;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            color: #0f2a1d;
        }
        .input-group input:focus {
            border-color: #059669;
            box-shadow: 0 0 4px #059669;
        }
        .submit-btn {
            background: #059669;
            color: white;
            padding: 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
            width: 100%;
            margin-top: 15px;
        }
        .submit-btn:hover {
            background: #047857;
        }
    </style>
</head>
<body class="bg-[#E4F0D6] min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-lg mx-auto">
        <h2 class="text-2xl font-bold text-center mb-6 text-gray-800">Edit Profil Anda</h2>

        <?php if (!empty($message)) : ?>
            <div class="<?= (strpos($message, 'berhasil') !== false || strpos($message, 'Tidak ada perubahan') !== false) ? 'success-message' : 'error-message' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form action="edit_profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div class="flex flex-col items-center mb-6">
                <img id="profilePreview" src="<?= $display_profile_picture_url ?>" alt="Foto Profil" class="w-32 h-32 rounded-full object-cover border-2 border-[#6B8569] mb-4">
                <label for="profile_picture" class="cursor-pointer bg-[#A3B3A7] text-black font-semibold text-xs rounded-md py-2 px-4 hover:bg-[#8e9d92]">
                    Ubah Foto Profil
                </label>
                <input type="file" id="profile_picture" name="profile_picture" class="hidden" accept="image/jpeg,image/png,image/gif" onchange="previewProfilePicture(event)">
                <p class="text-gray-500 text-xs mt-2">Max 5MB (JPG, PNG, GIF)</p>
            </div>

            <div class="input-group">
                <label for="full_name">Nama Lengkap</label>
                <input type="text" id="full_name" name="full_name" value="<?= $display_full_name ?>" required>
            </div>

            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= $display_username ?>" required>
            </div>

            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= $display_email ?>" required>
            </div>

            <div class="input-group">
                <label for="location">Lokasi</label>
                <input type="text" id="location" name="location" value="<?= $display_location ?>" placeholder="Masukkan lokasi Anda" required>
                <button type="button" onclick="detectLocation()" class="bg-[#DCE9C9] text-black font-semibold text-xs rounded-md py-2 w-full mt-2 hover:bg-[#c2d0b5]">
                    Deteksi Lokasi Otomatis
                </button>
            </div>

            <button type="submit" class="submit-btn">Simpan Perubahan</button>
            <button type="button" onclick="window.location.href='halamanBeranda.php'" class="submit-btn !bg-gray-400 hover:!bg-gray-500">Batal</button>
        </form>
    </div>

    <script>
        // Fungsi untuk preview foto profil
        function previewProfilePicture(event) {
            const [file] = event.target.files;
            if (file) {
                document.getElementById('profilePreview').src = URL.createObjectURL(file);
            }
        }

        // Fungsi untuk deteksi lokasi otomatis
        function detectLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const latitude = position.coords.latitude;
                        const longitude = position.coords.longitude;

                        // Gunakan layanan geocoding terbalik (misalnya OpenStreetMap Nominatim)
                        // Perlu diingat: layanan pihak ketiga memiliki batasan penggunaan
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}`)
                            .then(response => response.json())
                            .then(data => {
                                let detectedLocation = "Lokasi tidak spesifik";
                                if (data.address) {
                                    // Anda bisa menyesuaikan format alamat yang ingin ditampilkan
                                    // Contoh: kota, kabupaten, provinsi
                                    const city = data.address.city || data.address.town || data.address.village || '';
                                    const county = data.address.county || '';
                                    const state = data.address.state || '';
                                    const country = data.address.country || '';

                                    const parts = [];
                                    if (city) parts.push(city);
                                    if (county && county !== city) parts.push(county); // Hindari duplikasi jika county = city
                                    if (state) parts.push(state);
                                    if (country) parts.push(country);

                                    if (parts.length > 0) {
                                        detectedLocation = parts.join(', ');
                                    } else {
                                        detectedLocation = data.display_name; // Fallback ke nama lengkap
                                    }
                                }
                                document.getElementById('location').value = detectedLocation;
                            })
                            .catch(error => {
                                alert("Gagal mendapatkan nama lokasi dari koordinat: " + error.message);
                                console.error("Error geocoding:", error);
                            });
                    },
                    (error) => {
                        let errorMessage = "Gagal mendeteksi lokasi Anda: ";
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage += "Pengguna menolak permintaan Geolocation.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += "Informasi lokasi tidak tersedia.";
                                break;
                            case error.TIMEOUT:
                                errorMessage += "Waktu permintaan untuk mendapatkan lokasi habis.";
                                break;
                            case error.UNKNOWN_ERROR:
                                errorMessage += "Terjadi kesalahan yang tidak diketahui.";
                                break;
                        }
                        alert(errorMessage + " Pastikan GPS/lokasi Anda aktif dan browser memiliki izin.");
                        console.error("Geolocation error:", error);
                    }
                );
            } else {
                alert("Browser Anda tidak mendukung Geolocation.");
            }
        }
    </script>
</body>
</html>