<?php
session_start();
require 'db.php'; // Koneksi ke database

header('Content-Type: application/json'); // Set header for JSON response

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $donor_id = intval($_POST['donor_id'] ?? 0);
    $recipient_id = intval($_POST['recipient_id'] ?? 0); // This should be $_SESSION['user_id']
    $rating = intval($_POST['rating'] ?? 0);

    // Basic validation
    if ($item_id === 0 || $donor_id === 0 || $recipient_id !== $_SESSION['user_id'] || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
        exit();
    }

    try {
        // Check if recipient has already rated this item
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM ratings WHERE recipient_id = :recipient_id AND item_id = :item_id");
        $stmt_check->bindParam(':recipient_id', $recipient_id);
        $stmt_check->bindParam(':item_id', $item_id);
        $stmt_check->execute();
        if ($stmt_check->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Anda sudah memberikan rating untuk barang ini.']);
            exit();
        }

        // Insert the rating
        $stmt_insert = $conn->prepare("INSERT INTO ratings (donor_id, recipient_id, item_id, rating) VALUES (:donor_id, :recipient_id, :item_id, :rating)");
        $stmt_insert->bindParam(':donor_id', $donor_id);
        $stmt_insert->bindParam(':recipient_id', $recipient_id);
        $stmt_insert->bindParam(':item_id', $item_id);
        $stmt_insert->bindParam(':rating', $rating);

        if ($stmt_insert->execute()) {
            echo json_encode(['success' => true, 'message' => 'Rating berhasil disimpan.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan rating.']);
        }

    } catch (PDOException $e) {
        error_log("Rating submission error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>