<?php
session_start();
require 'db.php'; // Koneksi ke database

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: halamanLogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_data = null;
$giving_history = [];
$receiving_history = [];
$donations_given_count = 0;
$current_level = 1;
$average_donor_rating = 0; // New variable for average rating

// Search and filter parameters for giving history
$search_giving = $_GET['search_giving'] ?? '';
$filter_giving_status = $_GET['filter_giving_status'] ?? ''; // New filter for giving history

// Search and filter parameters for receiving history
$search_receiving = $_GET['search_receiving'] ?? '';
$filter_receiving_status = $_GET['filter_receiving_status'] ?? ''; // New filter for receiving history

try {
    // Fetch user data
    $stmt = $conn->prepare("SELECT full_name, username, email, location, profile_picture_url FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        session_destroy();
        header("Location: halamanLogin.php");
        exit();
    }

    // Fetch giving history for the logged-in user
    // Modified SQL to include item_status which will represent 'Available' or 'Sold'
    $sql_giving = "
        SELECT d.id, d.item_name, d.recipient_username, d.recipient_location, d.item_image_url, d.donor_location,
               CASE WHEN d.status = 'Received' THEN 'Sold' ELSE 'Available' END AS item_status, d.status AS actual_status
        FROM donations d
        WHERE d.donor_id = :user_id
    ";
    $params_giving = [':user_id' => $user_id];

    if (!empty($search_giving)) {
        $sql_giving .= " AND (d.item_name LIKE :search_giving OR d.recipient_username LIKE :search_giving)";
        $params_giving[':search_giving'] = '%' . $search_giving . '%';
    }
    
    // Adjusting filter logic for 'Available' and 'Sold'
    if (!empty($filter_giving_status)) {
        if ($filter_giving_status == 'Sold') {
            $sql_giving .= " AND d.status = 'Received'";
        } elseif ($filter_giving_status == 'Available') {
            $sql_giving .= " AND d.status != 'Received'";
        }
    }

    $sql_giving .= " ORDER BY created_at DESC";

    $stmt_giving = $conn->prepare($sql_giving);
    foreach ($params_giving as $key => &$val) {
        $stmt_giving->bindParam($key, $val);
    }
    $stmt_giving->execute();
    $giving_history = $stmt_giving->fetchAll(PDO::FETCH_ASSOC);

    // Get the count of donations given for level calculation
    // CHANGED: Counting all donations, not just 'Received' ones for level calculation
    $stmt_donations_count = $conn->prepare("SELECT COUNT(*) AS total_donations FROM donations WHERE donor_id = :user_id");
    $stmt_donations_count->bindParam(':user_id', $user_id);
    $stmt_donations_count->execute();
    $donations_given_count_result = $stmt_donations_count->fetch(PDO::FETCH_ASSOC);
    $donations_given_count = $donations_given_count_result['total_donations'];

    // Calculate level based on total donations (every 5 items)
    $current_level = floor($donations_given_count / 5) + 1; // Start from Level 1, add 1 for every 5 donations

    // Calculate average rating for the donor (current user as donor)
    $stmt_avg_rating = $conn->prepare("SELECT AVG(rating) AS avg_rating FROM ratings WHERE donor_id = :user_id");
    $stmt_avg_rating->bindParam(':user_id', $user_id);
    $stmt_avg_rating->execute();
    $avg_rating_result = $stmt_avg_rating->fetch(PDO::FETCH_ASSOC);
    // Use coalesce to ensure 0 if no ratings, and round to one decimal place for display
    $average_donor_rating = round($avg_rating_result['avg_rating'] ?? 0, 1); 

    // Fetch receiving history for the logged-in user
    $sql_receiving = "
        SELECT
            d.id AS item_id,
            d.item_name,
            d.donor_id,
            d.donor_username,
            d.donor_location,
            i.status AS interest_status,
            d.item_image_url,
            (SELECT COUNT(*) FROM ratings r WHERE r.recipient_id = :current_user_id AND r.item_id = d.id) AS has_rated
        FROM interests i
        JOIN donations d ON i.item_id = d.id
        WHERE i.user_id = :user_id
    ";
    $params_receiving = [':user_id' => $user_id, ':current_user_id' => $user_id]; // Add current_user_id for subquery

    if (!empty($search_receiving)) {
        $sql_receiving .= " AND (d.item_name LIKE :search_receiving OR d.donor_username LIKE :search_receiving)";
        $params_receiving[':search_receiving'] = '%' . $search_receiving . '%';
    }

    if (!empty($filter_receiving_status)) {
        $sql_receiving .= " AND i.status = :filter_receiving_status";
        $params_receiving[':filter_receiving_status'] = $filter_receiving_status;
    }
    $sql_receiving .= " ORDER BY i.created_at DESC";

    $stmt_receiving = $conn->prepare($sql_receiving);
    foreach ($params_receiving as $key => &$val) {
        $stmt_receiving->bindParam($key, $val);
    }
    $stmt_receiving->execute();
    $receiving_history = $stmt_receiving->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error retrieving user data or history: " . $e->getMessage());
}

