<?php

session_start();

require 'db.php'; // Koneksi ke database

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: halamanLogin.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

if ($item_id === 0) {
    // Redirect if no item_id is provided or invalid
    header("Location: beranda.php?error=itemnotfound"); // Redirect to home or an error page
    exit();
}

// --- START: Handle Verification and Rejection ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $item_id_post = intval($_POST['item_id']);
    $recipient_user_id = intval($_POST['recipient_user_id']);
    $action = $_POST['action'];

    // Fetch item details immediately for action validation
    $stmt_check_item = $conn->prepare("SELECT donor_id, item_count FROM donations WHERE id = :item_id");
    $stmt_check_item->bindParam(':item_id', $item_id_post);
    $stmt_check_item->execute();
    $item_data_for_action = $stmt_check_item->fetch(PDO::FETCH_ASSOC);

    if (!$item_data_for_action || $item_data_for_action['donor_id'] != $current_user_id) {
        // If not the donor of the item, deny action
        $_SESSION['error_message'] = "Unauthorized action.";
        header("Location: detailBarang.php?item_id=" . $item_id_post);
        exit();
    }

    $current_item_count = $item_data_for_action['item_count'];

    // Fetch the current interest status to prevent re-verification/re-rejection
    // *** CHANGE: Using 'status' column from 'interests' table for verification status ***
    $stmt_check_interest_status = $conn->prepare("SELECT status FROM interests WHERE item_id = :item_id AND user_id = :user_id");
    $stmt_check_interest_status->bindParam(':item_id', $item_id_post);
    $stmt_check_interest_status->bindParam(':user_id', $recipient_user_id);
    $stmt_check_interest_status->execute();
    $interest_current_status = $stmt_check_interest_status->fetchColumn();

    if ($interest_current_status === 'verified') {
        $_SESSION['error_message'] = "Peminat ini sudah diverifikasi sebelumnya.";
        header("Location: detailBarang.php?item_id=" . $item_id_post);
        exit();
    }
    if ($interest_current_status === 'rejected') {
        $_SESSION['error_message'] = "Peminat ini sudah ditolak sebelumnya.";
        header("Location: detailBarang.php?item_id=" . $item_id_post);
        exit();
    }


    if ($action === 'verify') {
        // Check item_count only when verifying. This is where the error message for 0 count will appear.
        if ($current_item_count <= 0) {
            $_SESSION['error_message'] = "Barang sudah habis, tidak dapat diverifikasi.";
            header("Location: detailBarang.php?item_id=" . $item_id_post);
            exit();
        }

        // Fetch recipient's username and location for donations table update
        $stmt_fetch_recipient_info = $conn->prepare("SELECT username, location FROM users WHERE id = :user_id");
        $stmt_fetch_recipient_info->bindParam(':user_id', $recipient_user_id);
        $stmt_fetch_recipient_info->execute();
        $recipient_info = $stmt_fetch_recipient_info->fetch(PDO::FETCH_ASSOC);
        $recipient_username = $recipient_info['username'] ?? 'N/A';
        $recipient_location = $recipient_info['location'] ?? 'N/A';

        try {
            // Start transaction
            $conn->beginTransaction();

            // 1. Update the 'interests' status for the verified recipient to 'verified'
            // *** CHANGE: Updating 'status' column in 'interests' table ***
            $stmt_update_interest_status = $conn->prepare("UPDATE interests SET status = 'verified' WHERE item_id = :item_id AND user_id = :recipient_id");
            $stmt_update_interest_status->bindParam(':item_id', $item_id_post);
            $stmt_update_interest_status->bindParam(':recipient_id', $recipient_user_id);
            $stmt_update_interest_status->execute();

            // 2. Decrement item_count in donations table and set recipient details
            // The item_count should only be decremented once for each successful verification
            // *** CHANGE: Corrected status mapping for 'donations' table ('Received' instead of 'Diterima', 'Available' instead of 'Tersedia') ***
            $stmt_update_donation = $conn->prepare("
                UPDATE donations
                SET item_count = item_count - 1,
                    recipient_id = :recipient_id,
                    recipient_username = :recipient_username,
                    recipient_location = :recipient_location,
                    status = CASE WHEN item_count - 1 <= 0 THEN 'Received' ELSE 'Available' END
                WHERE id = :item_id
            ");
            $stmt_update_donation->bindParam(':item_id', $item_id_post);
            $stmt_update_donation->bindParam(':recipient_id', $recipient_user_id);
            $stmt_update_donation->bindParam(':recipient_username', $recipient_username);
            $stmt_update_donation->bindParam(':recipient_location', $recipient_location);
            $stmt_update_donation->execute();

            // Commit transaction
            $conn->commit();

            $_SESSION['success_message'] = "Peminat berhasil diverifikasi dan jumlah barang telah berkurang.";
            header("Location: detailBarang.php?item_id=" . $item_id_post); // Redirect back to detail page
            exit();

        } catch (PDOException $e) {
            // Rollback transaction if error occurs
            $conn->rollBack();
            $_SESSION['error_message'] = "Gagal memverifikasi peminat: " . $e->getMessage();
            error_log("Verification error: " . $e->getMessage()); // Log the error for debugging
            header("Location: detailBarang.php?item_id=" . $item_id_post);
            exit();
        }
    } elseif ($action === 'reject') {
        try {
            // Update interest status to 'rejected'
            // *** CHANGE: Updating 'status' column in 'interests' table ***
            $stmt_update_interest_status = $conn->prepare("UPDATE interests SET status = 'rejected' WHERE item_id = :item_id AND user_id = :recipient_id");
            $stmt_update_interest_status->bindParam(':item_id', $item_id_post);
            $stmt_update_interest_status->bindParam(':recipient_id', $recipient_user_id);
            $stmt_update_interest_status->execute();

            $_SESSION['success_message'] = "Peminat berhasil ditolak.";
            header("Location: detailBarang.php?item_id=" . $item_id_post); // Redirect back to detail page
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Gagal menolak peminat: " . $e->getMessage();
            error_log("Rejection error: " . $e->getMessage()); // Log the error for debugging
            header("Location: detailBarang.php?item_id=" . $item_id_post);
            exit();
        }
    }
}
// --- END: Handle Verification and Rejection ---


