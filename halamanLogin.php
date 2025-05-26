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
                <img src="https://lh3.googleusercontent.com/d/1q0PjKVPzmVWdLiJLpwfJ1rtl-ttyvtDp=w1000" alt="logo" width="40" height="40">
                <h1 class="title">KindBox</h1>
            </div>

            <div class="image-section mobile-only">
                <img src="https://lh3.googleusercontent.com/d/14ER28wan2OcQfEnCixA0hP78jr5qFdO1=w1000" alt="Illustration" width="400" height="400">
            </div>

            <p class="welcome-text">SELAMAT DATANG KEMBALI👋🏻</p>
            <h2 class="subtitle">Lanjutkan masuk ke akunmu!</h2>

            <button class="google-login">
                <img src="https://cdn.pixabay.com/photo/2017/01/19/09/11/logo-google-1991840_640.png" alt="Google Logo" class="google-icon">
                Masuk dengan Google
            </button>

            <p class="or-text">---atau---</p>

            <?php
            session_start(); // Start session to access session variables
            if (isset($_SESSION['login_error']) && !empty($_SESSION['login_error'])) {
                echo '<div class="error-message">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
                unset($_SESSION['login_error']); // Clear the error message after displaying it
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
                <button class="submit-btn" type="submit">MASUK</button>
            </form>

            <p class="member-text">
                Belum memiliki akun?
                <a class="signup-link" href="halamanRegister.php">DAFTAR</a>
            </p>
        </div>

        <div class="image-section desktop-only">
            <img src="https://lh3.googleusercontent.com/d/14ER28wan2OcQfEnCixA0hP78jr5qFdO1=w1000" alt="Illustration" width="400" height="400">
        </div>
    </div>
</body>
</html>