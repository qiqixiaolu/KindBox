<?php
session_start();
session_destroy();
setcookie("kindbox_user", "", time() - 3600, "/");
header("Location: halamanLogin.html");
exit();
?>