$item_details = null;
$donor_details = null;
$is_donor = false; // Flag to check if the logged-in user is the donor
$user_has_expressed_interest = false; // New flag to check if current user has expressed interest

$peminat_count = 0;
$peminat_list = []; // List of users interested in this item

try {
    // 1. Fetch Item Details
    // Ensure item_details is fetched AFTER any POST actions so it reflects the latest state.
    $stmt_item = $conn->prepare("
        SELECT
            d.id, d.item_name, d.item_description, d.item_image_url,
            d.status, d.donor_id, d.donor_username, d.donor_location,
            d.item_count, d.category, d.item_condition, d.whatsapp_contact,
            d.recipient_id, d.recipient_username, d.recipient_location
        FROM donations d
        WHERE d.id = :item_id
    ");
    $stmt_item->bindParam(':item_id', $item_id);
    $stmt_item->execute();
    $item_details = $stmt_item->fetch(PDO::FETCH_ASSOC);

    if (!$item_details) {
        // Item not found
        header("Location: beranda.php?error=itemnotfound");
        exit();
    }

    // Determine if current user is the donor
    if ($item_details['donor_id'] == $current_user_id) {
        $is_donor = true;
    }

    // 2. Fetch Donor Details (even if current user is donor, we still fetch their full details here)
    $stmt_donor = $conn->prepare("SELECT full_name, username, profile_picture_url FROM users WHERE id = :donor_id");
    $stmt_donor->bindParam(':donor_id', $item_details['donor_id']);
    $stmt_donor->execute();
    $donor_raw_details = $stmt_donor->fetch(PDO::FETCH_ASSOC);

    // Calculate donor's goodness level (rating) based on average rating from 'ratings' table
    $stmt_donor_avg_rating = $conn->prepare("SELECT AVG(rating) AS avg_rating FROM ratings WHERE donor_id = :donor_id");
    $stmt_donor_avg_rating->bindParam(':donor_id', $item_details['donor_id']);
    $stmt_donor_avg_rating->execute();
    $donor_avg_rating_result = $stmt_donor_avg_rating->fetch(PDO::FETCH_ASSOC);
    // Round to one decimal place for display, default to 0 if no ratings
    $donor_average_rating = round($donor_avg_rating_result['avg_rating'] ?? 0, 1); 

    // Calculate donor's goodness level based on total donations received (every 5 items)
    $stmt_donor_donations_count = $conn->prepare("SELECT COUNT(*) AS total_donations FROM donations WHERE donor_id = :donor_id AND status = 'Received'");
    $stmt_donor_donations_count->bindParam(':donor_id', $item_details['donor_id']);
    $stmt_donor_donations_count->execute();
    $donor_donations_count_result = $stmt_donor_donations_count->fetch(PDO::FETCH_ASSOC);
    $donor_level = floor($donor_donations_count_result['total_donations'] / 5) + 1;

    $donor_details = [
        'full_name' => htmlspecialchars($donor_raw_details['full_name'] ?? 'N/A'),
        'username' => htmlspecialchars($donor_raw_details['username'] ?? 'N/A'),
        'profile_picture_url' => htmlspecialchars($donor_raw_details['profile_picture_url'] ?? 'https://storage.googleapis.com/a1aa/image/bd3a933d-2733-4863-bea1-4a32e05e398e.jpg'),
        'level' => $donor_level, // Goodness level based on donation count
        'average_rating' => $donor_average_rating // Average star rating
    ];

    // 3. Fetch Peminat (Interested Users) Details (requires 'interests' table)
    $table_exists_query = $conn->query("SHOW TABLES LIKE 'interests'")->fetch();
    if ($table_exists_query) {
        // Count all interests for this specific item, regardless of status
        $stmt_peminat_count = $conn->prepare("SELECT COUNT(*) AS total_peminat FROM interests WHERE item_id = :item_id");
        $stmt_peminat_count->bindParam(':item_id', $item_id);
        $stmt_peminat_count->execute();
        $peminat_count_result = $stmt_peminat_count->fetch(PDO::FETCH_ASSOC);
        $peminat_count = $peminat_count_result['total_peminat'];

        // Check the current user's specific interest status
        $current_user_interest_status = ''; // Initialize
        // *** CHANGE: Using 'status' column from 'interests' table ***
        $stmt_check_interest = $conn->prepare("SELECT status FROM interests WHERE item_id = :item_id AND user_id = :user_id");
        $stmt_check_interest->bindParam(':item_id', $item_id);
        $stmt_check_interest->bindParam(':user_id', $current_user_id);
        $stmt_check_interest->execute();
        $user_has_expressed_interest_data = $stmt_check_interest->fetch(PDO::FETCH_ASSOC);

        if ($user_has_expressed_interest_data) {
            $user_has_expressed_interest = true;
            // *** CHANGE: Accessing 'status' column ***
            $current_user_interest_status = $user_has_expressed_interest_data['status'];
        } else {
            $user_has_expressed_interest = false;
        }

        // *** CHANGE: Selecting 'status' as 'interest_status' for clarity in array ***
        $stmt_peminat_list = $conn->prepare("
            SELECT
                i.user_id, u.full_name, u.username, u.profile_picture_url, u.email, u.location, i.whatsapp_user_contact,
                i.nama AS interest_nama, i.alamat AS interest_alamat, i.jumlah AS interest_jumlah, i.item_alasan AS interest_item_alasan,
                i.status AS interest_status -- Use 'status' column for interest status
            FROM interests i
            JOIN users u ON i.user_id = u.id
            WHERE i.item_id = :item_id
            ORDER BY
                CASE i.status
                    WHEN 'pending' THEN 1
                    WHEN 'verified' THEN 2
                    WHEN 'rejected' THEN 3
                    ELSE 4
                END,
                i.created_at ASC
        "); // Order by status: pending first, then verified, then rejected. Then by creation date.
        $stmt_peminat_list->bindParam(':item_id', $item_id);
        $stmt_peminat_list->execute();
        $raw_peminat_list = $stmt_peminat_list->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_peminat_list as $peminat) {
            // Fetch user's donations count for level calculation
            // *** CHANGE: Using 'Received' as the status for completed donations ***
            $stmt_user_donations_count = $conn->prepare("SELECT COUNT(*) AS total_donations FROM donations WHERE donor_id = :user_id AND status = 'Received'");
            $stmt_user_donations_count->bindParam(':user_id', $peminat['user_id']);
            $stmt_user_donations_count->execute();
            $user_donations_count_result = $stmt_user_donations_count->fetch(PDO::FETCH_ASSOC);
            $peminat_level = floor($user_donations_count_result['total_donations'] / 5) + 1;

            // Fetch average rating for each potential recipient (peminat)
            $stmt_peminat_avg_rating = $conn->prepare("SELECT AVG(rating) AS avg_rating FROM ratings WHERE donor_id = :user_id");
            $stmt_peminat_avg_rating->bindParam(':user_id', $peminat['user_id']);
            $stmt_peminat_avg_rating->execute();
            $peminat_avg_rating_result = $stmt_peminat_avg_rating->fetch(PDO::FETCH_ASSOC);
            $peminat_average_rating = round($peminat_avg_rating_result['avg_rating'] ?? 0, 1);

            $peminat_list[] = [
                'user_id' => $peminat['user_id'],
                'full_name' => htmlspecialchars($peminat['full_name'] ?? 'N/A'),
                'username' => htmlspecialchars($peminat['username'] ?? 'N/A'),
                'profile_picture_url' => htmlspecialchars($peminat['profile_picture_url'] ?? 'https://storage.googleapis.com/a1aa/image/bd3a933d-2733-4863-bea1-4a32e05e398e.jpg'),
                'level' => $peminat_level,
                'average_rating' => $peminat_average_rating, // Added average rating for peminat
                'email' => htmlspecialchars($peminat['email'] ?? 'N/A'),
                'location' => htmlspecialchars($peminat['location'] ?? 'N/A'),
                'whatsapp_contact' => htmlspecialchars($peminat['whatsapp_user_contact'] ?? ''),
                'interest_nama' => htmlspecialchars($peminat['interest_nama'] ?? 'N/A'),
                'interest_alamat' => htmlspecialchars($peminat['interest_alamat'] ?? 'N/A'),
                'interest_jumlah' => htmlspecialchars($peminat['interest_jumlah'] ?? 'N/A'),
                'interest_item_alasan' => htmlspecialchars($peminat['interest_item_alasan'] ?? 'N/A'),
                'interest_status' => htmlspecialchars($peminat['interest_status'] ?? 'pending') // Added status
            ];
        }
    } else {
        $peminat_count = 0;
        $peminat_list = [];
    }

} catch (PDOException $e) {
    die("Error fetching item details or donor info: " . $e->getMessage());
}

// Extract item details for display
$item_name = htmlspecialchars($item_details['item_name'] ?? 'N/A');
$item_condition = htmlspecialchars($item_details['item_condition'] ?? 'N/A');
$item_description = htmlspecialchars($item_details['item_description'] ?? 'N/A');
$item_count = htmlspecialchars($item_details['item_count'] ?? 'N/A'); // This will now reflect the decremented value
$donor_location = htmlspecialchars($item_details['donor_location'] ?? 'N/A');
$item_image_url = htmlspecialchars($item_details['item_image_url'] ?? 'https://storage.googleapis.com/a1aa/image/placeholder.jpg'); // Placeholder image
$whatsapp_contact_item = htmlspecialchars($item_details['whatsapp_contact'] ?? '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Detail Barang - <?= $item_name ?></title>
    <link rel="stylesheet" href="beranda.css"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
        rel="stylesheet"
    />
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap"
        rel="stylesheet"
    />
    <style>
        body {
            font-family: "Poppins", sans-serif;
        }
        /* Styles for the modal */
        .modal {
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
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
        }
        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        /* Style for indicated interested items */
        .item-has-interest {
            border: 2px solid #6B856D; /* Green border for items with interest */
            box-shadow: 0 0 8px rgba(107, 133, 109, 0.4); /* Subtle shadow */
        }
        /* Rating stars */
        .rating-stars .fa-star {
            color: #ddd; /* Default gray */
        }
        .rating-stars .fa-star.filled {
            color: #ffc107; /* Gold for filled stars */
        }
    </style>
</head>
<body class="bg-white">

    <header
        class="bg-[#7B927B] flex items-center px-4 py-3 text-white fixed top-0 left-0 w-full z-20 md:hidden"
        aria-label="Mobile Navigation Header"
    >
        <button aria-label="Back to home" class="mr-4 text-lg" onclick="history.back()">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="flex-1 text-center font-semibold text-lg leading-6 select-none">
            Detail Barang
        </h1>
        <div class="w-6"></div>
    </header>

    <header
        class="bg-[#7B927B] hidden md:flex items-center px-6 py-4 text-white fixed top-0 left-0 w-full z-20"
        aria-label="Desktop Navigation Header"
    >
        <button aria-label="Back to home" class="mr-6 text-xl" onclick="history.back()">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="flex-1 text-center font-semibold text-xl leading-7 select-none">
            Detail Barang
        </h1>
        <div class="w-8"></div>
    </header>

    <div class="h-14 md:h-16"></div>

    <?php
        // Tampilkan pesan sukses atau error jika ada
        if (isset($_SESSION['success_message'])) {
            echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mx-auto my-4 max-w-7xl" role="alert">
                        <strong class="font-bold">Sukses!</strong>
                        <span class="block sm:inline">' . $_SESSION['success_message'] . '</span>
                        <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display=\'none\';">
                            <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                        </span>
                    </div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mx-auto my-4 max-w-7xl" role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline">' . $_SESSION['error_message'] . '</span>
                        <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display=\'none\';">
                            <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                        </span>
                    </div>';
            unset($_SESSION['error_message']);
        }
    ?>

    <div class="max-w-7xl mx-auto min-h-screen flex flex-col md:flex-row md:gap-6 p-4 pt-0 md:pt-6 pb-20">
        <section class="md:w-1/3 rounded-xl overflow-hidden relative">
            <img
                alt="<?= $item_name ?>"
                class="w-full rounded-xl object-cover"
                height="400"
                src="<?= $item_image_url ?>"
                width="400"
            />
        </section>

        <section class="md:w-2/3 flex flex-col">
            <h2 class="font-semibold text-[#2F4F2F] text-2xl leading-8 mb-2">
                <?= $item_name ?>
            </h2>

            <div class="text-[#2F4F2F] text-sm mb-1">
                <p class="font-semibold">Kondisi Barang: <span class="font-normal"><?= $item_condition ?></span></p>
                <p class="font-semibold mt-2">Deskripsi:</p>
                <p class="font-normal mt-0.5 whitespace-pre-wrap"><?= $item_description ?></p>
            </div>

            <hr class="border-t border-gray-300 my-4" />

            <div class="text-[#2F4F2F] font-semibold text-sm mb-4">
                <div class="flex justify-between py-2 border-b border-gray-300">
                    <span>Jumlah</span>
                    <span class="font-normal"><?= $item_count ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-300">
                    <span>Lokasi</span>
                    <span class="font-normal border-l border-[#7B927B] pl-3">
                        <?= $donor_location ?>
                    </span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-300">
                    <span class="flex items-center gap-2">
                        Peminat
                        <span
                            class="bg-[#B7C9B7] text-[#4F6B4F] text-xs font-semibold rounded-full px-2 py-0.5 select-none"
                            ><?= $peminat_count ?></span
                        >
                    </span>
                    <span class="flex items-center gap-2">
                        Tersedia
                        <span
                            class="bg-[#B7C9B7] text-[#4F6B4F] text-xs font-semibold rounded-full px-2 py-0.5 select-none"
                            ><?= $item_details['item_count'] ?></span
                        >
                    </span>
                </div>
            </div>

            <?php if (!$is_donor): // Peminat Version ?>
            <section
                aria-label="Informasi Pemberi Barang"
                class="flex items-center justify-between bg-[#B7C9B7] rounded-lg p-3 mb-6"
            >
                <div class="flex items-center gap-3">
                    <img
                        alt="Profil <?= $donor_details['username'] ?>"
                        class="w-14 h-14 rounded-full object-cover"
                        height="56"
                        src="<?= $donor_details['profile_picture_url'] ?>"
                        width="56"
                    />
                    <div>
                        <p class="text-[#4F6B4F] font-semibold text-sm mb-1">
                            <?= $donor_details['username'] ?>
                        </p>
                        <div class="rating-stars text-sm">
                            <?php
                                $max_stars = 5;
                                $filled_stars_donor_display = round($donor_details['average_rating']); // Use rounded average rating for display
                                for ($i = 1; $i <= $max_stars; $i++) {
                                    if ($i <= $filled_stars_donor_display) {
                                        echo '<i class="fas fa-star filled"></i>';
                                    } else {
                                        echo '<i class="fas fa-star"></i>';
                                    }
                                }
                            ?>
                        </div>
                    </div>
                </div>
                <a
                    href="https://wa.me/<?= htmlspecialchars($whatsapp_contact_item) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="bg-[#4F6B4F] hover:bg-[#3e5740] text-white font-semibold rounded-lg px-5 py-3 flex items-center gap-2 select-none"
                    aria-label="Hubungi via WhatsApp"
                >
                    <i class="fab fa-whatsapp text-lg"></i> WhatsApp
                </a>
            </section>

            <?php if ($current_user_interest_status === 'verified'): // Current user's interest is verified ?>
                <button
                    type="button"
                    class="bg-green-600 text-white font-semibold rounded-lg py-2 text-md select-none cursor-not-allowed"
                    disabled
                >
                    Anda Telah Diverifikasi
                </button>
                <p class="text-center text-green-700 font-semibold text-sm mt-2">Selamat! Anda telah diverifikasi sebagai penerima barang ini.</p>
            <?php elseif ($current_user_interest_status === 'rejected'): // Current user's interest is rejected ?>
                <button
                    type="button"
                    class="bg-red-600 text-white font-semibold rounded-lg py-2 text-md select-none cursor-not-allowed"
                    disabled
                >
                    Pengajuan Ditolak
                </button>
                <p class="text-center text-red-700 text-sm mt-2">Maaf, pengajuan minat Anda telah ditolak oleh pemberi.</p>
            <?php elseif ($user_has_expressed_interest): // Current user has expressed interest (status is 'pending') ?>
                <button
                    type="button"
                    class="bg-gray-400 text-white font-semibold rounded-lg py-2 text-md select-none cursor-not-allowed"
                    disabled
                >
                    Sudah Minat
                </button>
                <p class="text-center text-gray-600 text-sm mt-2">Anda sudah menyatakan minat pada barang ini. Tunggu verifikasi dari pemberi.</p>
            <?php else: // Current user has not expressed interest yet ?>
                <?php if ($item_details['item_count'] > 0): // Only allow interest if item is available ?>
                <button
                    type="button"
                    class="bg-[#4F6B4F] hover:bg-[#3e5740] text-white font-semibold rounded-lg py-2 text-md select-none"
                    onclick="location.href='minat.php?item_id=<?= $item_id ?>'"
                >
                    Minat
                </button>
                <?php else: // Item is out of stock ?>
                <button
                    type="button"
                    class="bg-gray-400 text-white font-semibold rounded-lg py-2 text-md select-none cursor-not-allowed"
                    disabled
                >
                    Barang Habis
                </button>
                <p class="text-center text-gray-600 text-sm mt-2">Maaf, barang ini sudah habis.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php else: // Pemberi Version (current user is the donor) ?>
            <section aria-label="Daftar Peminat" class="mb-6">
                <p class="text-[#2F4F2F] font-semibold text-sm mb-4 select-none">
                    Daftar Peminat:
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <?php if (empty($peminat_list)): ?>
                        <p class="col-span-full text-center text-gray-500">Belum ada peminat untuk barang ini.</p>
                    <?php else: ?>
                        <?php foreach ($peminat_list as $peminat): ?>
                            <div
                                class="bg-[#B7C9B7] rounded-lg flex flex-col sm:flex-row items-center sm:items-start gap-4 p-4 select-none
                                <?php
                                    if ($peminat['interest_status'] === 'verified') echo 'border-2 border-green-500 shadow-md cursor-pointer';
                                    elseif ($peminat['interest_status'] === 'rejected') echo 'border-2 border-red-500 opacity-70 cursor-pointer';
                                    else echo 'cursor-pointer'; // Default for pending
                                ?> "
                                onclick="showPeminatDetails(
                                    '<?= $peminat['profile_picture_url'] ?>',
                                    '<?= $peminat['username'] ?>',
                                    '<?= $peminat['full_name'] ?>',
                                    '<?= $peminat['location'] ?>',
                                    '<?= $peminat['email'] ?>',
                                    '<?= $peminat['whatsapp_contact'] ?>',
                                    '<?= $peminat['average_rating'] ?>',  // Pass average_rating to modal
                                    '<?= $peminat['interest_nama'] ?>',
                                    '<?= $peminat['interest_alamat'] ?>',
                                    '<?= $peminat['interest_jumlah'] ?>',
                                    '<?= $peminat['interest_item_alasan'] ?>',
                                    '<?= $peminat['interest_status'] ?>' // Pass status to modal
                                )"
                            >
                                <img
                                    alt="Profil <?= $peminat['username'] ?>"
                                    class="w-14 h-14 rounded-full object-cover flex-shrink-0"
                                    height="56"
                                    src="<?= $peminat['profile_picture_url'] ?>"
                                    width="56"
                                />
                                <div class="flex-1 text-center sm:text-left">
                                    <p class="text-[#4F6B4F] font-semibold text-sm mb-1">
                                        <?= $peminat['username'] ?>
                                    </p>
                                    <p class="text-[#4F6B4F] text-xs mb-1">
                                        <?= $peminat['full_name'] ?>
                                    </p>
                                    <p class="text-[#4F6B4F] text-xs mb-2">
                                        <?= $peminat['location'] ?>
                                    </p>
                                    <div class="rating-stars text-sm mb-3">
                                        <?php
                                            $max_stars_peminat = 5;
                                            $filled_stars_peminat_display = round($peminat['average_rating']); // Use rounded average rating for display
                                            for ($i = 1; $i <= $max_stars_peminat; $i++) {
                                                if ($i <= $filled_stars_peminat_display) {
                                                    echo '<i class="fas fa-star filled"></i>';
                                                } else {
                                                    echo '<i class="fas fa-star"></i>';
                                                }
                                            }
                                        ?>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <?php if ($peminat['interest_status'] === 'verified'): ?>
                                            <p class="text-center text-green-700 font-semibold text-xs">Peminat ini telah diverifikasi!</p>
                                        <?php elseif ($peminat['interest_status'] === 'rejected'): ?>
                                            <p class="text-center text-red-700 font-semibold text-xs">Peminat ini telah ditolak.</p>
                                        <?php else: // interest_status is 'pending' ?>
                                        <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memverifikasi peminat ini? Tindakan ini akan mengurangi jumlah barang jika berhasil.');">
                                            <input type="hidden" name="item_id" value="<?= $item_id ?>">
                                            <input type="hidden" name="recipient_user_id" value="<?= $peminat['user_id'] ?>">
                                            <button type="submit" name="action" value="verify"
                                                    class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold text-xs rounded-md py-2">
                                                    Verifikasi
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menolak peminat ini?');">
                                            <input type="hidden" name="item_id" value="<?= $item_id ?>">
                                            <input type="hidden" name="recipient_user_id" value="<?= $peminat['user_id'] ?>">
                                            <button type="submit" name="action" value="reject"
                                                    class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold text-xs rounded-md py-2">
                                                    Tolak
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <div class="flex gap-4 mb-16">
                <button
                    type="button"
                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg py-3 select-none"
                    onclick="location.href='edit_item.php?item_id=<?= $item_id ?>'"
                >
                    Edit Barang
                </button>
                <button
                    type="button"
                    class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg py-3 select-none"
                    onclick="location.href='delete_item.php?item_id=<?= $item_id ?>'"
                >
                    Hapus Barang
                </button>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <div id="peminatDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closePeminatDetailsModal()">&times;</span>
            <h3 class="text-xl font-semibold text-[#2F4F2F] mb-4">Detail Peminat</h3>
            <div class="flex items-center gap-4 mb-4">
                <img id="modalPeminatImage" alt="Profile Picture" class="w-20 h-20 rounded-full object-cover"/>
                <div>
                    <p id="modalPeminatUsername" class="text-[#2F4F2F] font-semibold text-lg"></p>
                    <p id="modalPeminatFullName" class="text-[#4F6B4F] text-sm"></p>
                    <div id="modalPeminatRatingStars" class="rating-stars text-base mt-1"></div>
                </div>
            </div>
            <div class="text-[#2F4F2F] text-sm space-y-2 mb-6">
                <p><i class="fas fa-map-marker-alt mr-2"></i> Lokasi Akun: <span id="modalPeminatLocation"></span></p>
                <p><i class="fas fa-envelope mr-2"></i> Email Akun: <span id="modalPeminatEmail"></span></p>
                <hr class="border-t border-gray-300 my-2" />
                <h4 class="font-semibold text-base mt-4 mb-2">Detail Pengajuan Minat:</h4>
                <p><i class="fas fa-user-circle mr-2"></i> Nama Penerima: <span id="modalInterestNama"></span></p>
                <p><i class="fas fa-home mr-2"></i> Alamat Lengkap: <span id="modalInterestAlamat"></span></p>
                <p><i class="fas fa-cubes mr-2"></i> Jumlah Barang Diminta: <span id="modalInterestJumlah"></span></p>
                <p><i class="fas fa-info-circle mr-2"></i> Alasan Minat: <span id="modalInterestAlasan"></span></p>
                <p><i class="fab fa-whatsapp mr-2"></i> Kontak WhatsApp Peminat: <span id="modalPeminatWhatsapp"></span></p>
                <p><i class="fas fa-info-circle mr-2"></i> Status Minat: <span id="modalInterestStatus" class="font-bold"></span></p>
            </div>
            <a id="modalPeminatWhatsappLink" href="#" target="_blank" rel="noopener noreferrer"
                class="mt-6 block w-full text-center bg-[#4F6B4F] hover:bg-[#3e5740] text-white font-semibold rounded-lg px-5 py-3 flex items-center justify-center gap-2 select-none">
                <i class="fab fa-whatsapp text-lg"></i> Hubungi via WhatsApp
            </a>
        </div>
    </div>

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

    <script>
        // Get the modal element
        var modal = document.getElementById("peminatDetailsModal");

        // Get the <span> element that closes the modal
        var span = document.getElementsByClassName("close-button")[0];

        // Function to show the modal with peminat details
        function showPeminatDetails(imageUrl, username, fullName, location, email, whatsapp, averageRating, interestNama, interestAlamat, interestJumlah, interestAlasan, interestStatus) {
            document.getElementById('modalPeminatImage').src = imageUrl;
            document.getElementById('modalPeminatUsername').textContent = '@' + username;
            document.getElementById('modalPeminatFullName').textContent = fullName;
            document.getElementById('modalPeminatLocation').textContent = location;
            document.getElementById('modalPeminatEmail').textContent = email;

            // Set WhatsApp contact and link
            const whatsappTextElement = document.getElementById('modalPeminatWhatsapp');
            const whatsappLinkElement = document.getElementById('modalPeminatWhatsappLink');
            if (whatsapp) {
                whatsappTextElement.textContent = whatsapp;
                whatsappLinkElement.href = 'https://wa.me/' + whatsapp;
                whatsappLinkElement.style.display = 'flex'; // Show the button
            } else {
                whatsappTextElement.textContent = 'Tidak Tersedia';
                whatsappLinkElement.style.display = 'none'; // Hide the button if no contact
            }

            // Set rating stars
            const ratingStarsElement = document.getElementById('modalPeminatRatingStars');
            ratingStarsElement.innerHTML = ''; // Clear previous stars
            const maxStars = 5;
            const filledStars = Math.round(averageRating); // Round to nearest integer for displaying filled stars
            for (let i = 1; i <= maxStars; i++) {
                const star = document.createElement('i');
                star.classList.add('fas', 'fa-star');
                if (i <= filledStars) {
                    star.classList.add('filled'); // Filled star
                } else {
                    star.classList.add('text-gray-300'); // Empty star
                }
                ratingStarsElement.appendChild(star);
            }

            // Set interest form details
            document.getElementById('modalInterestNama').textContent = interestNama;
            document.getElementById('modalInterestAlamat').textContent = interestAlamat;
            document.getElementById('modalInterestJumlah').textContent = interestJumlah;
            document.getElementById('modalInterestAlasan').textContent = interestAlasan;

            // Set and style status
            const modalInterestStatusElement = document.getElementById('modalInterestStatus');
            modalInterestStatusElement.textContent = interestStatus.charAt(0).toUpperCase() + interestStatus.slice(1); // Capitalize first letter

            modalInterestStatusElement.classList.remove('text-green-600', 'text-red-600', 'text-gray-600'); // Clear previous classes
            if (interestStatus === 'verified') {
                modalInterestStatusElement.classList.add('text-green-600');
            } else if (interestStatus === 'rejected') {
                modalInterestStatusElement.classList.add('text-red-600');
            } else { // pending
                modalInterestStatusElement.classList.add('text-gray-600');
            }


            modal.style.display = "flex"; // Use flex to center the modal content
        }

        // Function to close the modal
        function closePeminatDetailsModal() {
            modal.style.display = "none";
        }

        // Close the modal if the user clicks anywhere outside of it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>

</body>
</html>