<?php
error_reporting(E_ALL ^ E_DEPRECATED);
session_start();
require 'db.php'; // Koneksi ke database

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: halamanLogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_full_name = '';
$user_location = '';
// Initialize profile_picture_url with a default to avoid errors if not fetched
$profile_picture_url = 'https://storage.googleapis.com/a1aa/image/bd3a933d-2733-4863-bea1-4a32e05e398e.jpg'; // Default avatar

try {
    // Fetch logged-in user's full name, location, and profile picture URL
    $stmt_user = $conn->prepare("SELECT full_name, location, profile_picture_url FROM users WHERE id = :user_id");
    $stmt_user->bindParam(':user_id', $user_id);
    $stmt_user->execute();
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($user_info) {
        $user_full_name = htmlspecialchars($user_info['full_name']);
        $user_location = htmlspecialchars($user_info['location']);
        // Use the fetched profile picture URL, or the default if it's not available
        $profile_picture_url = htmlspecialchars($user_info['profile_picture_url'] ?? $profile_picture_url);

        // Extract the city from the user's location (e.g., "Klojen, Malang, Jawa Timur" -> "Klojen")
        // This is a simple assumption that the first part before ',' is the city.
        $location_parts = explode(',', $user_location);
        $user_city = trim($location_parts[0]);

    } else {
        // If user data not found, something is wrong, redirect to logout
        session_destroy();
        header("Location: halamanLogin.php");
        exit();
    }

    // --- Fetch Recommendations ---
    $recommendations = [];

    // SQL for recommendations: exclude own items, available status, and order by proximity
    // ADDED 'id' to the SELECT statement
    $stmt_recs = $conn->prepare("
        SELECT 
            id, item_name, item_description, item_image_url, status, 
            donor_username, donor_location, item_count
        FROM 
            donations 
        WHERE 
            donor_id != :user_id AND status = 'Available'
        ORDER BY
            CASE 
                WHEN donor_location = :user_location THEN 0
                WHEN donor_location LIKE CONCAT(:user_city, '%') THEN 1
                ELSE 2
            END,
            donor_location ASC,
            created_at DESC
    ");
    $stmt_recs->bindParam(':user_id', $user_id);
    $stmt_recs->bindParam(':user_location', $user_location);
    $stmt_recs->bindParam(':user_city', $user_city); // Used for LIKE condition
    $stmt_recs->execute();
    $recommendations = $stmt_recs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>KindBox Home</title>
    <link rel="stylesheet" href="beranda.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet" />
</head>
<body>

    </div><header id="mobile-home-header">
        <p class="greeting">Hi, <?= $user_full_name ?>!</p>
        <form class="search-form">
            <input
                type="text"
                placeholder="Cari di KindBox"
                class="search-input-mobile"
            />
            <button
                type="button"
                aria-label="Filter"
                class="filter-button-mobile"
            >
                <i class="fas fa-sliders-h"></i>
            </button>
        </form>
        <p class="location-mobile">
            <i class="fas fa-map-marker-alt"></i>
            Berada di <strong class="location-text"><?= $user_location ?></strong>
        </p>
    </header>

    <header id="desktop-header">
        <div class="logo-container">
            <img
                src="Logo.png" alt="KindBox Logo"
                class="logo-img"
            />
            <span class="logo-text">KindBox</span>
        </div>
        <form class="search-form-desktop">
            <input
                type="text"
                placeholder="Cari di KindBox"
                class="search-input-desktop"
            />
            <button
                type="button"
                aria-label="Filter"
                class="filter-button-desktop"
            >
                <i class="fas fa-sliders-h"></i>
            </button>
        </form>
        <nav class="desktop-nav">
            <a href="halamanBeranda.php" class="nav-link">Home</a>
            <a href="halamanTambah.php" class="nav-link">Upload Barang</a>
            <button
                aria-label="User profile"
                class="profile-button"
                onclick="location.href='halamanProfil.php'"
            >
                <img src="<?= htmlspecialchars($profile_picture_url) ?>" alt="Profil Pengguna" class="profile-pic-header">
            </button>
        </nav>
    </header>

    <main class="main-content">

        <section class="warning-section">
            <div class="warning-content">
                <p class="warning-title">
                    <span>📢</span>
                    <span>Perhatian!</span>
                    <span>📢</span>
                </p>
                <p class="warning-text">
                    <strong>Platform ini bukan tempat untuk jual beli.</strong> Harap gunakan sesuai tujuan komunitas.
                    <strong class="danger-text">Dilarang melakukan transaksi dalam platform ini.</strong> Jika menemukan pelanggaran,
                    <strong>segera laporkan kepada kami.</strong><br />
                    <span class="wise-safe-text">Tetap bijak dan aman dalam berinteraksi!</span>
                </p>
            </div>
        </section>

        <p class="location-desktop">
            <i class="fas fa-map-marker-alt"></i>
            Berada di <strong class="location-text"><?= $user_location ?></strong>
        </p>

        <section class="recommendations-section">
            <h2 class="recommendations-title">Rekomendasi Untukmu</h2>
            <div class="recommendations-grid">
                <?php if (empty($recommendations)): ?>
                    <p style="grid-column: 1 / -1; text-align: center; color: #555;">Belum ada rekomendasi barang yang tersedia di sekitar Anda.</p>
                <?php else: ?>
                    <?php foreach ($recommendations as $item): ?>
                        <article class="card">
                            <img
                                src="<?= htmlspecialchars($item['item_image_url']) ?>"
                                alt="<?= htmlspecialchars($item['item_name']) ?>"
                                class="card-img"
                                width="300"
                                height="150"
                            />
                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($item['item_name']) ?></h3>
                                <p class="card-info">
                                    <i class="fas fa-heart"></i> Sisa <?= htmlspecialchars($item['item_count']) ?> Barang
                                </p>
                                <p class="card-location">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item['donor_location']) ?>
                                </p>
                            </div>
                            <a href="detailBarang.php?item_id=<?= htmlspecialchars($item['id']) ?>" class="card-button">
                                Lihat Detail
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer id="mobile-footer">
        <button
            aria-label="Home"
            class="footer-button"
            type="button"
            onclick="location.href='halamanBeranda.php'"
        >
            <i class="fas fa-home"></i>
            <span>Home</span>
        </button>
        <button
            aria-label="Upload Barang"
            class="footer-button"
            type="button"
            onclick="location.href='halamanTambah.php'"
        >
            <i class="fas fa-plus-circle"></i>
            <span>Upload</span>
        </button>
        <button
            aria-label="Profile"
            class="footer-button"
            type="button"
            onclick="location.href='halamanProfil.php'"
        >
            <i class="fas fa-user-circle"></i>
            <span>Profil</span>
        </button>
    </footer>
</body>
</html>