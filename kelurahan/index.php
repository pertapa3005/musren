<?php
session_start();
require_once '../koneksi.php';

/* ===============================
   FILTER INPUT
================================ */
$jenis   = $_GET['jenis'] ?? 'musrenbang'; // musrenbang | rt | all
$filter_rw = (isset($_GET['rw']) && $_GET['rw'] !== '') ? (int)$_GET['rw'] : null;
$filter_rt = (isset($_GET['rt']) && $_GET['rt'] !== '') ? (int)$_GET['rt'] : null;
$filter_opd = $_GET['opd'] ?? '';

/* ===============================
   WHERE DINAMIS (FINAL)
================================ */
$where = "WHERE 1=1";

/* filter jenis usulan */
if ($jenis === 'musrenbang') {
    $where .= " AND jenis_usulan='musrenbang'";
} elseif ($jenis === 'rt') {
    $where .= " AND jenis_usulan='rt'";
}

/* filter RW */
if ($filter_rw) {
    $where .= " AND rw = $filter_rw";
}

/* filter RT */
if ($filter_rt !== null) {
    if ($filter_rt == 0) {
        $where .= " AND no_rt = 0";
    } else {
        $where .= " AND no_rt = $filter_rt";
    }
}

/* filter Perangkat Daerah */
if ($filter_opd !== '') {
    $opd_safe = $conn->real_escape_string($filter_opd);
    $where .= " AND perangkat_daerah = '$opd_safe'";
}

