<?php
$errorMsg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $judul = isset($_POST['judul']) ? $_POST['judul'] : '';
  $deskripsi = isset($_POST['deskripsi']) ? $_POST['deskripsi'] : '';
  $lokasi = isset($_POST['lokasi']) ? $_POST['lokasi'] : '';
  $jumlah = isset($_POST['jumlah']) ? $_POST['jumlah'] : '';
  $kategori = isset($_POST['kategori']) ? $_POST['kategori'] : '';
  $kondisi = isset($_POST['kondisi']) ? $_POST['kondisi'] : '';
  
  if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
  }
  
  $uploadedFiles = [];
  $rejectedFiles = [];
  $allowedTypes = ['image/jpg', 'image/png'];
  
  if (isset($_FILES['foto']) && is_array($_FILES['foto']['name'])) {
    $totalFiles = count($_FILES['foto']['name']);
    
    if ($totalFiles > 5) {
      $errorMsg = "Maksimal hanya 5 foto yang diperbolehkan!";
    } elseif ($totalFiles == 0) {
      $errorMsg = "Minimal upload 1 foto.";
    } else {
      for ($i = 0; $i < $totalFiles; $i++) {
        if ($_FILES['foto']['error'][$i] == 0 && $_FILES['foto']['size'][$i] > 0) {
          $fileName = $_FILES['foto']['name'][$i];
          $fileType = $_FILES['foto']['type'][$i];
          $tmpName = $_FILES['foto']['tmp_name'][$i];
          
          if (in_array($fileType, $allowedTypes)) {
            $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueName = uniqid() . '.' . $fileExt;
            $targetPath = "uploads/" . $uniqueName;
            
            if (move_uploaded_file($tmpName, $targetPath)) {
              $uploadedFiles[] = $targetPath;
            } else {
              $errorMsg = "Gagal mengunggah file " . $fileName;
              break;
            }
          } else {
            $rejectedFiles[] = $fileName;
          }
        } else {
          $errorMsg = "Terjadi kesalahan saat mengunggah file.";
          break;
        }
      }
      
      // Jika tidak ada file yang valid
      if (empty($uploadedFiles) && !empty($rejectedFiles)) {
        $errorMsg = "Tidak ada file gambar yang valid untuk diunggah.";
      }
    }
  } else {
    $errorMsg = "Tidak ada file yang diunggah.";
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= empty($errorMsg) ? "Barang Berhasil Diupload" : "Error Upload" ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php if (empty($errorMsg)) : ?>
    <div class="upload-result">
      <h2>Barang Berhasil Diupload!</h2>
      <p><span>Nama Barang:</span> <?= htmlspecialchars($judul) ?></p>
      <p><span>Deskripsi:</span> <?= htmlspecialchars($deskripsi) ?></p>
      <p><span>Lokasi:</span> <?= htmlspecialchars($lokasi) ?></p>
      <p><span>Jumlah:</span> <?= htmlspecialchars($jumlah) ?></p>
      <p><span>Kategori:</span> <?= htmlspecialchars($kategori) ?></p>
      <p><span>Kondisi:</span> <?= htmlspecialchars($kondisi) ?></p>
      
      <?php if (!empty($rejectedFiles)) : ?>
        <div class="error-message" style="margin-bottom: 15px;">
          File berikut bukan format gambar yang didukung dan tidak diproses:<br>
          <?php foreach ($rejectedFiles as $file) : ?>
            - <?= htmlspecialchars($file) ?><br>
          <?php endforeach; ?>
          <small>Hanya file gambar (jpg, png) yang diproses.</small>
        </div>
      <?php endif; ?>
      
      <div class="photo-preview">
        <?php foreach ($uploadedFiles as $file) : ?>
          <img src="<?= htmlspecialchars($file) ?>" alt="Foto Barang">
        <?php endforeach; ?>
      </div>
      
      <div style="text-align: center; margin-top: 20px;">
        <a href="index.html" style="display: inline-block; background: #059669; color: white; padding: 12px 24px;
        border-radius: 8px; text-decoration: none; font-weight: bold; transition: background 0.3s;">Kembali ke Form</a>
      </div>
    </div>
  <?php else : ?>
    <div class="container">
      <div class="error-message">
        <?= htmlspecialchars($errorMsg) ?>
        <div style="text-align: center; margin-top: 20px;">
          <a href="index.html" style="display: inline-block; background: #059669; color: white; padding: 12px 24px;
          border-radius: 8px; text-decoration: none; font-weight: bold; transition: background 0.3s;">Kembali ke Form</a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</body>
</html>