// Set default if data not in DB
$full_name = htmlspecialchars($user_data['full_name'] ?? 'Nama Pengguna');
$username = htmlspecialchars($user_data['username'] ?? '@username');
$email = htmlspecialchars($user_data['email'] ?? 'email@example.com');
$location = htmlspecialchars($user_data['location'] ?? 'Lokasi Tidak Diketahui');
$profile_picture_url = htmlspecialchars($user_data['profile_picture_url'] ?? 'https://storage.googleapis.com/a1aa/image/bd3a933d-2733-4863-bea1-4a32e05e398e.jpg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>KindBox - Profil</title>
    <link rel="stylesheet" href="beranda.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            height: 100vh;
        }
        @media (min-width: 768px) {
            .desktop-only {
                display: flex !important;
                overflow: hidden;
            }
            .mobile-only {
                display: none !important;
            }
        }
        @media (max-width: 767px) {
            .desktop-only {
                display: none !important;
            }
            .mobile-only {
                display: block !important;
            }
        }
        /* Custom styles for desktop layout */
        .desktop-layout {
            display: flex;
            height: 100vh;
        }
        .profile-sidebar {
            width: 288px; /* w-72 equivalent */
            flex-shrink: 0;
            /* Using h-full and overflow-y-auto to allow internal scroll if content exceeds sidebar height */
            height: 100vh; /* Set explicit height to make sticky/scroll work within viewport */
            overflow-y: auto; /* Allow sidebar to scroll internally if its content is too long */
            position: sticky; /* Make it sticky */
            top: 0; /* Stick to the top */
            align-self: flex-start; /* Align to the top within flex container */
        }
        .content-main {
            flex-grow: 1;
            height: 100vh; /* Set explicit height to allow internal scroll */
            overflow-y: auto; /* This is the scrollable main content area */
        }
        /* Mobile footer styles */
        #mobile-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #6b856d;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            z-index: 100;
        }
        .footer-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
            font-size: 10px;
            background: none;
            border: none;
        }
        .footer-button i {
            font-size: 20px;
            margin-bottom: 4px;
        }
        .footer-button span {
            font-size: 10px;
        }
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
        .clear-filter-button {
            background-color: #dc3545; /* Bootstrap's danger red */
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }
        .clear-filter-button:hover {
            background-color: #c82333;
        }
        /* Rating stars */
        .rating-stars .fa-star {
            cursor: pointer;
            color: #ddd; /* Default gray */
        }
        .rating-stars .fa-star.active {
            color: #ffc107; /* Gold for active stars */
        }
    </style>
