<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}
$rw = (int) $_SESSION['rw'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Preview Cetak Usulan</title>
<style>
body { font-family: Arial, sans-serif; background:#f3f4f6; }
.box {
    max-width: 700px;
    margin: 60px auto;
    background:#fff;
    padding: 24px;
    border-radius: 8px;
}
.form-group { margin-bottom: 14px; }
label { font-weight:bold; display:block; margin-bottom:6px; }
select { width:100%; padding:8px; }
button {
    padding:10px 16px;
    font-weight:bold;
    border:none;
    border-radius:4px;
    cursor:pointer;
}
.btn-primary { background:#2563eb; color:#fff; }
</style>
</head>
<body>

<div class="box">
<h2>Preview & Cetak Usulan</h2>
<p>Login sebagai <b>RW <?= $rw ?></b></p>

<form method="get" id="formCetak">

    <div class="form-group">
        <label>Jenis Usulan</label>
        <select name="jenis" id="jenis" required>
            <option value="">-- Pilih --</option>
            <option value="rt">RT Berkelas</option>
            <option value="musrenbang">Musrenbang</option>
        </select>
    </div>

    <div class="form-group" id="form_rt" style="display:none;">
        <label>Pilih RT</label>
        <select name="rt">
            <?php for ($i=1;$i<=21;$i++): ?>
                <option value="<?= $i ?>">RT <?= $i ?></option>
            <?php endfor; ?>
        </select>
    </div>

    <button type="submit" class="btn-primary">
        Lanjutkan
    </button>
</form>

<script>
const jenis   = document.getElementById('jenis');
const formRT  = document.getElementById('form_rt');
const form    = document.getElementById('formCetak');

jenis.addEventListener('change', () => {
    if (jenis.value === 'rt') {
        formRT.style.display = 'block';
        form.action = 'export_excel.php';
        form.target = '_blank'; // langsung download
    } else if (jenis.value === 'musrenbang') {
        formRT.style.display = 'none';
        form.action = 'preview_musrenbang.php';
        form.target = '_self'; // ke halaman preview
    }
});
</script>

</body>
</html>
