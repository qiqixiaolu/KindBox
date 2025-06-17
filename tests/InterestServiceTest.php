<?php
use PHPUnit\Framework\TestCase;
use KindBox\InterestService;

final class InterestServiceTest extends TestCase
{
    private $pdo;
    private $stmt;
    private $itemStmt; // For checking item availability
    private $interestCheckStmt; // For checking existing interest

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->stmt = $this->createMock(PDOStatement::class);
        $this->itemStmt = $this->createMock(PDOStatement::class);
        $this->interestCheckStmt = $this->createMock(PDOStatement::class);

        // Definisikan kueri SQL persis seperti di InterestService.php
        // Lalu normalisasi untuk perbandingan
        $sqlCheckItem = "SELECT item_count FROM donations WHERE id = :item_id AND item_count > 0";
        $sqlCheckInterest = "SELECT COUNT(*) FROM interests WHERE item_id = :item_id AND user_id = :user_id";
        
        // Perhatikan kueri INSERT, kita akan menggunakan cara baru untuk menormalisasinya
        $sqlInsertInterestRaw = "
            INSERT INTO interests (item_id, user_id, nama, alamat, jumlah, item_alasan, whatsapp_user_contact, status)
            VALUES (:item_id, :user_id, :nama, :alamat, :jumlah, :item_alasan, :whatsapp_user_contact, 'pending')
        ";
        // Fungsi normalisasi untuk string SQL multi-baris:
        // 1. Ganti semua whitespace (termasuk newline, tab, spasi) dengan satu spasi tunggal.
        // 2. Hapus spasi di awal dan akhir string.
        $sqlInsertInterestNormalized = trim(preg_replace('/\s+/', ' ', $sqlInsertInterestRaw));

        // Konfigurasi PDO untuk mengembalikan mock spesifik untuk kueri spesifik
        $this->pdo->method('prepare')
            ->will($this->returnCallback(function ($sql) use ($sqlCheckItem, $sqlCheckInterest, $sqlInsertInterestNormalized) {
                // Trim kueri yang masuk dari InterestService untuk perbandingan yang lebih kuat
                $trimmedSql = trim(preg_replace('/\s+/', ' ', $sql)); // Normalisasi kueri yang diterima juga

                if ($trimmedSql === trim($sqlCheckItem)) {
                    return $this->itemStmt;
                } elseif ($trimmedSql === trim($sqlCheckInterest)) {
                    return $this->interestCheckStmt;
                } elseif ($trimmedSql === $sqlInsertInterestNormalized) { // Gunakan string yang sudah dinormalisasi
                    return $this->stmt;
                }

                // Jika kueri tidak terduga, lemparkan Exception agar mudah di-debug
                throw new \Exception("Kueri SQL tidak terduga di prepare(): '" . $trimmedSql . "'");
            }));
    }

    public function testSubmitInterestSuccess()
    {
        // Mock item as available
        $this->itemStmt->method('execute')->willReturn(true);
        $this->itemStmt->method('fetch')->willReturn(['item_count' => 10]);

        // Mock no existing interest
        $this->interestCheckStmt->method('execute')->willReturn(true);
        $this->interestCheckStmt->method('fetchColumn')->willReturn(0);

        // Mock successful interest insert
        $this->stmt->method('execute')->willReturn(true);

        $postData = [
            'nama' => 'Penerima Test',
            'alamat' => 'Jl. Test No. 123',
            'jumlah' => 1,
            'item_alasan' => 'Sangat membutuhkan barang ini.',
            'whatsapp_user_contact' => '6281234567890'
        ];

        $interestService = new InterestService($this->pdo);
        $result = $interestService->submitInterest(
            1, // item_id
            2, // user_id (recipient)
            $postData
        );

        $this->assertEquals("success", $result);
    }

    public function testSubmitInterestEmptyFields()
    {
        $postData = [
            'nama' => '', // Empty name
            'alamat' => 'Jl. Test No. 123',
            'jumlah' => 1,
            'item_alasan' => 'Sangat membutuhkan barang ini.',
            'whatsapp_user_contact' => '6281234567890'
        ];

        $interestService = new InterestService($this->pdo);
        $result = $interestService->submitInterest(1, 2, $postData);

        $this->assertEquals("Harap lengkapi semua bidang dan pastikan jumlah barang valid.", $result);
    }

    public function testSubmitInterestItemNotFoundOrUnavailable()
    {
        // Mock item as not found or unavailable
        $this->itemStmt->method('execute')->willReturn(true);
        $this->itemStmt->method('fetch')->willReturn(false); // Item not found or count is 0

        $postData = [
            'nama' => 'Penerima Test',
            'alamat' => 'Jl. Test No. 123',
            'jumlah' => 1,
            'item_alasan' => 'Sangat membutuhkan barang ini.',
            'whatsapp_user_contact' => '6281234567890'
        ];

        $interestService = new InterestService($this->pdo);
        $result = $interestService->submitInterest(1, 2, $postData);

        $this->assertEquals("Barang tidak ditemukan atau sudah habis.", $result);
    }

    public function testSubmitInterestAlreadySubmitted()
    {
        // Mock item as available
        $this->itemStmt->method('execute')->willReturn(true);
        $this->itemStmt->method('fetch')->willReturn(['item_count' => 10]);

        // Mock existing interest
        $this->interestCheckStmt->method('execute')->willReturn(true);
        $this->interestCheckStmt->method('fetchColumn')->willReturn(1); // User already interested

        $postData = [
            'nama' => 'Penerima Test',
            'alamat' => 'Jl. Test No. 123',
            'jumlah' => 1,
            'item_alasan' => 'Sangat membutuhkan barang ini.',
            'whatsapp_user_contact' => '6281234567890'
        ];

        $interestService = new InterestService($this->pdo);
        $result = $interestService->submitInterest(1, 2, $postData);

        $this->assertEquals("Anda sudah mengajukan minat untuk barang ini.", $result);
    }
}