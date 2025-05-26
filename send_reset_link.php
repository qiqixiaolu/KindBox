<?php
session_start();
require 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $_SESSION['forgot_password_message'] = "Email tidak boleh kosong.";
        header("Location: forgot_password.php");
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_id = $user['id'];

            // Buat token unik
            $token = bin2hex(random_bytes(32)); // Token 64 karakter heksadesimal
            $expiry = date("Y-m-d H:i:s", strtotime('+1 hour')); // Token berlaku 1 jam

            // Simpan token di database
            $update_stmt = $conn->prepare("UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :id");
            $update_stmt->bindParam(':token', $token);
            $update_stmt->bindParam(':expiry', $expiry);
            $update_stmt->bindParam(':id', $user_id);
            $update_stmt->execute();

            // Kirim email
            $mail = new PHPMailer(true);
            try {
                // Konfigurasi Server (ganti dengan detail SMTP Anda)
                $mail->isSMTP();
                $mail->Host       = 'smtp.yourdomain.com'; // Contoh: smtp.gmail.com
                $mail->SMTPAuth   = true;
                $mail->Username   = 'your_email@yourdomain.com'; // Email pengirim
                $mail->Password   = 'your_email_password'; // Password email pengirim
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Atau ENCRYPTION_SMTPS
                $mail->Port       = 587; // Atau 465 untuk SMTPS

                // Penerima
                $mail->setFrom('no-reply@kindbox.com', 'KindBox Support'); // Ganti dengan email Anda
                $mail->addAddress($email);

                // Konten Email
                $mail->isHTML(true);
                $mail->Subject = 'Reset Password KindBox Anda';
                $reset_link = 'http://localhost/kindbox/reset_password.php?token=' . $token; // Ganti dengan URL domain Anda
                $mail->Body    = 'Halo,<br><br>Anda telah meminta reset password. Silakan klik tautan berikut untuk mereset password Anda:<br><br>' .
                                 '<a href="' . $reset_link . '">' . $reset_link . '</a><br><br>' .
                                 'Tautan ini akan kedaluwarsa dalam 1 jam.<br><br>Jika Anda tidak meminta reset password ini, abaikan email ini.<br><br>' .
                                 'Terima kasih,<br>Tim KindBox';

                $mail->send();
                $_SESSION['forgot_password_message'] = "Link reset password telah dikirim ke email Anda.";
                header("Location: forgot_password.php");
                exit();
            } catch (Exception $e) {
                $_SESSION['forgot_password_message'] = "Gagal mengirim email. Mailer Error: {$mail->ErrorInfo}";
                header("Location: forgot_password.php");
                exit();
            }
        } else {
            $_SESSION['forgot_password_message'] = "Email tidak terdaftar.";
            header("Location: forgot_password.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['forgot_password_message'] = "Terjadi kesalahan sistem: " . $e->getMessage();
        header("Location: forgot_password.php");
        exit();
    }
} else {
    header("Location: forgot_password.php");
    exit();
}
?>