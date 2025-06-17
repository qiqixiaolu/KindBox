<?php
namespace KindBox; // Pastikan ini ada dan penulisan 'KindBox' sesuai dengan di composer.json

class UserManager
{
    private $conn;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }

    public function registerUser($fullName, $username, $email, $password, $confirmPassword)
    {
        if (empty($fullName) || empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
            return "Semua field harus diisi.";
        }

        if ($password !== $confirmPassword) {
            return "Password tidak cocok.";
        }

        try {
            // Check if username or email already exists
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return "Username atau email sudah digunakan.";
            } else {
                // Save new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $this->conn->prepare("INSERT INTO users (full_name, username, password, email) VALUES (:full_name, :username, :password, :email)");
                $stmt->bindParam(':full_name', $fullName);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':email', $email);
                $stmt->execute();

                return "success";
            }
        } catch (\PDOException $e) {
            // In a real application, you'd log this, not expose to user
            return "Error: " . $e->getMessage();
        }
    }

    public function loginUser($email, $password)
    {
        if (empty($email) || empty($password)) {
            return "Email dan Password harus diisi.";
        }

        try {
            $stmt = $this->conn->prepare("SELECT id, username, email, full_name, password FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (password_verify($password, $user['password'])) {
                    // For testing, we just return the user array on success.
                    // Session handling will be outside this unit.
                    return ['id' => $user['id'], 'username' => $user['username'], 'email' => $user['email'], 'full_name' => $user['full_name']];
                } else {
                    return "Password salah.";
                }
            } else {
                return "Email tidak ditemukan.";
            }
        } catch (\PDOException $e) {
            return "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}