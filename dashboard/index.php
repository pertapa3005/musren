<?php
session_start();
require_once '../koneksi.php';

if (!isset($_SESSION['login'], $_SESSION['rw'])) {
    header("Location: ../login.php");
    exit;
}

$rw = (int) $_SESSION['rw'];

/* =========================
   DATA SUMMARY
========================= */
$q1 = $conn->prepare("SELECT COUNT(*) FROM usulan_warga WHERE jenis_usulan='musrenbang' AND rw=?");
$q1->bind_param("i", $rw);
$q1->execute();
$q1->bind_result($total_usulan);
$q1->fetch();
$q1->close();

$q2 = $conn->prepare("SELECT COALESCE(SUM(subtotal),0) FROM usulan_warga WHERE jenis_usulan='musrenbang' AND rw=?");
$q2->bind_param("i", $rw);
$q2->execute();
$q2->bind_result($total_anggaran);
$q2->fetch();
$q2->close();

$q3 = $conn->prepare("SELECT COUNT(DISTINCT no_rt) FROM usulan_warga WHERE jenis_usulan='musrenbang' AND rw=?");
$q3->bind_param("i", $rw);
$q3->execute();
$q3->bind_result($rt_aktif);
$q3->fetch();
$q3->close();

$q4 = $conn->prepare("SELECT COUNT(DISTINCT perangkat_daerah) FROM usulan_warga WHERE jenis_usulan='musrenbang' AND rw=?");
$q4->bind_param("i", $rw);
$q4->execute();
$q4->bind_result($opd);
$q4->fetch();
$q4->close();

/* =========================
   GRAFIK RT
========================= */
$rt = [];
$rt_jumlah = [];
$res = $conn->prepare("
    SELECT no_rt, COUNT(*) jumlah
    FROM usulan_warga
    WHERE jenis_usulan='musrenbang' AND rw=?
    GROUP BY no_rt
    ORDER BY no_rt
");
$res->bind_param("i", $rw);
$res->execute();
$r = $res->get_result();
while ($d = $r->fetch_assoc()) {
    $rt[] = 'RT '.$d['no_rt'];
    $rt_jumlah[] = $d['jumlah'];
}
$res->close();

/* =========================
   GRAFIK OPD
========================= */
$opd_nama = [];
$opd_jumlah = [];
$res = $conn->prepare("
    SELECT perangkat_daerah, COUNT(*) jumlah
    FROM usulan_warga
    WHERE jenis_usulan='musrenbang' AND rw=?
    GROUP BY perangkat_daerah
");
$res->bind_param("i", $rw);
$res->execute();
$r = $res->get_result();
while ($d = $r->fetch_assoc()) {
    $opd_nama[] = $d['perangkat_daerah'];
    $opd_jumlah[] = $d['jumlah'];
}
$res->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Musrenbang RW <?= $rw ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">

<div class="container-fluid p-4">

<h4 class="mb-4">📊 Dashboard Musrenbang RW <?= $rw ?></h4>

<!-- SUMMARY -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <small>Total Usulan</small>
                <h3><?= $total_usulan ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <small>Total Anggaran</small>
                <h3>Rp <?= number_format($total_anggaran,0,',','.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <small>RT Aktif</small>
                <h3><?= $rt_aktif ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <small>Perangkat Daerah</small>
                <h3><?= $opd ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- GRAFIK -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6>Usulan per RT</h6>
                <canvas id="chartRT"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6>Perangkat Daerah</h6>
                <canvas id="chartOPD"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- TABEL -->
<div class="card shadow-sm">
<div class="card-body">
<h6>Daftar Usulan</h6>

<table class="table table-bordered table-sm">
<thead>
<tr>
    <th>No</th>
    <th>Permasalahan</th>
    <th>RT</th>
    <th>Perangkat</th>
    <th>Volume</th>
    <th>Subtotal</th>
    <th>Tanggal</th>
</tr>
</thead>
<tbody>
<?php
$no = 1;
$q = $conn->prepare("
    SELECT permasalahan,no_rt,perangkat_daerah,volume,subtotal,created_at
    FROM usulan_warga
    WHERE jenis_usulan='musrenbang' AND rw=?
    ORDER BY created_at DESC
");
$q->bind_param("i", $rw);
$q->execute();
$r = $q->get_result();
while ($d = $r->fetch_assoc()):
?>
<tr>
    <td><?= $no++ ?></td>
    <td><?= htmlspecialchars($d['permasalahan']) ?></td>
    <td><?= $d['no_rt'] ?></td>
    <td><?= $d['perangkat_daerah'] ?></td>
    <td><?= $d['volume'] ?></td>
    <td>Rp <?= number_format($d['subtotal'],0,',','.') ?></td>
    <td><?= date('d-m-Y H:i', strtotime($d['created_at'])) ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</div>
</div>

</div>

<script>
new Chart(document.getElementById('chartRT'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($rt) ?>,
        datasets: [{
            label: 'Jumlah Usulan',
            data: <?= json_encode($rt_jumlah) ?>
        }]
    }
});

new Chart(document.getElementById('chartOPD'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($opd_nama) ?>,
        datasets: [{
            data: <?= json_encode($opd_jumlah) ?>
        }]
    }
});
</script>

</body>
</html>
