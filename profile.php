<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: halamanLogin.html");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head><title>Profil</title></head>
<body>
    <h2>Halo, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
    <p>Username: <?php echo $_SESSION['username']; ?></p>
    <a href="logout.php">Logout</a>
</body>
</html>