<?php
session_start();
if (!isset($_SESSION["login"]) || $_SESSION["login"] !== true) {
    header("Location: login.php");
    exit;
}
$rw_login = (int) $_SESSION["rw"];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Form Usulan</title>

<style>
body { font-family: Arial, sans-serif; }
.form-container { max-width: 900px; }
.form-group { display: flex; flex-direction: column; margin-bottom: 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 12px; }
label { font-weight: bold; margin-bottom: 4px; }
input, textarea, select {
    padding: 6px;
    font-size: 14px;
    width: 100%;
    box-sizing: border-box;
}
textarea { resize: vertical; }
.hidden { display: none; }
button { padding: 8px 16px; font-size: 14px; }
small { color: #555; }
</style>
</head>

<body>

<p>
    Login sebagai RW <b><?= htmlspecialchars($rw_login) ?></b> |
    <a href="logout.php">Logout</a>
</p>
<hr>

<form class="form-container"
      method="post"
      action="simpan_usulan.php"
      enctype="multipart/form-data">

    <!-- JENIS USULAN -->
    <div class="form-group">
        <label>Jenis Usulan</label>
        <select id="jenis_usulan" name="jenis_usulan" required>
            <option value="">-- Pilih Jenis Usulan --</option>
            <option value="rt">RT berkelas</option>
            <option value="musrenbang">Musrenbang</option>
        </select>
    </div>

    <!-- NO RT -->
    <div id="form_rt" class="form-group hidden">
        <label>No RT</label>
        <input type="number" id="no_rt" name="no_rt" min="1">
    </div>

    <!-- PERANGKAT DAERAH -->
    <div class="form-group">
        <label>Perangkat Daerah</label>
        <select id="perangkat" name="perangkat_daerah" disabled required>
            <option value="">-- Pilih Perangkat Daerah --</option>
        </select>
    </div>

    <!-- URAIAN -->
    <div class="form-group">
        <label>Uraian</label>
        <input type="text"
               id="uraian"
               list="list_uraian"
               name="uraian_text"
               placeholder="Ketik untuk mencari uraian"
               disabled
               autocomplete="off"
               required>
        <datalist id="list_uraian"></datalist>
    </div>

    <input type="hidden" id="usulan_id" name="usulan_id">

    <!-- DEFINISI -->
    <div class="form-group">
        <label>Definisi</label>
        <textarea id="definisi" rows="4" readonly></textarea>
    </div>

    <!-- SATUAN & HARGA -->
    <div class="form-row">
        <div class="form-group">
            <label>Satuan</label>
            <input type="text" id="satuan" readonly>
        </div>
        <div class="form-group">
            <label>Harga Satuan</label>
            <input type="text" id="harga" readonly>
        </div>
    </div>

    <!-- VOLUME & SUBTOTAL -->
    <div class="form-row">
        <div class="form-group">
            <label>Volume</label>
            <input type="number" id="volume" name="volume" min="1" required>
        </div>
        <div class="form-group">
            <label>Subtotal Biaya</label>
            <input type="text" id="subtotal" readonly>
        </div>
    </div>

    <!-- UPLOAD GAMBAR -->
    <div class="form-group">
        <label>Upload Gambar Kondisi Lapangan</label>
        <input type="file" name="gambar[]" accept="image/*" multiple>
        <small>Disarankan untuk usulan fisik</small>
    </div>

    <!-- PERMASALAHAN (BARU) -->
    <div class="form-group">
        <label>Permasalahan</label>
        <textarea name="permasalahan"
                  rows="4"
                  placeholder="Jelaskan permasalahan yang terjadi di lapangan"
                  required></textarea>
    </div>

    <!-- KELOMPOK SASARAN -->
    <div class="form-group">
        <label>Kelompok Sasaran</label>
        <textarea name="kelompok_sasaran" rows="3" required></textarea>
    </div>

    <!-- ALAMAT -->
    <div class="form-group">
        <label>Alamat Lokasi Kegiatan</label>
        <textarea name="alamat" rows="3" required></textarea>
    </div>

    <button type="submit">Simpan Usulan</button>
</form>

<script>
const jenis = document.getElementById("jenis_usulan");
const formRT = document.getElementById("form_rt");
const noRT = document.getElementById("no_rt");

const perangkat = document.getElementById("perangkat");
const uraianInput = document.getElementById("uraian");
const uraianList = document.getElementById("list_uraian");
const usulanId = document.getElementById("usulan_id");

const definisi = document.getElementById("definisi");
const satuan = document.getElementById("satuan");
const hargaInput = document.getElementById("harga");
const volumeInput = document.getElementById("volume");
const subtotalInput = document.getElementById("subtotal");

let hargaNumeric = 0;

// filter RT berkelas
const bannedRTKeywords = ["meja", "kursi", "tenda", "laptop"];
const bannedRTExact = "pengadaan motor sampah roda 3";

// load perangkat
function loadPerangkat() {
    fetch("get_perangkat.php")
        .then(r => r.json())
        .then(data => {
            perangkat.innerHTML = '<option value="">-- Pilih Perangkat Daerah --</option>';
            data.forEach(p => {
                const o = document.createElement("option");
                o.value = p;
                o.textContent = p;
                perangkat.appendChild(o);
            });
        });
}

// jenis usulan
jenis.addEventListener("change", () => {
    if (jenis.value === "rt") {
        formRT.classList.remove("hidden");
        noRT.required = true;
    } else {
        formRT.classList.add("hidden");
        noRT.required = false;
        noRT.value = "";
    }

    perangkat.disabled = false;
    perangkat.value = "";
    uraianInput.value = "";
    uraianInput.disabled = true;
    uraianList.innerHTML = "";

    definisi.value = "";
    satuan.value = "";
    hargaInput.value = "";
    volumeInput.value = "";
    subtotalInput.value = "";
    hargaNumeric = 0;

    loadPerangkat();
});

// perangkat daerah
perangkat.addEventListener("change", () => {
    uraianInput.disabled = true;
    uraianInput.value = "";
    uraianList.innerHTML = "";

    fetch("get_uraian.php?perangkat_daerah=" + encodeURIComponent(perangkat.value))
        .then(r => r.json())
        .then(data => {
            data.forEach(item => {
                const u = item.uraian.toLowerCase();

                if (jenis.value === "rt") {
                    if (bannedRTKeywords.some(k => u.includes(k))) return;
                    if (u === bannedRTExact) return;
                }

                const opt = document.createElement("option");
                opt.value = item.uraian;
                opt.dataset.id = item.id;
                uraianList.appendChild(opt);
            });
            uraianInput.disabled = false;
        });
});

// uraian
uraianInput.addEventListener("change", () => {
    usulanId.value = "";

    for (let o of uraianList.options) {
        if (o.value === uraianInput.value) {
            usulanId.value = o.dataset.id;
            break;
        }
    }
    if (!usulanId.value) return;

    fetch("get_detail.php?id=" + usulanId.value)
        .then(r => r.json())
        .then(d => {
            definisi.value = d.definisi || "";
            satuan.value = d.satuan || "";
            hargaNumeric = d.harga || 0;
            hargaInput.value = hargaNumeric
                ? "Rp " + new Intl.NumberFormat("id-ID").format(hargaNumeric)
                : "";
            volumeInput.value = "";
            subtotalInput.value = "";
        });
});

// subtotal
volumeInput.addEventListener("input", () => {
    const subtotal = hargaNumeric * (parseInt(volumeInput.value || 0));
    subtotalInput.value = subtotal
        ? "Rp " + new Intl.NumberFormat("id-ID").format(subtotal)
        : "";
});
</script>

</body>
</html>
