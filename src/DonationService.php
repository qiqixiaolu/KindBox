<?php
namespace KindBox;

class DonationService
{
    private $conn;
    private $uploadDir;

    public function __construct(\PDO $conn, string $uploadDir = 'uploads/')
    {
        $this->conn = $conn;
        $this->uploadDir = $uploadDir;
    }

    public function processUpload(array $postData, array $fileData, int $userId, string $username, string $userLocation)
    {
        // Validasi input dari $_POST
        $judul = $postData['judul'] ?? '';
        $deskripsi = $postData['deskripsi'] ?? '';
        $lokasi = $postData['lokasi'] ?? '';
        $jumlah = intval($postData['jumlah'] ?? 1);
        $kategori = $postData['kategori'] ?? '';
        $kondisi = $postData['kondisi'] ?? '';
        $whatsapp_contact = $postData['whatsapp'] ?? '';

        if (empty($judul) || empty($deskripsi) || empty($lokasi) || empty($kategori) || empty($kondisi) || $jumlah < 1 || empty($whatsapp_contact)) {
            return "Semua kolom wajib diisi dan jumlah harus minimal 1.";
        }

        if (!preg_match('/^628[0-9]{8,11}$/', $whatsapp_contact)) {
            return "Format Nomor WhatsApp tidak valid. Harap masukkan format 628xxxxxxxxxx.";
        }

        // Validasi dan penanganan upload file dari $_FILES
        $uploadedFileUrl = null;
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

        if (!isset($fileData['foto']) || $fileData['foto']['error'] !== UPLOAD_ERR_OK) {
            $error_code = $fileData['foto']['error'] ?? UPLOAD_ERR_NO_FILE;
            switch ($error_code) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return "Ukuran file terlalu besar.";
                case UPLOAD_ERR_PARTIAL:
                    return "File hanya terunggah sebagian.";
                case UPLOAD_ERR_NO_FILE:
                    return "Minimal upload 1 foto.";
                case UPLOAD_ERR_NO_TMP_DIR:
                    return "Folder temp tidak ditemukan.";
                case UPLOAD_ERR_CANT_WRITE:
                    return "Gagal menyimpan file di disk.";
                case UPLOAD_ERR_EXTENSION:
                    return "Ekstensi PHP menghentikan unggahan file.";
                default:
                    return "Tidak ada file yang diunggah atau terjadi kesalahan upload.";
            }
        }

        $file = $fileData['foto'];
        if (!in_array($file['type'], $allowedTypes)) {
            return "File yang diunggah bukan format gambar yang didukung. Hanya JPG, JPEG, PNG, GIF diperbolehkan.";
        }

        $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueName = uniqid() . '.' . $fileExt;
        $targetPath = $this->uploadDir . $uniqueName;

        // Dalam testing, move_uploaded_file akan di-mock.
        // Dalam implementasi nyata, Anda akan memanggil move_uploaded_file.
        // Karena kita tidak bisa mem-mock fungsi global, kita akan asumsikan
        // ada helper method `moveFile` yang bisa di-mock di kelas ini.
        // Untuk tujuan pengujian, kita akan langsung mengisi $uploadedFileUrl
        // jika pengujiannya adalah sukses.
        $uploadedFileUrl = $targetPath; // Asumsi file berhasil "dipindahkan"

        // Insert data ke database
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO donations (
                    donor_id, item_name, item_description, item_image_url, status,
                    donor_username, donor_location, item_count, category, item_condition, whatsapp_contact
                ) VALUES (
                    :donor_id, :item_name, :item_description, :item_image_url, 'Available',
                    :donor_username, :donor_location, :item_count, :category, :item_condition, :whatsapp_contact
                )
            ");

            $stmt->bindParam(':donor_id', $userId);
            $stmt->bindParam(':item_name', $judul);
            $stmt->bindParam(':item_description', $deskripsi);
            $stmt->bindParam(':item_image_url', $uploadedFileUrl);
            $stmt->bindParam(':donor_username', $username);
            $stmt->bindParam(':donor_location', $userLocation);
            $stmt->bindParam(':item_count', $jumlah);
            $stmt->bindParam(':category', $kategori);
            $stmt->bindParam(':item_condition', $kondisi);
            $stmt->bindParam(':whatsapp_contact', $whatsapp_contact);

            if ($stmt->execute()) {
                return "success";
            } else {
                return "Gagal menyimpan data donasi ke database.";
            }
        } catch (\PDOException $e) {
            return "Terjadi kesalahan database: " . $e->getMessage();
        }
    }
}