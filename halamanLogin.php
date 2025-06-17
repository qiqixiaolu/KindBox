<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KindBox Login</title>
    <link rel="stylesheet" href="KindBoxStyles.css">
</head>
<body>
    <div class="container">
        <div class="form-section">
            <div class="logo">
                <img src="logo.png" alt="logo" width="40" height="40">
                <h1 class="title">KindBox</h1>
            </div>

            <div class="image-section mobile-only">
                <img src="imagery1-kindbox.png" alt="Illustration" width="400" height="400">
            </div>

            <p class="welcome-text">SELAMAT DATANG KEMBALI👋🏻</p>
            <h2 class="subtitle">Lanjutkan masuk ke akunmu!</h2>

            <?php
            if (isset($_SESSION['login_error']) && !empty($_SESSION['login_error'])) {
                echo '<div style="background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold;">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
                unset($_SESSION['login_error']);
            }
            ?>

            <form action="login.php" method="post">
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="kindbox@gmail.com" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="********" required>
                </div>
                <div style="text-align: right; margin-top: 5px; margin-bottom: 20px;"> <a class="signup-link" href="forgot_password.php" style="font-size: 14px;">Lupa Password?</a>
                </div>
                <button class="submit-btn" type="submit">MASUK</button>
            </form>

            <p class="member-text">
                Belum memiliki akun?
                <a class="signup-link" href="halamanRegister.php">DAFTAR</a>
            </p>
        </div>

        <div class="image-section desktop-only">
            <img src="imagery1-kindbox.png" alt="Illustration" width="400" height="400">
        </div>
    </div>
</body>
</html>