<?php
use PHPUnit\Framework\TestCase;
use KindBox\DonationService;

final class DonationServiceTest extends TestCase
{
    private $pdo;
    private $stmt;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->stmt = $this->createMock(PDOStatement::class);

        $this->pdo->method('prepare')->willReturn($this->stmt);
    }

    public function testProcessUploadSuccess()
    {
        $postData = [
            'judul' => 'Baju Bekas',
            'deskripsi' => 'Baju layak pakai',
            'lokasi' => 'Jakarta',
            'jumlah' => 5,
            'kategori' => 'Pakaian',
            'kondisi' => 'Bekas (Baik)',
            'whatsapp' => '6281234567890'
        ];

        $fileData = [
            'foto' => [
                'name' => 'baju.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php_upload_test_file', // Mocked temp path
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ]
        ];

        // Mock execute method to return true for successful DB insert
        $this->stmt->method('execute')->willReturn(true);

        $donationService = new DonationService($this->pdo, 'test_uploads/');
        $result = $donationService->processUpload(
            $postData,
            $fileData,
            1, // user_id
            'testuser',
            'Jakarta'
        );

        $this->assertEquals("success", $result);
    }

    public function testProcessUploadEmptyFields()
    {
        $postData = [
            'judul' => '', // Empty field
            'deskripsi' => 'Baju layak pakai',
            'lokasi' => 'Jakarta',
            'jumlah' => 5,
            'kategori' => 'Pakaian',
            'kondisi' => 'Bekas (Baik)',
            'whatsapp' => '6281234567890'
        ];

        $fileData = [
            'foto' => [
                'name' => 'baju.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php_upload_test_file',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ]
        ];

        $donationService = new DonationService($this->pdo, 'test_uploads/');
        $result = $donationService->processUpload(
            $postData,
            $fileData,
            1,
            'testuser',
            'Jakarta'
        );

        $this->assertEquals("Semua kolom wajib diisi dan jumlah harus minimal 1.", $result);
    }

    public function testProcessUploadInvalidWhatsappFormat()
    {
        $postData = [
            'judul' => 'Baju Bekas',
            'deskripsi' => 'Baju layak pakai',
            'lokasi' => 'Jakarta',
            'jumlah' => 5,
            'kategori' => 'Pakaian',
            'kondisi' => 'Bekas (Baik)',
            'whatsapp' => '081234567890' // Invalid format
        ];

        $fileData = [
            'foto' => [
                'name' => 'baju.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php_upload_test_file',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ]
        ];

        $donationService = new DonationService($this->pdo, 'test_uploads/');
        $result = $donationService->processUpload(
            $postData,
            $fileData,
            1,
            'testuser',
            'Jakarta'
        );

        $this->assertEquals("Format Nomor WhatsApp tidak valid. Harap masukkan format 628xxxxxxxxxx.", $result);
    }

    public function testProcessUploadNoFileUploaded()
    {
        $postData = [
            'judul' => 'Baju Bekas',
            'deskripsi' => 'Baju layak pakai',
            'lokasi' => 'Jakarta',
            'jumlah' => 5,
            'kategori' => 'Pakaian',
            'kondisi' => 'Bekas (Baik)',
            'whatsapp' => '6281234567890'
        ];

        $fileData = [
            'foto' => [
                'name' => '',
                'type' => '',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE, // No file uploaded
                'size' => 0
            ]
        ];

        $donationService = new DonationService($this->pdo, 'test_uploads/');
        $result = $donationService->processUpload(
            $postData,
            $fileData,
            1,
            'testuser',
            'Jakarta'
        );

        $this->assertEquals("Minimal upload 1 foto.", $result);
    }

    public function testProcessUploadInvalidFileType()
    {
        $postData = [
            'judul' => 'Dokumen',
            'deskripsi' => 'Dokumen PDF',
            'lokasi' => 'Surabaya',
            'jumlah' => 1,
            'kategori' => 'Lain-lain',
            'kondisi' => 'Baru',
            'whatsapp' => '628765432100'
        ];

        $fileData = [
            'foto' => [
                'name' => 'document.pdf',
                'type' => 'application/pdf', // Invalid file type
                'tmp_name' => '/tmp/php_upload_test_file_pdf',
                'error' => UPLOAD_ERR_OK,
                'size' => 2048
            ]
        ];

        $donationService = new DonationService($this->pdo, 'test_uploads/');
        $result = $donationService->processUpload(
            $postData,
            $fileData,
            1,
            'testuser',
            'Surabaya'
        );

        $this->assertEquals("File yang diunggah bukan format gambar yang didukung. Hanya JPG, JPEG, PNG, GIF diperbolehkan.", $result);
    }
}