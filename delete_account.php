<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: halamanLogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Only process deletion if confirmed via POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete'])) {
    try {
        // Start a transaction for safety
        $conn->beginTransaction();

        // Optional: Delete related data (e.g., donations made by this user)
        // If ON DELETE CASCADE is set on foreign key in 'donations' table, this step is not strictly needed
        // $stmt = $conn->prepare("DELETE FROM donations WHERE user_id = :user_id");
        // $stmt->bindParam(':user_id', $user_id);
        // $stmt->execute();

        // Delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();

        $conn->commit();

        // Destroy session and redirect to login page after successful deletion
        session_destroy();
        setcookie("kindbox_user", "", time() - 3600, "/"); // Also clear any cookies
        header("Location: halamanLogin.php?status=deleted");
        exit();

    } catch (PDOException $e) {
        $conn->rollBack(); // Rollback transaction on error
        $message = "Gagal menghapus akun: " . $e->getMessage();
        error_log("Error deleting account: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>
   KindBox - Hapus Akun
  </title>
  <script src="https://cdn.tailwindcss.com">
  </script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&amp;display=swap" rel="stylesheet"/>
  <style>
   body {
      font-family: "Poppins", sans-serif;
    }
    .error-message {
        background: #fee2e2;
        color: #b91c1c;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
        font-weight: bold;
    }
  </style>
 </head>
 <body class="bg-[#E4F0D6] min-h-screen flex items-center justify-center">
  <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-md text-center">
   <h2 class="text-2xl font-bold mb-6 text-black">Hapus Akun</h2>

   <?php if (!empty($message)) : ?>
    <div class="error-message"><?= htmlspecialchars($message) ?></div>
   <?php else : ?>
    <p class="text-gray-700 mb-6">Anda yakin ingin menghapus akun Anda? Tindakan ini tidak dapat dibatalkan.</p>
    <form action="delete_account.php" method="post">
     <button type="submit" name="confirm_delete" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 font-semibold mb-4">Ya, Hapus Akun Saya</button>
     <a href="halamanProfil.php" class="block text-center text-sm text-[#6B8569] hover:underline">Batal</a>
    </form>
   <?php endif; ?>
  </div>
 </body>
</html>