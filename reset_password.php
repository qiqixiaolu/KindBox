<?php
session_start();
require 'db.php';

$token = $_GET['token'] ?? '';
$message = '';

if (empty($token)) {
    $message = "Token reset password tidak valid.";
} else {
    try {
        $stmt = $conn->prepare("SELECT id, reset_token_expiry FROM users WHERE reset_token = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = "Token reset password tidak valid atau sudah digunakan.";
        } elseif (new DateTime() > new DateTime($user['reset_token_expiry'])) {
            $message = "Token reset password telah kedaluwarsa. Silakan minta link reset baru.";
        }
    } catch (PDOException $e) {
        $message = "Terjadi kesalahan sistem saat memvalidasi token: " . $e->getMessage();
    }
}

// Proses pembaruan password saat form disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password']) && isset($_POST['confirm_password']) && isset($_POST['token'])) {
    $new_password = trim($_POST['password']);
    $confirm_new_password = trim($_POST['confirm_password']);
    $submitted_token = $_POST['token']; // Pastikan token juga disubmit di form

    if (empty($new_password) || empty($confirm_new_password)) {
        $message = "Password baru dan konfirmasi password tidak boleh kosong.";
    } elseif ($new_password !== $confirm_new_password) {
        $message = "Password baru dan konfirmasi password tidak cocok.";
    } elseif (strlen($new_password) < 6) { // Contoh: minimal 6 karakter
        $message = "Password minimal 6 karakter.";
    } else {
        try {
            // Validasi ulang token untuk keamanan
            $stmt = $conn->prepare("SELECT id, reset_token_expiry FROM users WHERE reset_token = :token");
            $stmt->bindParam(':token', $submitted_token);
            $stmt->execute();
            $user_for_reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user_for_reset || new DateTime() > new DateTime($user_for_reset['reset_token_expiry'])) {
                $message = "Token reset password tidak valid atau telah kedaluwarsa.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password dan hapus token
                $update_stmt = $conn->prepare("UPDATE users SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE id = :id");
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':id', $user_for_reset['id']);
                $update_stmt->execute();

                $_SESSION['login_error'] = "Password Anda berhasil direset. Silakan login dengan password baru Anda.";
                header("Location: halamanLogin.php");
                exit();
            }
        } catch (PDOException $e) {
            $message = "Terjadi kesalahan sistem saat mereset password: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="KindBoxStyles.css">
</head>
<body>
    <div class="container">
        <div class="form-section">
            <div class="logo">
                <img src="https://lh3.googleusercontent.com/d/1q0PjKVPzmVWdLiJLpwfJ1rtl-ttyvtDp=w1000" alt="logo" width="40" height="40">
                <h1 class="title">KindBox</h1>
            </div>

            <p class="welcome-text">Reset Password Anda</p>
            <h2 class="subtitle">Atur password baru Anda.</h2>

            <?php if (!empty($message)) : ?>
                <div class="error-message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($token) && empty($message)) : // Tampilkan form hanya jika token valid dan tidak ada pesan error ?>
                <form action="reset_password.php" method="post">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="input-group">
                        <label for="password">Password Baru</label>
                        <input type="password" id="password" name="password" placeholder="********" required>
                    </div>
                    <div class="input-group">
                        <label for="confirm_password">Konfirmasi Password Baru</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="********" required>
                    </div>
                    <button class="submit-btn" type="submit">Reset Password</button>
                </form>
            <?php endif; ?>

            <p class="member-text">
                Kembali ke
                <a class="signup-link" href="halamanLogin.php">Login</a>
            </p>
        </div>

        <div class="image-section desktop-only">
            <img src="https://lh3.googleusercontent.com/d/14ER28wan2OcQfEnCixA0hP78jr5qFdO1=w1000" alt="Illustration" width="400" height="400">
        </div>
    </div>
</body>
</html>