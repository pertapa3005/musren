<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['login'], $_SESSION['rw'])) {
    header("Location: login.php");
    exit;
}

$rw = (int) $_SESSION['rw'];

$sql = "
SELECT *
FROM usulan_warga
WHERE jenis_usulan = 'musrenbang'
  AND rw = ?
ORDER BY id ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $rw);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die('Tidak ada usulan Musrenbang untuk RW ini.');
}

/* helper: tentukan sheet */
function tentukanSheet($perangkat_daerah) {
    $pd = strtolower(trim((string)$perangkat_daerah));
    if (
        $pd === '' ||
        strpos($pd, 'kelurahan') !== false ||
        strpos($pd, 'kecamatan') !== false
    ) {
        return 'Form 2.5'; // Kelurahan
    }
    return 'Form 2.4'; // OPD lain
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Preview Musrenbang</title>
<style>
body { font-family: Arial, sans-serif; background:#f3f4f6; }
.box { max-width:1200px; margin:40px auto; background:#fff; padding:24px; border-radius:8px; }
table { width:100%; border-collapse:collapse; margin-top:16px; }
th,td { border:1px solid #ccc; padding:8px; font-size:14px; }
th { background:#e5e7eb; }
.badge { padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; }
.badge-opd { background:#dcfce7; color:#166534; }
.badge-kel { background:#dbeafe; color:#1e40af; }
.badge-warn { background:#fee2e2; color:#991b1b; }
.btn { padding:10px 14px; border:none; border-radius:4px; font-weight:bold; cursor:pointer; }
.btn-export { background:#16a34a; color:#fff; }
.btn-back { background:#6b7280; color:#fff; text-decoration:none; }
</style>
</head>
<body>

<div class="box">
<h2>Preview Usulan Musrenbang</h2>
<p><b>RW <?= $rw ?></b></p>

<table>
<thead>
<tr>
    <th>No</th>
    <th>Permasalahan</th>
    <th>Usulan</th>
    <th>Lokasi</th>
    <th>Volume</th>
    <th>Anggaran</th>
    <th>Perangkat Daerah</th>
    <th>Sheet</th>
    <th>Status</th>
</tr>
</thead>
<tbody>
<?php
$no = 1;
$invalid = 0;

while ($r = $res->fetch_assoc()) {
    $sheet = tentukanSheet($r['perangkat_daerah']);
    $badgeSheet = ($sheet === 'Form 2.4') ? 'badge-opd' : 'badge-kel';

    // validasi minimal (OPD/Kelurahan boleh kosong)
    $error = [];
    if (empty($r['permasalahan'])) $error[] = 'Permasalahan';
    if (empty($r['usulan_id']))    $error[] = 'Usulan';
    if (empty($r['alamat']))       $error[] = 'Lokasi';
    if (empty($r['volume']))       $error[] = 'Volume';
    if (empty($r['subtotal']))     $error[] = 'Anggaran';

    if ($error) {
        $status = 'Kurang: '.implode(', ', $error);
        $badgeStatus = 'badge-warn';
        $invalid++;
    } else {
        $status = 'Lengkap';
        $badgeStatus = 'badge-opd';
    }
?>
<tr>
    <td><?= $no ?></td>
    <td><?= htmlspecialchars($r['permasalahan']) ?></td>
    <td><?= htmlspecialchars($r['usulan_id']) ?></td>
    <td><?= htmlspecialchars($r['alamat']) ?></td>
    <td><?= htmlspecialchars($r['volume']) ?></td>
    <td><?= number_format((int)$r['subtotal'],0,',','.') ?></td>
    <td><?= htmlspecialchars($r['perangkat_daerah'] ?: '-') ?></td>
    <td><span class="badge <?= $badgeSheet ?>"><?= $sheet ?></span></td>
    <td><span class="badge <?= $badgeStatus ?>"><?= $status ?></span></td>
</tr>
<?php
    $no++;
}
?>
</tbody>
</table>

<br>

<?php if ($invalid > 0): ?>
<p style="color:#b91c1c;font-weight:bold">
⚠ Terdapat <?= $invalid ?> usulan yang datanya belum lengkap.  
Silakan perbaiki sebelum export.
</p>
<?php else: ?>
<form action="export_excel_musrenbang.php" method="get" target="_blank">
    <button type="submit" class="btn btn-export">Export Excel Musrenbang</button>
</form>
<?php endif; ?>

<br>
<a href="preview_cetak.php" class="btn btn-back">← Kembali</a>
</div>
</body>
</html>
