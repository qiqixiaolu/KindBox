<?php
session_start();
require 'db.php'; // koneksi ke database

$_SESSION['register_error'] = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['nama']); // Keep $_POST['nama'] as this is what the form sends
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['konfirmasi']);

    if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['register_error'] = "Semua field harus diisi.";
        header("Location: halamanRegister.php");
        exit();
    } elseif ($password !== $confirm_password) {
        $_SESSION['register_error'] = "Password tidak cocok.";
        header("Location: halamanRegister.php");
        exit();
    } else {
        try {
            // Cek apakah username atau email sudah ada
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $_SESSION['register_error'] = "Username atau email sudah digunakan.";
                header("Location: halamanRegister.php");
                exit();
            } else {
                // Simpan user baru
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Change 'nama' in the query to 'full_name'
                $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, email)
                                        VALUES (:full_name, :username, :password, :email)");
                // Change ':nama' binding to ':full_name' and use the $full_name variable
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':email', $email);
                $stmt->execute();

                // Redirect ke login setelah berhasil
                header("Location: halamanLogin.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['register_error'] = "Error: " . $e->getMessage();
            header("Location: halamanRegister.php");
            exit();
        }
    }
}
?>