</head>
<body class="bg-white text-[#1f3a2f]">
    <div class="mobile-only">
        <header class="bg-[#6b856d] py-3 text-center">
            <h1 class="text-white font-bold text-lg">Profil</h1>
        </header>
        <main class="flex-grow px-4 py-4 max-w-md mx-auto w-full pb-16">
            <section class="flex items-center space-x-4 mb-6">
                <img alt="User profile picture" class="w-16 h-16 rounded-full object-cover" src="<?= $profile_picture_url ?>"/>
                <div class="flex-1">
                    <h2 class="font-extrabold text-[#1f3a2f] text-lg leading-tight"><?= $full_name ?></h2>
                    <p class="text-xs text-[#4a5a44] leading-tight"><?= $username ?></p>
                    <p class="text-xs text-[#4a5a44] leading-tight"><?= $email ?></p>
                    <p class="text-xs text-[#4a5a44] leading-tight"><?= $location ?></p>
                </div>
                <button aria-label="Edit profile" class="text-[#4a5a44] hover:text-[#2f4a2f]" onclick="location.href='edit_profile.php'">
                    <i class="fas fa-pen"></i>
                </button>
            </section>
            
            <section class="mb-6">
                <h3 class="font-extrabold text-[#1f3a2f] mb-2 text-sm">Level Kebaikan</h3>
                <div class="bg-[#a6b79e] rounded-lg p-3 flex space-x-3 items-center">
                    <div aria-label="Level <?= $current_level ?>" class="bg-[#d7e1cc] rounded-lg flex items-center justify-center w-16 h-16 font-extrabold text-4xl text-[#3a4a2f]">
                        <?= $current_level ?>
                    </div>
                    <div class="text-xs text-[#2f3a2f] leading-tight">
                        Kamu telah memberikan barang sebanyak
                        <span class="font-bold"><?= $donations_given_count ?></span>
                        kali. Level kebaikan kamu adalah
                        <span class="font-bold">Level <?= $current_level ?>.</span>
                        <br/>
                        <span class="font-bold">Rating pemberianmu:</span>
                        <span class="inline-block text-yellow-400 ml-1">
                            <?php
                            $max_stars = 5;
                            // Round average_donor_rating to nearest integer for displaying filled stars
                            $filled_stars = round($average_donor_rating); 
                            for ($i = 1; $i <= $max_stars; $i++) {
                                if ($i <= $filled_stars) { 
                                    echo '<i class="fas fa-star"></i>';
                                } else {
                                    echo '<i class="fas fa-star text-gray-300"></i>';
                                }
                            }
                            ?>
                        </span>
                        <span class="font-bold"> (<?= number_format($average_donor_rating, 1) ?>/5)</span>
                    </div>
                </div>
            </section>
            
            <section class="mb-6">
                <h3 class="font-extrabold text-[#1f3a2f] mb-2 text-sm">Riwayat Donasi</h3>
                <form class="flex items-center space-x-2 mb-3" action="halamanProfil.php" method="GET">
                    <label class="sr-only" for="search-giving">Cari di Riwayat Donasi</label>
                    <div class="flex items-center flex-grow border border-[#4a5a44] rounded-lg px-3 py-1 text-[#4a5a44] text-sm">
                        <i class="fas fa-search mr-2"></i>
                        <input class="bg-transparent focus:outline-none w-full" id="search-giving" name="search_giving" placeholder="Cari di Riwayat Donasi" type="search" value="<?= htmlspecialchars($search_giving) ?>"/>
                        <input type="hidden" name="filter_giving_status" value="<?= htmlspecialchars($filter_giving_status) ?>">
                    </div>
                    <button type="button" aria-label="Filter" class="bg-[#DCE9C9] rounded-md p-2 text-black text-xs flex items-center justify-center" onclick="openFilterGivingModal()">
                        <i class="fas fa-sliders-h"></i>
                    </button>
                </form>
                <div class="grid grid-cols-2 gap-3 overflow-y-auto max-h-96">
                    <?php if (empty($giving_history)): ?>
                        <p class="col-span-full text-center text-gray-500 text-xs">Belum ada riwayat donasi.</p>
                    <?php else: ?>
                        <?php foreach ($giving_history as $item): ?>
                        <article class="bg-[#c3d0b3] rounded-lg overflow-hidden">
                            <img alt="<?= htmlspecialchars($item['item_name']) ?>" class="w-full h-24 object-cover rounded-t-lg" src="<?= htmlspecialchars($item['item_image_url']) ?>"/>
                            <div class="p-2 text-xs text-[#2f4a2f]">
                                <h4 class="font-extrabold text-sm mb-1"><?= htmlspecialchars($item['item_name']) ?></h4>
                                <p class="flex items-center gap-1 mb-0.5">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($item['recipient_username'] ? '@' . $item['recipient_username'] : 'Penerima Tidak Diketahui') ?>
                                </p>
                                <p class="flex items-center gap-1 mb-0.5">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($item['donor_location'] ?? 'Lokasi Tidak Diketahui') ?>
                                </p>
                                <p class="flex items-center gap-1 mb-0.5">
                                    <i class="fas fa-info-circle"></i>
                                    <?= htmlspecialchars($item['item_status']) ?>
                                </p>
                                <a href="detailBarang.php?item_id=<?= htmlspecialchars($item['id']) ?>" class="mt-2 w-full bg-[#6b856d] text-white text-xs rounded-md py-1 text-center block">
                                    Lihat Detail
                                </a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
            
            <section class="mb-6">
                <h3 class="font-extrabold text-[#1f3a2f] mb-2 text-sm">Riwayat Minat</h3>
                <form class="flex items-center space-x-2 mb-3" action="halamanProfil.php" method="GET">
                    <label class="sr-only" for="search-receiving">Cari di Riwayat Minat</label>
                    <div class="flex items-center flex-grow border border-[#4a5a44] rounded-lg px-3 py-1 text-[#4a5a44] text-sm">
                        <i class="fas fa-search mr-2"></i>
                        <input class="bg-transparent focus:outline-none w-full" id="search-receiving" name="search_receiving" placeholder="Cari di Riwayat Minat" type="search" value="<?= htmlspecialchars($search_receiving) ?>"/>
                        <input type="hidden" name="filter_receiving_status" value="<?= htmlspecialchars($filter_receiving_status) ?>">
                    </div>
                    <button type="button" aria-label="Filter" class="bg-[#DCE9C9] rounded-md p-2 text-black text-xs flex items-center justify-center" onclick="openFilterReceivingModal()">
                        <i class="fas fa-sliders-h"></i>
                    </button>
                </form>
                <div class="grid grid-cols-2 gap-3 overflow-y-auto max-h-96"> 
                    <?php if (empty($receiving_history)): ?>
                        <p class="col-span-full text-center text-gray-500 text-xs">Belum ada riwayat minat.</p>
                    <?php else: ?>
                        <?php foreach ($receiving_history as $item): ?>
                        <article class="bg-[#c3d0b3] rounded-lg overflow-hidden">
                            <img alt="<?= htmlspecialchars($item['item_name']) ?>" class="w-full h-24 object-cover rounded-t-lg" src="<?= htmlspecialchars($item['item_image_url']) ?>"/>
                            <div class="p-2 text-xs text-[#2f4a2f]">
                                <h4 class="font-extrabold text-sm mb-1"><?= htmlspecialchars($item['item_name']) ?></h4>
                                <p class="flex items-center gap-1 mb-0.5">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($item['donor_username'] ? '@' . $item['donor_username'] : 'Pemberi Tidak Diketahui') ?>
                                </p>
                                <p class="flex items-center gap-1 mb-0.5">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($item['donor_location'] ?? 'Lokasi Tidak Diketahui') ?>
                                </p>
                                <p class="flex items-center gap-1 mb-0.5">
                                    <i class="fas fa-info-circle"></i>
                                    Status: <?= htmlspecialchars($item['interest_status'] ?? 'N/A') ?>
                                </p>
                                <?php if ($item['interest_status'] === 'verified' && $item['has_rated'] == 0): ?>
                                <button
                                    type="button"
                                    class="mt-2 w-full bg-yellow-500 hover:bg-yellow-600 text-white text-xs rounded-md py-1 text-center block"
                                    onclick="openRatingModal(<?= $item['item_id'] ?>, <?= $item['donor_id'] ?>)"
                                >
                                    Beri Rating
                                </button>
                                <?php elseif ($item['interest_status'] === 'verified' && $item['has_rated'] > 0): ?>
                                <button
                                    type="button"
                                    class="mt-2 w-full bg-gray-400 text-white text-xs rounded-md py-1 text-center block cursor-not-allowed"
                                    disabled
                                >
                                    Sudah Dinilai
                                </button>
                                <?php endif; ?>
                                <a href="detailBarang.php?item_id=<?= htmlspecialchars($item['item_id']) ?>" class="mt-2 w-full bg-[#6b856d] text-white text-xs rounded-md py-1 text-center block">
                                    Lihat Detail
                                </a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
            
            <section class="mb-6 space-y-2">
                <button class="w-full bg-[#2f4a2f] text-white text-xs rounded-md py-2" type="button" onclick="location.href='logout.php'">
                    Keluar
                </button>
                <button class="w-full bg-[#2f4a2f] text-white text-xs rounded-md py-2" type="button" onclick="location.href='delete_account.php'">
                    Hapus Akun
                </button>
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
    </div>

    <div class="desktop-only desktop-layout">
        <header id="desktop-header" class="fixed top-0 left-0 right-0 z-50">
            <div class="logo-container">
                <img src="Logo.png" alt="KindBox Logo" class="logo-img" />
                <span class="logo-text">KindBox</span>
            </div>
            <nav class="desktop-nav">
                <a href="halamanBeranda.php" class="nav-link">Home</a>
                <a href="halamanTambah.php" class="nav-link">Upload Barang</a>
                <button aria-label="User profile" class="profile-button" onclick="location.href='halamanProfil.php'">
                    <img src="<?= htmlspecialchars($profile_picture_url) ?>" alt="Profil Pengguna" class="profile-pic-header">
                </button>
            </nav>
        </header>
        
        <aside class="profile-sidebar bg-[#DCE9C9] w-72 flex flex-col items-center py-8 px-6 space-y-6 select-none mt-14">
            <img alt="User profile picture" class="rounded-full w-36 h-36 object-cover" src="<?= $profile_picture_url ?>"/>
            <h2 class="font-extrabold text-black text-center text-sm"><?= $full_name ?></h2>
            <div class="w-full space-y-4 text-xs text-black">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-user"></i>
                    <span><?= $username ?></span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-envelope"></i>
                    <span><?= $email ?></span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?= $location ?></span>
                </div>
            </div>
            <button class="bg-[#A3B3A7] text-black font-semibold text-xs rounded-md py-2 w-full" type="button" onclick="location.href='edit_profile.php'">
                Edit Profil
            </button>
            <button class="bg-[#A3B3A7] text-black font-semibold text-xs rounded-md py-2 w-full" type="button" onclick="location.href='delete_account.php'">
                Hapus Akun
            </button>
            <button class="bg-[#A3B3A7] text-black font-semibold text-xs rounded-md py-2 w-full" type="button" onclick="location.href='logout.php'">
                Keluar
            </button>
        </aside>
        
        <div class="content-main mt-16">
            <section class="p-6 md:p-10 space-y-6">
                <div class="bg-[#DCE9C9] rounded-md flex flex-col md:flex-row items-center md:items-start space-y-4 md:space-y-0 md:space-x-6 p-6">
                    <div class="bg-[#2F4A27] text-[#E4F0D6] font-extrabold text-5xl flex items-center justify-center rounded-md w-24 h-24 flex-shrink-0">
                        <?= $current_level ?>
                    </div>
                    <div class="text-black text-lg font-normal max-w-xxl">
                        Kamu telah memberikan barang sebanyak
                        <span class="font-extrabold"><?= $donations_given_count ?></span>
                        kali. Level Kebaikan kamu adalah
                        <span class="font-extrabold">Level <?= $current_level ?></span>.
                        <div class="mt-2 text-yellow-400 text-xl flex items-center space-x-1">
                            <?php
                            $max_stars = 5;
                            $filled_stars = round($average_donor_rating);
                            for ($i = 1; $i <= $max_stars; $i++) {
                                if ($i <= $filled_stars) {
                                    echo '<i class="fas fa-star"></i>';
                                } else {
                                    echo '<i class="fas fa-star text-gray-300"></i>';
                                }
                            }
                            ?>
                        </div>
                        <span class="font-bold"> (<?= number_format($average_donor_rating, 1) ?>/5)</span>
                    </div>
                </div>
                
                <h3 class="font-extrabold text-black text-lg">Riwayat Donasi</h3>
                <form class="flex items-center space-x-2 max-w-xl" action="halamanProfil.php" method="GET">
                    <div class="relative flex-1">
                        <input class="w-full rounded-md bg-[#DCE9C9] text-xs placeholder:text-[#A3B3A7] placeholder:text-xs py-2 pl-9 pr-3 focus:outline-none" placeholder="Cari di Riwayat Donasi" type="text" name="search_giving" value="<?= htmlspecialchars($search_giving) ?>"/>
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-[#A3B3A7] text-xs">
                            <i class="fas fa-search"></i>
                        </div>
                        <input type="hidden" name="filter_giving_status" value="<?= htmlspecialchars($filter_giving_status) ?>">
                    </div>
                    <button type="button" aria-label="Filter" class="bg-[#DCE9C9] rounded-md p-2 text-black text-xs flex items-center justify-center" onclick="openFilterGivingModal()">
                        <i class="fas fa-sliders-h"></i>
                    </button>
                </form>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <?php if (empty($giving_history)): ?>
                        <p class="col-span-full text-center text-gray-500">Belum ada riwayat donasi.</p>
                    <?php else: ?>
                        <?php foreach ($giving_history as $item): ?>
                            <article class="bg-[#DCE9C9] rounded-md overflow-hidden">
                                <img alt="<?= htmlspecialchars($item['item_name']) ?>" class="w-full h-44 object-cover" src="<?= htmlspecialchars($item['item_image_url']) ?>"/>
                                <div class="bg-[#A3B3A7] p-3 text-black text-xs space-y-1">
                                    <h4 class="font-extrabold text-[11px]"><?= htmlspecialchars($item['item_name']) ?></h4>
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-user"></i>
                                        <span><?= htmlspecialchars($item['recipient_username'] ? '@' . $item['recipient_username'] : 'Penerima Tidak Diketahui') ?></span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?= htmlspecialchars($item['donor_location'] ?? 'Lokasi Tidak Diketahui') ?></span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-info-circle"></i>
                                        <span><?= htmlspecialchars($item['item_status']) ?></span>
                                    </div>
                                    <a href="detailBarang.php?item_id=<?= htmlspecialchars($item['id']) ?>" class="mt-2 w-full bg-[#6b856d] text-white text-xs rounded-md py-1 text-center block">
                                        Lihat Detail
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <article class="bg-[#F3F9ED] rounded-md flex flex-col items-center justify-center p-6 text-[#A3B3A7] text-xs font-semibold select-none">
                        <button aria-label="Donasikan Barang" class="mb-3 text-4xl border border-[#A3B3A7] rounded-full w-14 h-14 flex items-center justify-center" onclick="location.href='halamanTambah.php'">
                            <i class="fas fa-plus"></i>
                        </button>
                        <span>Donasikan Barang</span>
                    </article>
                </div>
                <h3 class="font-extrabold text-black text-lg mt-8">Riwayat Minat</h3>
                <form class="flex items-center space-x-2 max-w-xl" action="halamanProfil.php" method="GET">
                    <div class="relative flex-1">
                        <input class="w-full rounded-md bg-[#DCE9C9] text-xs placeholder:text-[#A3B3A7] placeholder:text-xs py-2 pl-9 pr-3 focus:outline-none" placeholder="Cari di Riwayat Minat" type="text" name="search_receiving" value="<?= htmlspecialchars($search_receiving) ?>"/>
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-[#A3B3A7] text-xs">
                            <i class="fas fa-search"></i>
                        </div>
                        <input type="hidden" name="filter_receiving_status" value="<?= htmlspecialchars($filter_receiving_status) ?>">
                    </div>
                    <button type="button" aria-label="Filter" class="bg-[#DCE9C9] rounded-md p-2 text-black text-xs flex items-center justify-center" onclick="openFilterReceivingModal()">
                        <i class="fas fa-sliders-h"></i>
                    </button>
                </form>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <?php if (empty($receiving_history)): ?>
                        <p class="col-span-full text-center text-gray-500">Belum ada riwayat minat.</p>
                    <?php else: ?>
                        <?php foreach ($receiving_history as $item): ?>
                            <article class="bg-[#DCE9C9] rounded-md overflow-hidden">
                                <img alt="<?= htmlspecialchars($item['item_name']) ?>" class="w-full h-44 object-cover" src="<?= htmlspecialchars($item['item_image_url']) ?>"/>
                                <div class="bg-[#A3B3A7] p-3 text-black text-xs space-y-1">
                                    <h4 class="font-extrabold text-[11px]"><?= htmlspecialchars($item['item_name']) ?></h4>
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-user"></i>
                                        <span><?= htmlspecialchars($item['donor_username'] ? '@' . $item['donor_username'] : 'Pemberi Tidak Diketahui') ?></span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?= htmlspecialchars($item['donor_location'] ?? 'Lokasi Tidak Diketahui') ?></span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Status: <?= htmlspecialchars($item['interest_status'] ?? 'N/A') ?></span>
                                    </div>
                                    <?php if ($item['interest_status'] === 'verified' && $item['has_rated'] == 0): ?>
                                    <button
                                        type="button"
                                        class="mt-2 w-full bg-yellow-500 hover:bg-yellow-600 text-white text-xs rounded-md py-1 text-center block"
                                        onclick="openRatingModal(<?= $item['item_id'] ?>, <?= $item['donor_id'] ?>)"
                                    >
                                        Beri Rating
                                    </button>
                                    <?php elseif ($item['interest_status'] === 'verified' && $item['has_rated'] > 0): ?>
                                    <button
                                        type="button"
                                        class="mt-2 w-full bg-gray-400 text-white text-xs rounded-md py-1 text-center block cursor-not-allowed"
                                        disabled
                                    >
                                        Sudah Dinilai
                                    </button>
                                    <?php endif; ?>
                                    <a href="detailBarang.php?item_id=<?= htmlspecialchars($item['item_id']) ?>" class="mt-2 w-full bg-[#6b856d] text-white text-xs rounded-md py-1 text-center block">
                                        Lihat Detail
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <div id="filterGivingModal" class="filter-modal">
        <div class="filter-modal-content">
            <span class="filter-close-button" onclick="closeFilterGivingModal()">&times;</span>
            <h3 class="text-xl font-semibold text-[#2F4F2F] mb-4">Filter Riwayat Donasi</h3>
            <form action="halamanProfil.php" method="GET" id="filterGivingForm">
                <input type="hidden" name="search_giving" value="<?= htmlspecialchars($search_giving) ?>">
                <label for="filter_giving_status_modal" class="block text-gray-700 text-sm font-bold mb-2">Status:</label>
                <select id="filter_giving_status_modal" name="filter_giving_status">
                    <option value="">Semua Status</option>
                    <option value="Available" <?= ($filter_giving_status === 'Available') ? 'selected' : '' ?>>Available</option>
                    <option value="Sold" <?= ($filter_giving_status === 'Sold') ? 'selected' : '' ?>>Sold</option>
                </select>
                <button type="submit">Terapkan Filter</button>
                <button type="button" class="clear-filter-button" onclick="clearFilterGiving()">Hapus Filter</button>
            </form>
        </div>
    </div>

    <div id="filterReceivingModal" class="filter-modal">
        <div class="filter-modal-content">
            <span class="filter-close-button" onclick="closeFilterReceivingModal()">&times;</span>
            <h3 class="text-xl font-semibold text-[#2F4F2F] mb-4">Filter Riwayat Minat</h3>
            <form action="halamanProfil.php" method="GET" id="filterReceivingForm">
                <input type="hidden" name="search_receiving" value="<?= htmlspecialchars($search_receiving) ?>">
                <label for="filter_receiving_status_modal" class="block text-gray-700 text-sm font-bold mb-2">Status:</label>
                <select id="filter_receiving_status_modal" name="filter_receiving_status">
                    <option value="">Semua Status</option>
                    <option value="pending" <?= ($filter_receiving_status === 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="verified" <?= ($filter_receiving_status === 'verified') ? 'selected' : '' ?>>Verified</option>
                    <option value="rejected" <?= ($filter_receiving_status === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                </select>
                <button type="submit">Terapkan Filter</button>
                <button type="button" class="clear-filter-button" onclick="clearFilterReceiving()">Hapus Filter</button>
            </form>
        </div>
    </div>

    <div id="ratingModal" class="filter-modal">
        <div class="filter-modal-content">
            <span class="filter-close-button" onclick="closeRatingModal()">&times;</span>
            <h3 class="text-xl font-semibold text-[#2F4F2F] mb-4">Beri Rating Pemberi</h3>
            <form id="ratingForm">
                <input type="hidden" id="ratingItemId" name="item_id">
                <input type="hidden" id="ratingDonorId" name="donor_id">
                <input type="hidden" id="ratingRecipientId" name="recipient_id" value="<?= $user_id ?>">
                
                <div class="flex justify-center mb-4">
                    <div class="rating-stars text-3xl">
                        <i class="fas fa-star" data-rating="1"></i>
                        <i class="fas fa-star" data-rating="2"></i>
                        <i class="fas fa-star" data-rating="3"></i>
                        <i class="fas fa-star" data-rating="4"></i>
                        <i class="fas fa-star" data-rating="5"></i>
                    </div>
                </div>
                <input type="hidden" id="selectedRating" name="rating" value="0">
                <p id="ratingMessage" class="text-center text-sm text-gray-600 mb-4"></p>
                <button type="submit">Kirim Rating</button>
            </form>
        </div>
    </div>

    <script>
        // Get the filter modal elements
        var filterGivingModal = document.getElementById("filterGivingModal"); // New filter modal for giving history
        var filterReceivingModal = document.getElementById("filterReceivingModal");
        var ratingModal = document.getElementById("ratingModal");
        var ratingStars = document.querySelectorAll('#ratingModal .rating-stars .fa-star');
        var selectedRatingInput = document.getElementById('selectedRating');
        var ratingMessage = document.getElementById('ratingMessage');
        var currentRating = 0; // To keep track of the selected rating

        // Function to open the filter modal for giving history
        function openFilterGivingModal() {
            filterGivingModal.style.display = "flex";
        }

        // Function to close the filter modal for giving history
        function closeFilterGivingModal() {
            filterGivingModal.style.display = "none";
        }

        // Function to open the filter modal for receiving history
        function openFilterReceivingModal() {
            filterReceivingModal.style.display = "flex";
        }

        // Function to close the filter modal for receiving history
        function closeFilterReceivingModal() {
            filterReceivingModal.style.display = "none";
        }

        // Functions for Rating Modal
        function openRatingModal(itemId, donorId) {
            document.getElementById('ratingItemId').value = itemId;
            document.getElementById('ratingDonorId').value = donorId;
            ratingModal.style.display = "flex";
            resetRatingStars(); // Reset stars when opening
        }

        function closeRatingModal() {
            ratingModal.style.display = "none";
        }

        function resetRatingStars() {
            currentRating = 0;
            selectedRatingInput.value = 0;
            ratingMessage.textContent = "Pilih 1-5 bintang";
            ratingStars.forEach(star => {
                star.classList.remove('active');
            });
        }

        ratingStars.forEach(star => {
            star.addEventListener('mouseover', function() {
                const ratingValue = parseInt(this.dataset.rating);
                ratingStars.forEach(s => {
                    if (parseInt(s.dataset.rating) <= ratingValue) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
                ratingMessage.textContent = `${ratingValue} Bintang`;
            });

            star.addEventListener('mouseout', function() {
                ratingStars.forEach(s => {
                    if (parseInt(s.dataset.rating) <= currentRating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
                if (currentRating === 0) {
                    ratingMessage.textContent = "Pilih 1-5 bintang";
                } else {
                    ratingMessage.textContent = `${currentRating} Bintang`;
                }
            });

            star.addEventListener('click', function() {
                currentRating = parseInt(this.dataset.rating);
                selectedRatingInput.value = currentRating;
                ratingMessage.textContent = `${currentRating} Bintang dipilih!`;
            });
        });

        document.getElementById('ratingForm').addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent default form submission

            const itemId = document.getElementById('ratingItemId').value;
            const donorId = document.getElementById('ratingDonorId').value;
            const recipientId = document.getElementById('ratingRecipientId').value;
            const rating = document.getElementById('selectedRating').value;

            if (rating === "0") {
                alert("Harap pilih jumlah bintang.");
                return;
            }

            const formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('donor_id', donorId);
            formData.append('recipient_id', recipientId);
            formData.append('rating', rating);

            fetch('submit_rating.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Rating berhasil dikirim!");
                    closeRatingModal();
                    location.reload(); // Reload profile page to update status and level/rating
                } else {
                    alert("Gagal mengirim rating: " + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("Terjadi kesalahan saat mengirim rating.");
            });
        });

        // Function to clear filter for giving history
        function clearFilterGiving() {
            window.location.href = window.location.pathname; // Clears all query parameters
        }

        // Function to clear filter for receiving history
        function clearFilterReceiving() {
            window.location.href = window.location.pathname; // Clears all query parameters
        }

        // Close modals if the user clicks anywhere outside of them
        window.onclick = function(event) {
            if (event.target == filterGivingModal) { // Also close giving filter modal
                closeFilterGivingModal();
            }
            if (event.target == filterReceivingModal) {
                closeFilterReceivingModal();
            }
            if (event.target == ratingModal) {
                closeRatingModal();
            }
        }
    </script>
</body>
</html>