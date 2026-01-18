<?php
include "koneksi.php";

$perangkat = $_GET['perangkat_daerah'] ?? '';

$stmt = $conn->prepare("
    SELECT id, uraian
    FROM usulan_musrenbang
    WHERE perangkat_daerah = ?
    ORDER BY id ASC
");
$stmt->bind_param("s", $perangkat);
$stmt->execute();

$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
