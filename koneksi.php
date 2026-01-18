<?php
$conn = new mysqli("localhost", "root", "", "musren");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>
