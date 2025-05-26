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

try {
    $stmt = $conn->prepare("SELECT full_name, username, email, location, profile_picture_url FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        // Jika data user tidak ditemukan, mungkin ada masalah, arahkan ke logout
        session_destroy();
        header("Location: halamanLogin.php");
        exit();
    }
} catch (PDOException $e) {
    // Tangani error database
    die("Error mengambil data user: " . $e->getMessage());
}

// Set default jika data belum ada di DB
$full_name = htmlspecialchars($user_data['full_name'] ?? 'Nama Pengguna');
$username = htmlspecialchars($user_data['username'] ?? '@username');
$email = htmlspecialchars($user_data['email'] ?? 'email@example.com');
$location = htmlspecialchars($user_data['location'] ?? 'Lokasi Tidak Diketahui');
$profile_picture_url = htmlspecialchars($user_data['profile_picture_url'] ?? 'https://storage.googleapis.com/a1aa/image/bd3a933d-2733-4863-bea1-4a32e05e398e.jpg'); // Default avatar
?>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>
   KindBox
  </title>
  <script src="https://cdn.tailwindcss.com">
  </script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&amp;display=swap" rel="stylesheet"/>
  <style>
   body {
      font-family: "Poppins", sans-serif;
    }
  </style>
 </head>
 <body class="bg-[#E4F0D6] min-h-screen flex flex-col">
  <header class="bg-[#6B8569] flex items-center justify-between px-4 sm:px-6 md:px-10 h-16">
   <div class="flex items-center space-x-3">
    <button aria-label="Open menu" class="text-black text-xl sm:hidden">
     <i class="fas fa-bars">
     </i>
    </button>
    <img alt="KindBox logo with box and hands icon" class="w-10 h-10" height="40" src="https://storage.googleapis.com/a1aa/image/ba6cfd8e-3595-41ca-6af3-146bbdb8fc60.jpg" width="40"/>
    <span class="font-extrabold text-black text-lg select-none">
     KindBox
    </span>
   </div>
   <nav class="hidden sm:flex space-x-8 text-black text-sm font-normal">
    <a class="hover:underline" href="#">
     Home
    </a>
    <a class="hover:underline" href="#">
     Contact
    </a>
    <a class="hover:underline" href="#">
     About
    </a>
    <a class="hover:underline" href="#">
     Sign Up
    </a>
   </nav>
   <div class="flex items-center space-x-3">
    <div class="relative">
     <input class="rounded-md text-xs placeholder:text-xs placeholder:text-[#6B8569] py-2 pl-3 pr-8 focus:outline-none" placeholder="What are you looking for?" style="width: 220px" type="text"/>
     <button aria-label="Search" class="absolute right-1 top-1/2 -translate-y-1/2 text-black text-xs">
      <i class="fas fa-search">
      </i>
     </button>
    </div>
    <button aria-label="Cart" class="relative text-black text-lg">
     <i class="fas fa-shopping-cart">
     </i>
     <span class="absolute -top-1 -right-2 bg-red-600 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">
      2
     </span>
    </button>
    <button aria-label="Notifications" class="text-black text-lg">
     <i class="fas fa-bell">
     </i>
    </button>
    <button aria-label="User account" class="bg-black text-white rounded-full w-8 h-8 flex items-center justify-center text-sm">
     <i class="fas fa-user">
     </i>
    </button>
   </div>
  </header>
  <main class="flex flex-1 overflow-auto">
   <aside class="bg-[#DCE9C9] w-72 flex flex-col items-center py-8 px-6 space-y-6 select-none">
    <img alt="User avatar of a girl wearing a pink hoodie and light pink hijab" class="rounded-full w-36 h-36 object-cover" height="150" src="<?= $profile_picture_url ?>" width="150"/>
    <h2 class="font-extrabold text-black text-center text-sm">
     <?= $full_name ?>
    </h2>
    <div class="w-full space-y-4 text-xs text-black">
     <div class="flex items-center space-x-2">
      <i class="fas fa-user">
      </i>
      <span>
       <?= $username ?>
      </span>
     </div>
     <div class="flex items-center space-x-2">
      <i class="fas fa-envelope">
      </i>
      <span>
       <?= $email ?>
      </span>
     </div>
     <div class="flex items-center space-x-2">
      <i class="fas fa-map-marker-alt">
      </i>
      <span>
       <?= $location ?>
      </span>
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
      3
     </div>
     <div class="text-black text-lg font-normal max-w-xl">
      Kamu telah memberikan barang sebanyak
      <span class="font-extrabold">
       100
      </span>
      kali. Level Kebaikan kamu adalah
      <span class="font-extrabold">
       Level 3
      </span>
      .
      <div class="mt-2 text-yellow-400 text-xl flex items-center space-x-1">
       <i class="fas fa-star">
       </i>
       <i class="fas fa-star">
       </i>
       <i class="fas fa-star">
       </i>
       <i class="fas fa-star">
       </i>
       <i class="fas fa-star text-gray-300">
       </i>
      </div>
     </div>
    </div>
    <h3 class="font-extrabold text-black text-lg">
     Riwayat Memberi
    </h3>
    <div class="flex items-center space-x-2 max-w-xl">
     <div class="relative flex-1">
      <input class="w-full rounded-md bg-[#DCE9C9] text-xs placeholder:text-[#A3B3A7] placeholder:text-xs py-2 pl-9 pr-3 focus:outline-none" placeholder="Cari di Riwayat" type="text"/>
      <div class="absolute left-3 top-1/2 -translate-y-1/2 text-[#A3B3A7] text-xs">
       <i class="fas fa-search">
       </i>
      </div>
     </div>
     <button aria-label="Filter" class="bg-[#DCE9C9] rounded-md p-2 text-black text-xs flex items-center justify-center">
      <i class="fas fa-sliders-h">
      </i>
     </button>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
     <article class="bg-[#DCE9C9] rounded-md overflow-hidden">
      <img alt="Teddy bear plush toy sitting on a beige carpet" class="w-full h-44 object-cover" height="180" src="https://storage.googleapis.com/a1aa/image/9fbc1071-8104-49ab-b099-38e2b887d8c5.jpg" width="300"/>
      <div class="bg-[#A3B3A7] p-3 text-black text-xs space-y-1">
       <h4 class="font-extrabold text-[11px]">
        Boneka Beruang
       </h4>
       <div class="flex items-center space-x-1">
        <i class="fas fa-user">
        </i>
        <span>
         @userKindBox2
        </span>
       </div>
       <div class="flex items-center space-x-1">
        <i class="fas fa-map-marker-alt">
        </i>
        <span>
         Pasuruan, Jawa Timur
        </span>
       </div>
       <div class="flex items-center space-x-1">
        <i class="fas fa-info-circle">
        </i>
        <span>
         Sudah Diterima
        </span>
       </div>
      </div>
     </article>
     <article class="bg-[#DCE9C9] rounded-md overflow-hidden">
      <img alt="Book cover of Novel Laskar Pelangi with colorful sky and silhouette" class="w-full h-44 object-cover" height="180" src="https://storage.googleapis.com/a1aa/image/9369817b-d625-40c2-8fbe-5ac74cade286.jpg" width="300"/>
      <div class="bg-[#A3B3A7] p-3 text-black text-xs space-y-1">
       <h4 class="font-extrabold text-[11px]">
        Novel Laskar Pelangi
       </h4>
       <div class="flex items-center space-x-1">
        <i class="fas fa-user">
        </i>
        <span>
         @userKindBox2
        </span>
       </div>
       <div class="flex items-center space-x-1">
        <i class="fas fa-map-marker-alt">
        </i>
        <span>
         Mojokerto, Jawa Timur
        </span>
       </div>
       <div class="flex items-center space-x-1">
        <i class="fas fa-info-circle">
        </i>
        <span>
         Sudah Diterima
        </span>
       </div>
      </div>
     </article>
     <article class="bg-[#DCE9C9] rounded-md overflow-hidden">
      <img alt="Samsung Galaxy J5 smartphone on wooden table" class="w-full h-44 object-cover" height="180" src="https://storage.googleapis.com/a1aa/image/bb7c2410-5910-4aec-73e9-9324dfc69bdc.jpg" width="300"/>
      <div class="bg-[#A3B3A7] p-3 text-black text-xs space-y-1">
       <h4 class="font-extrabold text-[11px]">
        Samsung Galaxy J5
       </h4>
       <div class="flex items-center space-x-1">
        <i class="fas fa-user">
        </i>
        <span>
         @userKindBox2
        </span>
       </div>
       <div class="flex items-center space-x-1">
        <i class="fas fa-map-marker-alt">
        </i>
        <span>
         Surabaya, Jawa Timur
        </span>
       </div>
       <div class="flex items-center space-x-1">
        <i class="fas fa-info-circle">
        </i>
        <span>
         Sudah Diterima
        </span>
       </div>
      </div>
     </article>
     <article class="bg-[#DCE9C9] rounded-md overflow-hidden">
      <img alt="Stainless steel cooking pot with glass lid" class="w-full h-44 object-cover" height="180" src="https://storage.googleapis.com/a1aa/image/e4e761a2-f180-45a5-4f5d-a6794bf9a8b5.jpg" width="300"/>
      <div class="bg-[#A3B3A7] p-3 text-black text-xs space-y-1">
       <h4 class="font-extrabold text-[11px]">
        Panci Stainless
       </h4>
       <div class="flex items-center space-x-1">
        <i class="fas fa-user">
        </i>
        <span>
         @userKindBox2
        </span>
       </div>
       <div class="flex items-center space-x-1">
        <i class="fas fa-map-marker-alt">
        </i>
        <span>
         Sidoarjo, Jawa Timur
        </span>
       </div>
       <div class="flex items-center space-x-1">
        <i class="fas fa-info-circle">
        </i>
        <span>
         Sudah Diterima
        </span>
       </div>
      </div>
     </article>
     <article class="bg-[#DCE9C9] rounded-md overflow-hidden">
      <img alt="Blue backpack on wooden floor" class="w-full h-44 object-cover" height="180" src="https://storage.googleapis.com/a1aa/image/b3bc53a7-4f7e-44e9-078f-f5fb4aa949c8.jpg" width="300"/>
      <div class="bg-[#A3B3A7] p-3 text-black text-xs space-y-1">
       <h4 class="font-extrabold text-[11px]">
        Tas Ransel
       </h4>
       <div class="flex items-center space-x-1">
        <i class="fas fa-user">
        </i>
        <span>
         @userKindBox2
        </span>
       </div>
       <div class="flex items-center space-x-1">
        <i class="fas fa-map-marker-alt">
        </i>
        <span>
         Surabaya, Jawa Timur
        </span>
       </div>
      </div>
     </article>
     <article class="bg-[#DCE9C9] rounded-md overflow-hidden">
      <img alt="Blue 500 ml water bottle with strap on light blue background" class="w-full h-44 object-cover" height="180" src="https://storage.googleapis.com/a1aa/image/c7b08e54-c34b-4ac2-fc24-1ef95c70e74a.jpg" width="300"/>
      <div class="bg-[#A3B3A7] p-3 text-black text-xs space-y-1">
       <h4 class="font-extrabold text-[11px]">
        Botol Minum 500 ml
       </h4>
       <div class="flex items-center space-x-1">
        <i class="fas fa-user">
        </i>
        <span>
         @userKindBox2
        </span>
       </div>
       <div class="flex items-center space-x-1">
        <i class="fas fa-map-marker-alt">
        </i>
        <span>
         Malang, Jawa Timur
        </span>
       </div>
      </div>
     </article>
     <article class="bg-[#F3F9ED] rounded-md flex flex-col items-center justify-center p-6 text-[#A3B3A7] text-xs font-semibold select-none">
      <button aria-label="Donasikan Barang" class="mb-3 text-4xl border border-[#A3B3A7] rounded-full w-14 h-14 flex items-center justify-center">
       <i class="fas fa-plus">
       </i>
      </button>
     </article>
    </div>
   </section>
  </main>
 </body>
</html>