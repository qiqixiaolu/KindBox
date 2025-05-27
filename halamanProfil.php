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
$giving_history = []; // Array to store giving history
$receiving_history = []; // Array to store receiving history (new)
$donations_given_count = 0; // To store the count of given items
$current_level = 1; // Default level

try {
    // Fetch user data
    $stmt = $conn->prepare("SELECT full_name, username, email, location, profile_picture_url FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        // If user data not found, something is wrong, redirect to logout
        session_destroy();
        header("Location: halamanLogin.php");
        exit();
    }

    // Fetch giving history for the logged-in user
    $stmt_giving = $conn->prepare("SELECT item_name, recipient_username, recipient_location, status, item_image_url, donor_location FROM donations WHERE donor_id = :user_id ORDER BY created_at DESC");
    $stmt_giving->bindParam(':user_id', $user_id);
    $stmt_giving->execute();
    $giving_history = $stmt_giving->fetchAll(PDO::FETCH_ASSOC);

    // Get the count of donations given
    $stmt_donations_count = $conn->prepare("SELECT COUNT(*) AS total_donations FROM donations WHERE donor_id = :user_id");
    $stmt_donations_count->bindParam(':user_id', $user_id);
    $stmt_donations_count->execute();
    $donations_given_count_result = $stmt_donations_count->fetch(PDO::FETCH_ASSOC);
    $donations_given_count = $donations_given_count_result['total_donations'];

    // Calculate the current level based on donations_given_count
    $current_level = floor($donations_given_count / 5) + 1;

    // Fetch receiving history for the logged-in user (new)
    $stmt_receiving = $conn->prepare("SELECT item_name, donor_username, donor_location, status, item_image_url FROM donations WHERE recipient_id = :user_id ORDER BY created_at DESC");
    $stmt_receiving->bindParam(':user_id', $user_id);
    $stmt_receiving->execute();
    $receiving_history = $stmt_receiving->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Handle database error
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
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet"/>
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }
    @media (min-width: 768px) {
      .desktop-only {
        display: block;
      }
      .mobile-only {
        display: none;
      }
    }
    @media (max-width: 767px) {
      .desktop-only {
        display: none;
      }
      .mobile-only {
        display: block;
      }
    }
  </style>
