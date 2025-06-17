<?php
namespace KindBox; // Pastikan ini ada dan penulisan 'KindBox' sesuai

class InterestService // PASTIKAN NAMA KELASNYA 'InterestService'
{
    private $conn;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }

    public function submitInterest(int $itemId, int $userId, array $postData)
    {
        $nama = $postData['nama'] ?? '';
        $alamat = $postData['alamat'] ?? '';
        $jumlah = intval($postData['jumlah'] ?? 0);
        $item_alasan = $postData['item_alasan'] ?? '';
        $whatsapp_user_contact = $postData['whatsapp_user_contact'] ?? '';

        if (empty($nama) || empty($alamat) || $jumlah <= 0 || empty($item_alasan) || empty($whatsapp_user_contact)) {
            return "Harap lengkapi semua bidang dan pastikan jumlah barang valid.";
        }

        try {
            // Check if item exists and is available
            $stmt_check_item = $this->conn->prepare("SELECT item_count FROM donations WHERE id = :item_id AND item_count > 0");
            $stmt_check_item->bindParam(':item_id', $itemId);
            $stmt_check_item->execute();
            $item_info = $stmt_check_item->fetch(\PDO::FETCH_ASSOC);

            if (!$item_info) {
                return "Barang tidak ditemukan atau sudah habis.";
            }

            // Check if the user has already expressed interest in this item
            $stmt_check_interest = $this->conn->prepare("SELECT COUNT(*) FROM interests WHERE item_id = :item_id AND user_id = :user_id");
            $stmt_check_interest->bindParam(':item_id', $itemId);
            $stmt_check_interest->bindParam(':user_id', $userId);
            $stmt_check_interest->execute();
            if ($stmt_check_interest->fetchColumn() > 0) {
                return "Anda sudah mengajukan minat untuk barang ini.";
            }

            // Insert into interests table
            $stmt_insert_interest = $this->conn->prepare("
                INSERT INTO interests (item_id, user_id, nama, alamat, jumlah, item_alasan, whatsapp_user_contact, status)
                VALUES (:item_id, :user_id, :nama, :alamat, :jumlah, :item_alasan, :whatsapp_user_contact, 'pending')
            ");
            $stmt_insert_interest->bindParam(':item_id', $itemId);
            $stmt_insert_interest->bindParam(':user_id', $userId);
            $stmt_insert_interest->bindParam(':nama', $nama);
            $stmt_insert_interest->bindParam(':alamat', $alamat);
            $stmt_insert_interest->bindParam(':jumlah', $jumlah);
            $stmt_insert_interest->bindParam(':item_alasan', $item_alasan);
            $stmt_insert_interest->bindParam(':whatsapp_user_contact', $whatsapp_user_contact);

            if ($stmt_insert_interest->execute()) {
                return "success";
            } else {
                return "Gagal mengajukan minat. Silakan coba lagi.";
            }
        } catch (\PDOException $e) {
            return "Terjadi kesalahan database: " . $e->getMessage();
        }
    }
}