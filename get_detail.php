<?php
include "koneksi.php";

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT definisi, satuan, harga
    FROM usulan_musrenbang
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
echo json_encode($result->fetch_assoc());
