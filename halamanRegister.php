<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KindBox Register</title>
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

            <p class="welcome-text">SELAMAT DATANG👋🏻</p>
            <h2 class="subtitle">Buatlah akunmu!</h2>

            <?php
            session_start(); // Start session to access session variables
            if (isset($_SESSION['register_error']) && !empty($_SESSION['register_error'])) {
                echo '<div class="error-message">' . htmlspecialchars($_SESSION['register_error']) . '</div>';
                unset($_SESSION['register_error']); // Clear the error message after displaying it
            }
            ?>

            <form action="register.php" method="post">
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="kindbox@gmail.com" required>
                </div>
                <div class="input-group">
                    <label for="nama">Nama</label>
                    <input type="text" id="nama" name="nama" placeholder="Admin Kindbox" required>
                </div>
                <div class="input-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="kindbox" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="********" required>
                </div>
                <div class="input-group">
                    <label for="konfirmasi">Konfirmasi Password</label>
                    <input type="password" id="konfirmasi" name="konfirmasi" placeholder="********" required>
                </div>
                <button class="submit-btn" type="submit">DAFTAR</button>
            </form>

            <p class="member-text">
                Sudah memiliki akun?
                <a class="signup-link" href="halamanLogin.php">MASUK</a> </p>
        </div>

        <div class="image-section desktop-only">
            <img src="imagery1-kindbox.png" alt="Illustration" width="400" height="400">
        </div>
    </div>
</body>
</html>