/* ===============================
   SUMMARY
================================ */
$total_usulan = $conn->query("
    SELECT COUNT(*) total FROM usulan_warga $where
")->fetch_assoc()['total'];

$total_anggaran = $conn->query("
    SELECT COALESCE(SUM(subtotal),0) total FROM usulan_warga $where
")->fetch_assoc()['total'];

$total_rw = $conn->query("
    SELECT COUNT(DISTINCT rw) total FROM usulan_warga $where
")->fetch_assoc()['total'];

$total_rt = $conn->query("
    SELECT COUNT(DISTINCT no_rt) total 
    FROM usulan_warga $where AND no_rt > 0
")->fetch_assoc()['total'];

/* ===============================
   GRAFIK PER RW
================================ */
$rw_label = [];
$rw_data = [];

$q = $conn->query("
    SELECT rw, COUNT(*) jumlah
    FROM usulan_warga
    $where
    GROUP BY rw
    ORDER BY rw
");
while ($d = $q->fetch_assoc()) {
    $rw_label[] = 'RW '.$d['rw'];
    $rw_data[] = $d['jumlah'];
}

/* ===============================
   GRAFIK PER OPD
================================ */
$opd_label = [];
$opd_data = [];

$q = $conn->query("
    SELECT perangkat_daerah, COUNT(*) jumlah
    FROM usulan_warga
    $where
    GROUP BY perangkat_daerah
    ORDER BY jumlah DESC
");
while ($d = $q->fetch_assoc()) {
    $opd_label[] = $d['perangkat_daerah'];
    $opd_data[] = $d['jumlah'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Kelurahan</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-light">
<div class="container-fluid p-4">

<h4 class="mb-3">🏛️ Dashboard Kelurahan</h4>

<!-- FILTER -->
<form method="get" class="row g-2 mb-3">

    <div class="col-md-2">
        <label class="form-label">Jenis Usulan</label>
        <select name="jenis" class="form-select" onchange="this.form.submit()">
            <option value="musrenbang" <?= $jenis=='musrenbang'?'selected':'' ?>>Musrenbang</option>
            <option value="rt" <?= $jenis=='rt'?'selected':'' ?>>Usulan RT</option>
            <option value="all" <?= $jenis=='all'?'selected':'' ?>>Semua</option>
        </select>
    </div>

    <div class="col-md-2">
        <label class="form-label">RW</label>
        <select name="rw" class="form-select" onchange="this.form.submit()">
            <option value="">Semua RW</option>
            <?php
            $q = $conn->query("SELECT DISTINCT rw FROM usulan_warga ORDER BY rw");
            while ($r = $q->fetch_assoc()):
            ?>
            <option value="<?= $r['rw'] ?>" <?= ($filter_rw==$r['rw']?'selected':'') ?>>
                RW <?= $r['rw'] ?>
            </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="col-md-2">
        <label class="form-label">RT</label>
        <select name="rt" class="form-select" onchange="this.form.submit()" <?= !$filter_rw?'disabled':'' ?>>
            <option value="">Semua</option>
            <option value="0" <?= ($filter_rt===0?'selected':'') ?>>Level RW</option>
            <?php
            if ($filter_rw):
                $q = $conn->query("
                    SELECT DISTINCT no_rt 
                    FROM usulan_warga
                    WHERE rw=$filter_rw
                    ORDER BY no_rt
                ");
                while ($r = $q->fetch_assoc()):
            ?>
            <option value="<?= $r['no_rt'] ?>" <?= ($filter_rt==$r['no_rt']?'selected':'') ?>>
                RT <?= $r['no_rt'] ?>
            </option>
            <?php endwhile; endif; ?>
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Perangkat Daerah</label>
        <select name="opd" class="form-select" onchange="this.form.submit()">
            <option value="">Semua Perangkat</option>
            <?php
            $q = $conn->query("
                SELECT DISTINCT perangkat_daerah 
                FROM usulan_warga 
                ORDER BY perangkat_daerah
            ");
            while ($r = $q->fetch_assoc()):
            ?>
            <option value="<?= htmlspecialchars($r['perangkat_daerah']) ?>"
                <?= ($filter_opd==$r['perangkat_daerah']?'selected':'') ?>>
                <?= htmlspecialchars($r['perangkat_daerah']) ?>
            </option>
            <?php endwhile; ?>
        </select>
    </div>

</form>

<p class="text-muted mb-3">
    Filter Aktif:
    <strong>
        <?= strtoupper($jenis) ?>
        <?= $filter_rw ? " | RW $filter_rw" : "" ?>
        <?= ($filter_rt !== null ? ($filter_rt==0 ? " | Level RW" : " | RT $filter_rt") : "") ?>
        <?= $filter_opd ? " | ".$filter_opd : "" ?>
    </strong>
</p>

<!-- SUMMARY -->
<div class="row mb-4">
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
        <small>Total Usulan</small>
        <h4><?= $total_usulan ?></h4>
    </div></div></div>

    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
        <small>Total Anggaran</small>
        <h4>Rp <?= number_format($total_anggaran,0,',','.') ?></h4>
    </div></div></div>

    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
        <small>Jumlah RW</small>
        <h4><?= $total_rw ?></h4>
    </div></div></div>

    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
        <small>Jumlah RT</small>
        <h4><?= $total_rt ?></h4>
    </div></div></div>
</div>

<!-- GRAFIK -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm"><div class="card-body">
            <h6>Usulan per RW</h6>
            <canvas id="chartRW"></canvas>
        </div></div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm"><div class="card-body">
            <h6>Distribusi Perangkat Daerah</h6>
            <canvas id="chartOPD"></canvas>
        </div></div>
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
    <th>Jenis</th>
    <th>RW</th>
    <th>RT</th>
    <th>Permasalahan</th>
    <th>Perangkat</th>
    <th>Volume</th>
    <th>Subtotal</th>
    <th>Tanggal</th>
</tr>
</thead>
<tbody>
<?php
$no = 1;
$q = $conn->query("
    SELECT jenis_usulan,rw,no_rt,permasalahan,perangkat_daerah,volume,subtotal,created_at
    FROM usulan_warga
    $where
    ORDER BY rw,no_rt,created_at DESC
");
while ($d = $q->fetch_assoc()):
?>
<tr>
    <td><?= $no++ ?></td>
    <td><?= strtoupper($d['jenis_usulan']) ?></td>
    <td><?= $d['rw'] ?></td>
    <td><?= $d['no_rt'] ?></td>
    <td><?= htmlspecialchars($d['permasalahan']) ?></td>
    <td><?= htmlspecialchars($d['perangkat_daerah']) ?></td>
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
new Chart(document.getElementById('chartRW'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($rw_label) ?>,
        datasets: [{
            label: 'Jumlah Usulan',
            data: <?= json_encode($rw_data) ?>
        }]
    }
});

new Chart(document.getElementById('chartOPD'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($opd_label) ?>,
        datasets: [{
            data: <?= json_encode($opd_data) ?>
        }]
    }
});
</script>

</body>
</html>
