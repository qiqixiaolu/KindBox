<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use KindBox\UserManager;

final class UserManagerTest extends TestCase
{
    private $pdo;
    private $stmt;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->stmt = $this->createMock(PDOStatement::class);
        $this->pdo->method('prepare')->willReturn($this->stmt);
    }

    public function testRegisterUserSuccess()
    {
        $this->stmt->method('rowCount')->willReturn(0);
        $this->stmt->method('execute')->willReturn(true);

        $userManager = new UserManager($this->pdo);
        $result = $userManager->registerUser(
            'Test User',
            'testuser',
            'test@example.com',
            'password123',
            'password123'
        );

        $this->assertEquals("success", $result);
    }

    public function testRegisterUserEmptyFields()
    {
        $userManager = new UserManager($this->pdo);
        $result = $userManager->registerUser(
            '',
            'testuser',
            'test@example.com',
            'password123',
            'password123'
        );

        $this->assertEquals("Semua field harus diisi.", $result);
    }

    public function testRegisterUserPasswordMismatch()
    {
        $userManager = new UserManager($this->pdo);
        $result = $userManager->registerUser(
            'Test User',
            'testuser',
            'test@example.com',
            'password123',
            'wrongpassword'
        );

        $this->assertEquals("Password tidak cocok.", $result);
    }

    public function testRegisterUserAlreadyExists()
    {
        $this->stmt->method('rowCount')->willReturn(1);

        $userManager = new UserManager($this->pdo);
        $result = $userManager->registerUser(
            'Existing User',
            'existinguser',
            'existing@example.com',
            'password123',
            'password123'
        );

        $this->assertEquals("Username atau email sudah digunakan.", $result);
    }

    public function testLoginUserSuccess()
    {
        $this->stmt->method('rowCount')->willReturn(1);
        $this->stmt->method('fetch')->willReturn([
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com',
            'full_name' => 'Test User',
            'password' => password_hash('correctpassword', PASSWORD_DEFAULT)
        ]);

        $userManager = new UserManager($this->pdo);
        $result = $userManager->loginUser(
            'test@example.com',
            'correctpassword'
        );

        $this->assertIsArray($result);
        $this->assertEquals('testuser', $result['username']);
    }

    public function testLoginUserIncorrectPassword()
    {
        $this->stmt->method('rowCount')->willReturn(1);
        $this->stmt->method('fetch')->willReturn([
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com',
            'full_name' => 'Test User',
            'password' => password_hash('correctpassword', PASSWORD_DEFAULT)
        ]);

        $userManager = new UserManager($this->pdo);
        $result = $userManager->loginUser(
            'test@example.com',
            'wrongpassword'
        );

        $this->assertEquals("Password salah.", $result);
    }

    public function testLoginUserNotFound()
    {
        $this->stmt->method('rowCount')->willReturn(0);

        $userManager = new UserManager($this->pdo);
        $result = $userManager->loginUser(
            'nonexistent@example.com',
            'anypassword'
        );

        $this->assertEquals("Email tidak ditemukan.", $result);
    }
}
