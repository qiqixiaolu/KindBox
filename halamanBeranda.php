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
$profile_picture_url = 'https://storage.googleapis.com/a1aa/image/bd3a933d-2733-4863-bea1-4a32e05e398e.jpg'; // Default avatar

// Search and filter parameters
$search_query = $_GET['search'] ?? '';
$filter_location = $_GET['location_filter'] ?? '';
$filter_category = $_GET['category_filter'] ?? '';
$filter_condition = $_GET['condition_filter'] ?? ''; // New: Condition filter
$filter_status = $_GET['status_filter'] ?? '';       // New: Status filter

try {
    // Fetch logged-in user's full name, location, and profile picture URL
    $stmt_user = $conn->prepare("SELECT full_name, location, profile_picture_url FROM users WHERE id = :user_id");
    $stmt_user->bindParam(':user_id', $user_id);
    $stmt_user->execute();
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($user_info) {
        $user_full_name = htmlspecialchars($user_info['full_name']);
        $user_location = htmlspecialchars($user_info['location']);
        $profile_picture_url = htmlspecialchars($user_info['profile_picture_url'] ?? $profile_picture_url);

        $location_parts = explode(',', $user_location);
        $user_city = trim($location_parts[0]);
    } else {
        session_destroy();
        header("Location: halamanLogin.php");
        exit();
    }

    // --- Fetch Recommendations ---
    $recommendations = [];

    // Base SQL query
    $sql_recs = "
        SELECT
            id, item_name, item_description, item_image_url, status, item_condition,
            donor_username, donor_location, item_count
        FROM
            donations
        WHERE
            donor_id != :user_id
    ";

    $params_recs = [':user_id' => $user_id];

    // Add search query condition
    if (!empty($search_query)) {
        $sql_recs .= " AND (item_name LIKE :search_query OR item_description LIKE :search_query)";
        $params_recs[':search_query'] = '%' . $search_query . '%';
    }

    // Add location filter condition
    if (!empty($filter_location)) {
        $sql_recs .= " AND donor_location LIKE :filter_location";
        $params_recs[':filter_location'] = '%' . $filter_location . '%';
    }

    // Add category filter condition
    if (!empty($filter_category)) {
        $sql_recs .= " AND category = :filter_category";
        $params_recs[':filter_category'] = $filter_category;
    }

    // New: Add condition filter
    if (!empty($filter_condition)) {
        $sql_recs .= " AND item_condition = :filter_condition";
        $params_recs[':filter_condition'] = $filter_condition;
    }

    // New: Add status filter (only 'Available' or 'Sold')
    if (!empty($filter_status)) {
        if ($filter_status == 'Sold') {
            $sql_recs .= " AND item_count = 0";
        } else if ($filter_status == 'Available') {
            $sql_recs .= " AND item_count > 0 AND status = 'Available'";
        }
    } else {
        // Default to showing only 'Available' items if no status filter is set
        // This ensures the homepage shows only available items by default, not sold ones
        $sql_recs .= " AND item_count > 0 AND status = 'Available'";
    }

    // Order by proximity first, then by creation date
    $sql_recs .= "
        ORDER BY
            CASE
                WHEN donor_location = :user_location THEN 0
                WHEN donor_location LIKE CONCAT(:user_city, '%') THEN 1
                ELSE 2
            END,
            created_at DESC
    ";
    $params_recs[':user_location'] = $user_location;
    $params_recs[':user_city'] = $user_city; // Remove '%' here, it's already in CONCAT above


    $stmt_recs = $conn->prepare($sql_recs);
    foreach ($params_recs as $key => &$val) {
        $stmt_recs->bindParam($key, $val);
    }
    $stmt_recs->execute();
    $recommendations = $stmt_recs->fetchAll(PDO::FETCH_ASSOC);

    // Fetch distinct categories for the filter dropdown
    $stmt_categories = $conn->prepare("SELECT DISTINCT category FROM donations ORDER BY category ASC");
    $stmt_categories->execute();
    $categories = $stmt_categories->fetchAll(PDO::FETCH_COLUMN);

    // Fetch distinct conditions for the filter dropdown
    $stmt_conditions = $conn->prepare("SELECT DISTINCT item_condition FROM donations ORDER BY item_condition ASC");
    $stmt_conditions->execute();
    $conditions = $stmt_conditions->fetchAll(PDO::FETCH_COLUMN);

    // Define possible statuses for filter (only Available and Sold)
    $statuses = ['Available', 'Sold'];

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
    <style>
        /* Styles for the filter modal */
        .filter-modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 100; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }
        .filter-modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            position: relative;
        }
        .filter-close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
        }
        .filter-close-button:hover,
        .filter-close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .filter-modal select, .filter-modal input[type="text"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .filter-modal button {
            background-color: #6B8E6B;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            width: 100%;
        }
    </style>
</head>
<body>

    <header id="mobile-home-header">
        <p class="greeting">Hi, <?= $user_full_name ?>!</p>
        <form class="search-form" action="halamanBeranda.php" method="GET">
            <input
                type="text"
                placeholder="Cari di KindBox"
                class="search-input-mobile"
                name="search"
                value="<?= htmlspecialchars($search_query) ?>"
            />
            <button
                type="button"
                aria-label="Filter"
                class="filter-button-mobile"
                onclick="openFilterModal()"
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
        <form class="search-form-desktop" action="halamanBeranda.php" method="GET">
            <input
                type="text"
                placeholder="Cari di KindBox"
                class="search-input-desktop"
                name="search"
                value="<?= htmlspecialchars($search_query) ?>"
            />
            <button
                type="button"
                aria-label="Filter"
                class="filter-button-desktop"
                onclick="openFilterModal()"
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
                    <p style="grid-column: 1 / -1; text-align: center; color: #555;">Tidak ada barang yang tersedia untuk rekomendasi Anda. Coba ubah filter atau lokasi.</p>
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

    <div id="filterModal" class="filter-modal">
        <div class="filter-modal-content">
            <span class="filter-close-button" onclick="closeFilterModal()">&times;</span>
            <h3 class="text-xl font-semibold text-[#2F4F2F] mb-4">Filter Barang</h3>
            <form action="halamanBeranda.php" method="GET">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">

                <label for="location_filter" class="block text-gray-700 text-sm font-bold mb-2">Lokasi:</label>
                <input type="text" id="location_filter" name="location_filter" value="<?= htmlspecialchars($filter_location) ?>" placeholder="Filter Lokasi">

                <label for="category_filter" class="block text-gray-700 text-sm font-bold mb-2">Kategori:</label>
                <select id="category_filter" name="category_filter">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $category_option): ?>
                        <option value="<?= htmlspecialchars($category_option) ?>" <?= ($filter_category === $category_option) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category_option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="condition_filter" class="block text-gray-700 text-sm font-bold mb-2">Kondisi:</label>
                <select id="condition_filter" name="condition_filter">
                    <option value="">Semua Kondisi</option>
                    <?php foreach ($conditions as $condition_option): ?>
                        <option value="<?= htmlspecialchars($condition_option) ?>" <?= ($filter_condition === $condition_option) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($condition_option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="status_filter" class="block text-gray-700 text-sm font-bold mb-2">Status:</label>
                <select id="status_filter" name="status_filter">
                    <option value="">Semua Status</option>
                    <?php foreach ($statuses as $status_option): ?>
                        <option value="<?= htmlspecialchars($status_option) ?>" <?= ($filter_status === $status_option) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($status_option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Terapkan Filter</button>
                <button type="button" onclick="clearFilters()" style="background-color: #dc3545; margin-top: 10px;">Hapus Filter</button>
            </form>
        </div>
    </div>

    <script>
        // Get the filter modal element
        var filterModal = document.getElementById("filterModal");

        // Function to open the filter modal
        function openFilterModal() {
            filterModal.style.display = "flex";
        }

        // Function to close the filter modal
        function closeFilterModal() {
            filterModal.style.display = "none";
        }

        // Close the modal if the user clicks anywhere outside of it
        window.onclick = function(event) {
            if (event.target == filterModal) {
                filterModal.style.display = "none";
            }
        }

        // Function to clear all filter fields and submit the form
        function clearFilters() {
            document.getElementById('location_filter').value = '';
            document.getElementById('category_filter').value = '';
            document.getElementById('condition_filter').value = '';
            document.getElementById('status_filter').value = '';
            document.querySelector('.filter-modal-content form').submit();
        }
    </script>
</body>
</html>