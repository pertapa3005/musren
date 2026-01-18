<?php
include "koneksi.php";

$sql = "SELECT DISTINCT perangkat_daerah 
        FROM usulan_musrenbang
        WHERE perangkat_daerah IS NOT NULL
        ORDER BY perangkat_daerah ASC";

$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row["perangkat_daerah"];
}

echo json_encode($data);
