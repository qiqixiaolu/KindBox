<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: halamanLogin.html");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Beranda KindBox</title>
</head>
<body>
    <h2>Halo, <?php echo htmlspecialchars($_SESSION["username"]); ?> 👋🏻</h2>
    <p>Selamat datang di KindBox!</p>
    <a href="logout.php">Logout</a>
</body>
</html>