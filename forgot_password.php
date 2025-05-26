<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password</title>
    <link rel="stylesheet" href="KindBoxStyles.css">
</head>
<body>
    <div class="container">
        <div class="form-section">
            <div class="logo">
                <img src="https://lh3.googleusercontent.com/d/1q0PjKVPzmVWdLiJLpwfJ1rtl-ttyvtDp=w1000" alt="logo" width="40" height="40">
                <h1 class="title">KindBox</h1>
            </div>

            <p class="welcome-text">Lupa Password?</p>
            <h2 class="subtitle">Masukkan email Anda untuk reset password.</h2>

            <?php
            session_start();
            if (isset($_SESSION['forgot_password_message']) && !empty($_SESSION['forgot_password_message'])) {
                echo '<div class="error-message">' . htmlspecialchars($_SESSION['forgot_password_message']) . '</div>';
                unset($_SESSION['forgot_password_message']);
            }
            ?>

            <form action="send_reset_link.php" method="post">
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="kindbox@gmail.com" required>
                </div>
                <button class="submit-btn" type="submit">Kirim Link Reset</button>
            </form>

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