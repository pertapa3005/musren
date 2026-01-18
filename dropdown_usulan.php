<?php
include "koneksi.php";

$sql = "SELECT id, uraian FROM usulan_musrenbang ORDER BY id ASC";
$result = $conn->query($sql);
?>

<select name="usulan_id" id="usulan_id" required>
    <option value="">-- Pilih Usulan --</option>
    <?php while ($row = $result->fetch_assoc()): ?>
        <option value="<?= $row['id']; ?>">
            <?= htmlspecialchars($row['uraian']); ?>
        </option>
    <?php endwhile; ?>
</select>