</head>
<body class="bg-white text-[#1f3a2f] min-h-screen flex flex-col">
  <!-- Mobile Version -->
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
              for ($i = 1; $i <= $max_stars; $i++) {
                  if ($i <= $current_level) {
                      echo '<i class="fas fa-star"></i>';
                  } else {
                      echo '<i class="fas fa-star text-gray-300"></i>';
                  }
              }
              ?>
            </span>
          </div>
        </div>
      </section>
      
      <section class="mb-6">
        <h3 class="font-extrabold text-[#1f3a2f] mb-2 text-sm">Riwayat Memberi</h3>
        <form class="flex items-center space-x-2 mb-3">
          <label class="sr-only" for="search-giving">Cari di Riwayat Pemberian</label>
          <div class="flex items-center flex-grow border border-[#4a5a44] rounded-lg px-3 py-1 text-[#4a5a44] text-sm">
            <i class="fas fa-search mr-2"></i>
            <input class="bg-transparent focus:outline-none w-full" id="search-giving" placeholder="Cari di Riwayat Pemberian" type="search"/>
          </div>
          <button aria-label="Filter" class="bg-[#DCE9C9] rounded-md p-2 text-black text-xs flex items-center justify-center">
            <i class="fas fa-sliders-h"></i>
          </button>
        </form>
        <div class="grid grid-cols-2 gap-3">
          <?php if (empty($giving_history)): ?>
            <p class="col-span-full text-center text-gray-500 text-xs">Belum ada riwayat pemberian.</p>
          <?php else: ?>
            <?php foreach (array_slice($giving_history, 0, 2) as $item): ?>
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
                    <?= htmlspecialchars($item['status'] ?? 'Available') ?>
                </p>
                <button class="mt-2 w-full bg-[#6b856d] text-white text-xs rounded-md py-1" type="button">
                    Lihat Detail
                </button>
                </div>
            </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
      
      <section class="mb-6">
        <h3 class="font-extrabold text-[#1f3a2f] mb-2 text-sm">Riwayat Penerimaan</h3>
        <form class="flex items-center space-x-2 mb-3">
          <label class="sr-only" for="search-receiving">Cari di Riwayat Penerimaan</label>
          <div class="flex items-center flex-grow border border-[#4a5a44] rounded-lg px-3 py-1 text-[#4a5a44] text-sm">
            <i class="fas fa-search mr-2"></i>
            <input class="bg-transparent focus:outline-none w-full" id="search-receiving" placeholder="Cari di Riwayat Penerimaan" type="search"/>
          </div>
          <button aria-label="Filter" class="bg-[#DCE9C9] rounded-md p-2 text-black text-xs flex items-center justify-center">
            <i class="fas fa-sliders-h"></i>
          </button>
        </form>
        <div class="grid grid-cols-2 gap-3">
          <?php if (empty($receiving_history)): ?>
            <p class="col-span-full text-center text-gray-500 text-xs">Belum ada riwayat penerimaan.</p>
          <?php else: ?>
            <?php foreach (array_slice($receiving_history, 0, 2) as $item): ?>
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
                    <?= htmlspecialchars($item['status'] ?? 'Available') ?>
                </p>
                <button class="mt-2 w-full bg-[#6b856d] text-white text-xs rounded-md py-1" type="button">
                    Lihat Detail
                </button>
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
    
    <nav aria-label="Primary" class="bg-[#6b856d] py-3 flex justify-around text-white text-xl fixed bottom-0 left-0 w-full max-w-md mx-auto">
      <button aria-label="Home" class="focus:outline-none" onclick="location.href='index.php'">
        <i class="fas fa-home"></i>
      </button>
      <button aria-label="Add" class="focus:outline-none" onclick="location.href='halamanTambah.php'">
        <i class="fas fa-plus"></i>
      </button>
      <button aria-label="Profile" class="focus:outline-none" onclick="location.href='profile.php'">
        <i class="fas fa-user"></i>
      </button>
    </nav>
  </div>

  <!-- Desktop Version -->
  <div class="desktop-only">
    <header class="bg-[#6B8569] flex items-center justify-between px-4 sm:px-6 md:px-10 h-16">
      <div class="flex items-center space-x-3">
        <button aria-label="Open menu" class="text-black text-xl sm:hidden">
          <i class="fas fa-bars"></i>
        </button>
        <img alt="KindBox logo with box and hands icon" class="w-10 h-10" height="40" src="https://storage.googleapis.com/a1aa/image/ba6cfd8e-3595-41ca-6af3-146bbdb8fc60.jpg" width="40"/>
        <span class="font-extrabold text-black text-lg select-none">KindBox</span>
      </div>
      <nav class="hidden sm:flex space-x-8 text-black text-sm font-normal">
        <a class="hover:underline" href="#">Home</a>
        <a class="hover:underline" href="#">Contact</a>
        <a class="hover:underline" href="#">About</a>
        <a class="hover:underline" href="#">Sign Up</a>
      </nav>
      <div class="flex items-center space-x-3">
        <div class="relative">
          <input class="rounded-md text-xs placeholder:text-xs placeholder:text-[#6B8569] py-2 pl-3 pr-8 focus:outline-none" placeholder="What are you looking for?" style="width: 220px" type="text"/>
          <button aria-label="Search" class="absolute right-1 top-1/2 -translate-y-1/2 text-black text-xs">
            <i class="fas fa-search"></i>
          </button>
        </div>
        <button aria-label="Cart" class="relative text-black text-lg">
          <i class="fas fa-shopping-cart"></i>
          <span class="absolute -top-1 -right-2 bg-red-600 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">2</span>
        </button>
        <button aria-label="Notifications" class="text-black text-lg">
          <i class="fas fa-bell"></i>
        </button>
        <button aria-label="User account" class="bg-black text-white rounded-full w-8 h-8 flex items-center justify-center text-sm">
          <i class="fas fa-user"></i>
        </button>
      </div>
    </header>
    <main class="flex flex-1 overflow-auto">
      <aside class="bg-[#DCE9C9] w-72 flex flex-col items-center py-8 px-6 space-y-6 select-none">
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
      <section class="flex-1 p-6 md:p-10 space-y-6 overflow-auto">
        <div class="bg-[#F3F9ED] rounded-md flex flex-col md:flex-row items-center md:items-start space-y-4 md:space-y-0 md:space-x-6 p-6">
          <div class="bg-[#2F4A27] text-[#E4F0D6] font-extrabold text-5xl flex items-center justify-center rounded-md w-24 h-24 flex-shrink-0">
            <?= $current_level ?>
          </div>
          <div class="text-black text-lg font-normal max-w-xl">
            Kamu telah memberikan barang sebanyak
            <span class="font-extrabold"><?= $donations_given_count ?></span>
            kali. Level Kebaikan kamu adalah
            <span class="font-extrabold">Level <?= $current_level ?></span>.
            <div class="mt-2 text-yellow-400 text-xl flex items-center space-x-1">
              <?php
              $max_stars = 5;
              for ($i = 1; $i <= $max_stars; $i++) {
                  if ($i <= $current_level) {
                      echo '<i class="fas fa-star"></i>';
                  } else {
                      echo '<i class="fas fa-star text-gray-300"></i>';
                  }
              }
              ?>
            </div>
          </div>
        </div>
        
        <h3 class="font-extrabold text-black text-lg">Riwayat Memberi</h3>
        <div class="flex items-center space-x-2 max-w-xl">
          <div class="relative flex-1">
            <input class="w-full rounded-md bg-[#DCE9C9] text-xs placeholder:text-[#A3B3A7] placeholder:text-xs py-2 pl-9 pr-3 focus:outline-none" placeholder="Cari di Riwayat Memberi" type="text"/>
            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-[#A3B3A7] text-xs">
              <i class="fas fa-search"></i>
            </div>
          </div>
          <button aria-label="Filter" class="bg-[#DCE9C9] rounded-md p-2 text-black text-xs flex items-center justify-center">
            <i class="fas fa-sliders-h"></i>
          </button>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <?php if (empty($giving_history)): ?>
            <p class="col-span-full text-center text-gray-500">Belum ada riwayat pemberian.</p>
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
                    <span><?= htmlspecialchars($item['status'] ?? 'Status Tidak Diketahui') ?></span>
                  </div>
                  <div class="flex items-center space-x-1">
                    <i class="fas fa-info-circle"></i>
                    <span><?= htmlspecialchars($item['status'] ?? 'Status Tidak Diketahui') ?></span>
                </div>
                <button class="mt-2 w-full bg-[#6b856d] text-white text-xs rounded-md py-1" type="button">
                    Lihat Detail
                </button>
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

        <h3 class="font-extrabold text-black text-lg mt-8">Riwayat Penerimaan</h3>
        <div class="flex items-center space-x-2 max-w-xl">
          <div class="relative flex-1">
            <input class="w-full rounded-md bg-[#DCE9C9] text-xs placeholder:text-[#A3B3A7] placeholder:text-xs py-2 pl-9 pr-3 focus:outline-none" placeholder="Cari di Riwayat Penerimaan" type="text"/>
            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-[#A3B3A7] text-xs">
              <i class="fas fa-search"></i>
            </div>
          </div>
          <button aria-label="Filter" class="bg-[#DCE9C9] rounded-md p-2 text-black text-xs flex items-center justify-center">
            <i class="fas fa-sliders-h"></i>
          </button>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <?php if (empty($receiving_history)): ?>
            <p class="col-span-full text-center text-gray-500">Belum ada riwayat penerimaan.</p>
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
                    <span><?= htmlspecialchars($item['status'] ?? 'Status Tidak Diketahui') ?></span>
                  </div>
                  <div class="flex items-center space-x-1">
                        <i class="fas fa-info-circle"></i>
                        <span><?= htmlspecialchars($item['status'] ?? 'Status Tidak Diketahui') ?></span>
                    </div>
                    <button class="mt-2 w-full bg-[#6b856d] text-white text-xs rounded-md py-1" type="button">
                        Lihat Detail
                    </button>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </main>
  </div>
</body>
</html>