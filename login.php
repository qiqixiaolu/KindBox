<?php
session_start();
require 'db.php';

// Initialize error variable
$_SESSION['login_error'] = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = "Email dan Password harus diisi."; // Store error in session
        header("Location: halamanLogin.php"); // Redirect back to login page
        exit();
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];

                    setcookie("kindbox_user", $user['username'], time() + (86400 * 7), "/");
                    header("Location: halamanBeranda.php");
                    exit();
                } else {
                    $_SESSION['login_error'] = "Password salah."; // Store error in session
                    header("Location: halamanLogin.php"); // Redirect back to login page
                    exit();
                }
            } else {
                $_SESSION['login_error'] = "Email tidak ditemukan."; // Store error in session
                header("Location: halamanLogin.php"); // Redirect back to login page
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['login_error'] = "Terjadi kesalahan sistem: " . $e->getMessage(); // Store error in session
            header("Location: halamanLogin.php"); // Redirect back to login page
            exit();
        }
    }
}
?>