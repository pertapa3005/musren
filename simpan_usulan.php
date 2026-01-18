<?php
session_start();

/* ======================
   VALIDASI LOGIN
====================== */
if (
    !isset($_SESSION['login']) ||
    $_SESSION['login'] !== true ||
    !isset($_SESSION['rw'])
) {
    header("Location: login.php");
    exit;
}

$rw = (int) $_SESSION['rw'];

/* ======================
   KONEKSI DATABASE
====================== */
$conn = new mysqli("localhost", "root", "", "musren");
if ($conn->connect_error) {
    die("Koneksi database gagal");
}

/* ======================
   AMBIL DATA POST
====================== */
$jenis        = $_POST['jenis_usulan'] ?? '';
$no_rt        = $_POST['no_rt'] ?? null;
$perangkat    = trim($_POST['perangkat_daerah'] ?? '');
$usulan_id    = (int) ($_POST['usulan_id'] ?? 0);
$volume       = (int) ($_POST['volume'] ?? 0);
$permasalahan = trim($_POST['permasalahan'] ?? '');
$kelompok     = trim($_POST['kelompok_sasaran'] ?? '');
$alamat       = trim($_POST['alamat'] ?? '');

/* ======================
   VALIDASI DASAR
====================== */
if (!$jenis || !$perangkat || !$usulan_id || $volume <= 0) {
    die("Data tidak lengkap");
}

if ($jenis === 'rt' && !$no_rt) {
    die("No RT wajib diisi untuk RT Berkelas");
}

if ($permasalahan === '') {
    die("Permasalahan wajib diisi");
}

/* ======================
   AMBIL HARGA SATUAN
====================== */
$stmt = $conn->prepare("
    SELECT harga
    FROM usulan_musrenbang
    WHERE id = ?
");
$stmt->bind_param("i", $usulan_id);
$stmt->execute();
$stmt->bind_result($harga_satuan);
$stmt->fetch();
$stmt->close();

if (!$harga_satuan) {
    die("Harga satuan tidak ditemukan");
}

$subtotal_baru = $harga_satuan * $volume;

/* ======================
   HITUNG TOTAL SEBELUMNYA
====================== */
$total_sebelumnya = 0;

/* ===== RT BERKELAS (≤ 50 JT per RW+RT) ===== */
if ($jenis === 'rt') {

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(subtotal),0)
        FROM usulan_warga
        WHERE jenis_usulan = 'rt'
          AND rw = ?
          AND no_rt = ?
    ");
    $stmt->bind_param("ii", $rw, $no_rt);
    $stmt->execute();
    $stmt->bind_result($total_sebelumnya);
    $stmt->fetch();
    $stmt->close();

    if (($total_sebelumnya + $subtotal_baru) > 50000000) {
        die(
            "Total usulan RT Berkelas (RW $rw RT $no_rt) " .
            "melebihi batas Rp 50.000.000"
        );
    }
}

/* ===== MUSRENBANG ===== */
if ($jenis === 'musrenbang') {

    $is_kec_kel =
        stripos($perangkat, 'kecamatan') !== false ||
        stripos($perangkat, 'kelurahan') !== false;

    /* ≤ 60 JT untuk kecamatan / kelurahan */
    if ($is_kec_kel) {

        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(subtotal),0)
            FROM usulan_warga
            WHERE jenis_usulan = 'musrenbang'
              AND rw = ?
              AND (
                    perangkat_daerah LIKE '%kecamatan%'
                 OR perangkat_daerah LIKE '%kelurahan%'
              )
        ");
        $stmt->bind_param("i", $rw);
        $stmt->execute();
        $stmt->bind_result($total_sebelumnya);
        $stmt->fetch();
        $stmt->close();

        if (($total_sebelumnya + $subtotal_baru) > 60000000) {
            die(
                "Total usulan Musrenbang Kecamatan/Kelurahan (RW $rw) " .
                "melebihi batas Rp 60.000.000"
            );
        }
    }
    /* OPD lain → tidak dibatasi */
}

/* ======================
   SIMPAN DATA UTAMA
====================== */
$stmt = $conn->prepare("
    INSERT INTO usulan_warga
    (jenis_usulan, rw, no_rt, perangkat_daerah, usulan_id,
     permasalahan, volume, subtotal, kelompok_sasaran, alamat)
    VALUES (?,?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
    "siisssisss",
    $jenis,
    $rw,
    $no_rt,
    $perangkat,
    $usulan_id,
    $permasalahan,
    $volume,
    $subtotal_baru,
    $kelompok,
    $alamat
);

$stmt->execute();
$usulan_warga_id = $stmt->insert_id;
$stmt->close();

/* ======================
   UPLOAD GAMBAR (OPSIONAL)
====================== */
if (!empty($_FILES['gambar']['name'][0])) {

    if (!is_dir("uploads")) {
        mkdir("uploads", 0777, true);
    }

    foreach ($_FILES['gambar']['tmp_name'] as $i => $tmp) {
        if ($_FILES['gambar']['error'][$i] === UPLOAD_ERR_OK) {

            $ext = pathinfo($_FILES['gambar']['name'][$i], PATHINFO_EXTENSION);
            $nama = uniqid("img_") . "." . $ext;

            move_uploaded_file($tmp, "uploads/" . $nama);

            $g = $conn->prepare("
                INSERT INTO usulan_warga_gambar
                (usulan_warga_id, nama_file)
                VALUES (?,?)
            ");
            $g->bind_param("is", $usulan_warga_id, $nama);
            $g->execute();
            $g->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Usulan Berhasil</title>
<style>
body {
    font-family: Arial, sans-serif;
    background:#f3f4f6;
    height:100vh;
    margin:0;
    display:flex;
    align-items:center;
    justify-content:center;
}
.box {
    background:#fff;
    padding:30px;
    border-radius:8px;
    width:420px;
    text-align:center;
    box-shadow:0 10px 25px rgba(0,0,0,0.1);
}
.box h2 { color:#15803d; }
.btn {
    display:inline-block;
    padding:10px 18px;
    margin:6px;
    border-radius:4px;
    text-decoration:none;
    font-weight:bold;
}
.btn-primary { background:#2563eb; color:#fff; }
.btn-secondary { background:#16a34a; color:#fff; }
</style>
</head>
<body>

<div class="box">
    <h2>Usulan Berhasil Disimpan</h2>
    <p>Silakan pilih langkah berikutnya:</p>

    <a href="index.php" class="btn btn-primary">
        ➕ Buat Usulan Baru
    </a>

    <a href="preview_cetak.php" class="btn btn-secondary">
        🖨 Preview / Cetak Usulan
    </a>
</div>

</body>
</html>
