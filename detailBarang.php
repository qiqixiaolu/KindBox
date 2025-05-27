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

$item_details = null;
$donor_details = null;
$is_donor = false; // Flag to check if the logged-in user is the donor

$peminat_count = 0;
$peminat_list = []; // List of users interested in this item

try {
    // 1. Fetch Item Details
    // ADDED whatsapp_contact to SELECT statement
    $stmt_item = $conn->prepare("
        SELECT 
            d.id, d.item_name, d.item_description, d.item_image_url, 
            d.status, d.donor_id, d.donor_username, d.donor_location, 
            d.item_count, d.category, d.item_condition, d.whatsapp_contact
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

    // Calculate donor's goodness level for star rating
    $stmt_donor_donations_count = $conn->prepare("SELECT COUNT(*) AS total_donations FROM donations WHERE donor_id = :donor_id");
    $stmt_donor_donations_count->bindParam(':donor_id', $item_details['donor_id']);
    $stmt_donor_donations_count->execute();
    $donor_donations_count_result = $stmt_donor_donations_count->fetch(PDO::FETCH_ASSOC);
    $donor_level = floor($donor_donations_count_result['total_donations'] / 5) + 1;

    $donor_details = [
        'full_name' => htmlspecialchars($donor_raw_details['full_name'] ?? 'N/A'),
        'username' => htmlspecialchars($donor_raw_details['username'] ?? 'N/A'),
        'profile_picture_url' => htmlspecialchars($donor_raw_details['profile_picture_url'] ?? 'https://storage.googleapis.com/a1aa/image/bd3a933d-2733-4863-bea1-4a32e05e398e.jpg'),
        'level' => $donor_level
    ];

    // 3. Fetch Peminat (Interested Users) Details (requires 'interests' table)
    // Check if the 'interests' table exists before querying
    $table_exists_query = $conn->query("SHOW TABLES LIKE 'interests'")->fetch();
    if ($table_exists_query) {
        $stmt_peminat_count = $conn->prepare("SELECT COUNT(*) AS total_peminat FROM interests WHERE item_id = :item_id");
        $stmt_peminat_count->bindParam(':item_id', $item_id);
        $stmt_peminat_count->execute();
        $peminat_count_result = $stmt_peminat_count->fetch(PDO::FETCH_ASSOC);
        $peminat_count = $peminat_count_result['total_peminat'];

        $stmt_peminat_list = $conn->prepare("
            SELECT 
                u.full_name, u.username, u.profile_picture_url, 
                (SELECT COUNT(*) FROM donations WHERE donor_id = u.id) AS user_donations_count
            FROM interests i
            JOIN users u ON i.user_id = u.id
            WHERE i.item_id = :item_id
        ");
        $stmt_peminat_list->bindParam(':item_id', $item_id);
        $stmt_peminat_list->execute();
        $raw_peminat_list = $stmt_peminat_list->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_peminat_list as $peminat) {
            $peminat_level = floor($peminat['user_donations_count'] / 5) + 1;
            $peminat_list[] = [
                'full_name' => htmlspecialchars($peminat['full_name'] ?? 'N/A'),
                'username' => htmlspecialchars($peminat['username'] ?? 'N/A'),
                'profile_picture_url' => htmlspecialchars($peminat['profile_picture_url'] ?? 'https://storage.googleapis.com/a1aa/image/bd3a933d-2733-4863-bea1-4a32e05e398e.jpg'),
                'level' => $peminat_level
            ];
        }
    } else {
        // If 'interests' table doesn't exist, set count to 0 and list empty
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
$item_count = htmlspecialchars($item_details['item_count'] ?? 'N/A');
$donor_location = htmlspecialchars($item_details['donor_location'] ?? 'N/A');
$item_image_url = htmlspecialchars($item_details['item_image_url'] ?? 'https://storage.googleapis.com/a1aa/image/placeholder.jpg'); // Placeholder image
$whatsapp_contact_item = htmlspecialchars($item_details['whatsapp_contact'] ?? ''); // NEW: Get WhatsApp contact
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1" name="viewport" />
  <title>Detail Barang - <?= $item_name ?></title>
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

  <?php if (!$is_donor): // Peminat Version ?>
  <div
    class="max-w-7xl mx-auto min-h-screen flex flex-col md:flex-row md:gap-6 p-4 pt-0 md:pt-6"
    id="peminat-version"
  >
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

      <div class="flex justify-between text-[#2F4F2F] font-semibold text-sm mb-1">
        <div>
          <p>Kondisi Barang</p>
          <p class="font-normal mt-0.5">Deskripsi</p>
        </div>
        <p class="self-center font-normal"><?= $item_condition ?></p>
      </div>
      <p class="text-[#2F4F2F] font-normal text-sm leading-relaxed mb-4">
        <?= $item_description ?>
      </p>

      <hr class="border-t border-gray-300 mb-4" />

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
            <div class="text-[#4F6B4F] text-sm">
              <?php
                $max_stars = 5;
                for ($i = 1; $i <= $max_stars; $i++) {
                    if ($i <= $donor_details['level']) {
                        echo '<i class="fas fa-star"></i>';
                    } else {
                        echo '<i class="fas fa-star text-gray-300"></i>';
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

      <button
          type="button"
          class="bg-[#4F6B4F] hover:bg-[#3e5740] text-white font-semibold rounded-lg py-2 text-md select-none"
          onclick="location.href='minat.php?item_id=<?= $item_id ?>'"
      >
          Minat
      </button>
    </section>
  </div>

  <?php else: // Pemberi Version (current user is the donor) ?>
  <div
    class="max-w-7xl mx-auto min-h-screen flex flex-col md:flex-row md:gap-6 p-4 pt-0 md:pt-6"
    id="pemberi-version"
  >
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

      <div class="flex justify-between text-[#2F4F2F] font-semibold text-sm mb-1">
        <div>
          <p>Kondisi Barang</p>
          <p class="font-normal mt-0.5">Deskripsi</p>
        </div>
        <p class="self-center font-normal"><?= $item_condition ?></p>
      </div>
      <p class="text-[#2F4F2F] font-normal text-sm leading-relaxed mb-4">
        <?= $item_description ?>
      </p>

      <hr class="border-t border-gray-300 mb-4" />

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
                class="bg-[#B7C9B7] rounded-lg flex items-center gap-4 px-4 py-3 select-none"
              >
                <img
                  alt="Profil <?= $peminat['username'] ?>"
                  class="w-14 h-14 rounded-full object-cover"
                  height="56"
                  src="<?= $peminat['profile_picture_url'] ?>"
                  width="56"
                />
                <div class="flex-1">
                  <p class="text-[#4F6B4F] font-semibold text-sm mb-1">
                    <?= $peminat['username'] ?>
                  </p>
                  <div class="text-[#4F6B4F] text-sm">
                    <?php
                      $max_stars = 5;
                      for ($i = 1; $i <= $max_stars; $i++) {
                          if ($i <= $peminat['level']) {
                              echo '<i class="fas fa-star"></i>';
                          } else {
                              echo '<i class="fas fa-star text-gray-300"></i>';
                          }
                      }
                    ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <div class="flex gap-4">
        <button
          type="button"
          class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg py-3 select-none"
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
    </section>
  </div>
  <?php endif; ?>


  <nav
    class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-300 flex justify-around items-center py-3 text-[#7B927B] md:hidden"
  >
    <button aria-label="Home" class="flex flex-col items-center text-sm" onclick="location.href='beranda.php'">
      <i class="fas fa-home text-xl"></i>
      <span>Home</span>
    </button>
    <button aria-label="Add" class="flex flex-col items-center text-sm" onclick="location.href='halamanTambah.php'">
      <i class="fas fa-plus-square text-xl"></i>
      <span>Donasi</span>
    </button>
    <button aria-label="Profile" class="flex flex-col items-center text-sm" onclick="location.href='profile.php'">
      <i class="fas fa-user text-xl"></i>
      <span>Profil</span>
    </button>
  </nav>

</body>
</html>