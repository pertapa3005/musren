<?php
session_start();

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/* ======================================================
   VALIDASI LOGIN
====================================================== */
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['rw'])) {
    die("Akses ditolak");
}

$rw = (int) $_SESSION['rw'];
$rt = isset($_GET['rt']) ? (int) $_GET['rt'] : 0;
if ($rt <= 0) die("RT tidak valid");

/* ======================================================
   KONEKSI DATABASE
====================================================== */
$conn = new mysqli("localhost", "root", "", "musren");
if ($conn->connect_error) die("Koneksi database gagal");

/* ======================================================
   HELPER
====================================================== */
function borderAll($sheet, $range)
{
    $sheet->getStyle($range)
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);
}

/* ======================================================
   QUERY DATA
====================================================== */
$sql = "
SELECT
    uw.id,
    uw.permasalahan,
    uw.alamat,
    uw.volume,
    uw.subtotal,
    uw.perangkat_daerah,
    um.uraian,
    um.satuan,
    um.harga,
    CONCAT('uploads/', ug.nama_file) AS gambar
FROM usulan_warga uw
JOIN usulan_musrenbang um ON um.id = uw.usulan_id
LEFT JOIN usulan_warga_gambar ug ON ug.usulan_warga_id = uw.id
WHERE uw.jenis_usulan = 'rt'
AND uw.rw = ?
AND uw.no_rt = ?
ORDER BY uw.id ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $rw, $rt);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) die("Tidak ada data usulan");

/* ======================================================
   SIAPKAN DATA & PERANGKAT DAERAH
====================================================== */
$data = [];
$pd = [];

while ($r = $res->fetch_assoc()) {
    if (!empty($r['perangkat_daerah'])) {
        $pd[] = $r['perangkat_daerah'];
    }
    $data[] = $r;
}

$perangkat_daerah_text = implode(", ", array_unique($pd));

/* ======================================================
   SPREADSHEET
====================================================== */
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

/* ======================================================
   FORM 1.3
====================================================== */
$sheet = $spreadsheet->createSheet();
$sheet->setTitle("FORM 1.3");

/* JUDUL */
$sheet->getStyle("A1")->getFont()->setBold(true);
$sheet->setCellValue(
    "A1",
    "Form 1.3"
);
$sheet->mergeCells("A2:J2");
$sheet->setCellValue(
    "A2",
    "DAFTAR USULAN KEGIATAN PEMBANGUNAN PRIORITAS KELURAHAN TAHUN 2027\nPROGRAM RT BERKELAS"
);
$sheet->getStyle("A1")->getFont()->setBold(true)->setSize(12);
$sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
$sheet->getRowDimension(1)->setRowHeight(45);

/* IDENTITAS */
$sheet->setCellValue("A3", "RT : ".sprintf("%02d",$rt));
$sheet->setCellValue("C3", "RW : ".sprintf("%02d",$rw));
$sheet->setCellValue("A4", "Kelurahan : Pandanwangi");
$sheet->setCellValue("A5", "Kecamatan : Blimbing");
$sheet->setCellValue("A6", "Kota : Kota Malang");

/* PERANGKAT DAERAH (DETAIL PENTING) */
$sheet->mergeCells("A8:J8");
$sheet->setCellValue("A8", "PERANGKAT DAERAH TUJUAN USULAN : ".$perangkat_daerah_text);
$sheet->getStyle("A8")->getFont()->setBold(true);
$sheet->getStyle("A8")->getAlignment()->setWrapText(true);

/* HEADER */
$h = 9;
$sheet->fromArray([
 "No\n(Prioritas)","Permasalahan","Uraian Usulan","Volume","Satuan",
 "Harga Persatuan","Subtotal Biaya","Kelompok Sasaran",
 "Alamat Lengkap/Lokasi","Keterangan"
], null, "A$h");

$sheet->fromArray(
 ["(1)","(2)","(3)","(4)","(5)","(6)","(7)","(8)","(9)","(10)"],
 null, "A".($h+1)
);

$sheet->getStyle("A$h:J".($h+1))->getFont()->setBold(true);
$sheet->getStyle("A$h:J".($h+1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

/* DATA */
$row = $h + 2;
$no = 1;
$total = 0;

foreach ($data as $r) {

    $sheet->fromArray([
        $no,
        "#RT_P{$no}\n".$r['permasalahan'],
        $r['uraian'],
        $r['volume'],
        $r['satuan'],
        $r['harga'],
        $r['subtotal'],
        "Warga RT ".sprintf("%02d",$rt),
        $r['alamat'],
        ""
    ], null, "A$row");

    if (!empty($r['gambar']) && file_exists($r['gambar'])) {
        $img = new Drawing();
        $img->setPath($r['gambar']);
        $img->setHeight(80);
        $img->setCoordinates("J$row");
        $img->setWorksheet($sheet);
        $sheet->getRowDimension($row)->setRowHeight(90);
    }

    $total += $r['subtotal'];
    $row++; $no++;
}

$sheet->getStyle("F".($h+2).":G".($row-1))
    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

borderAll($sheet, "A$h:J".($row-1));

/* TOTAL */
$sheet->mergeCells("A$row:F$row");
$sheet->setCellValue("A$row", "Total Biaya");
$sheet->setCellValue("G$row", $total);
$sheet->getStyle("A$row:G$row")->getFont()->setBold(true);
borderAll($sheet, "A$row:G$row");

/* TANDA TANGAN FORM 1.3 */
$ttd = $row + 3;
$sheet->setCellValue("C$ttd", "Ketua RT ".sprintf("%02d",$rt));
$sheet->setCellValue("G$ttd", "Sekretaris RT ".sprintf("%02d",$rt));
$sheet->setCellValue("C".($ttd+4), "(....................)");
$sheet->setCellValue("G".($ttd+4), "(....................)");
$sheet->setCellValue("E".($ttd+6), "Mengetahui:");
$sheet->setCellValue("E".($ttd+7), "Ketua RW ".sprintf("%02d",$rw));
$sheet->setCellValue("E".($ttd+11), "(....................)");

/* ======================================================
   FORM 1.4
====================================================== */
$sheetR = $spreadsheet->createSheet();
$sheetR->setTitle("FORM 1.4");

$sheetR->mergeCells("A1:K1");
$sheetR->setCellValue("A1", "REKAPITULASI DAFTAR USULAN PROGRAM RT BERKELAS");
$sheetR->getStyle("A1")->getFont()->setBold(true);
$sheetR->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheetR->setCellValue("A3", "RT : ".sprintf("%02d",$rt));
$sheetR->setCellValue("C3", "RW : ".sprintf("%02d",$rw));
$sheetR->setCellValue("A4", "Kelurahan : Pandanwangi");
$sheetR->setCellValue("A5", "Kecamatan : Blimbing");
$sheetR->setCellValue("A6", "Kota : Kota Malang");

/* HEADER */
$h = 9;
$sheetR->fromArray([
 "No","Permasalahan","Uraian Usulan","Volume","Satuan",
 "Harga Persatuan","Subtotal Biaya","Kelompok Sasaran",
 "Alamat Lengkap/Lokasi","Perangkat Daerah Pengampu","Keterangan"
], null, "A$h");

$sheetR->fromArray(
 ["(1)","(2)","(3)","(4)","(5)","(6)","(7)","(8)","(9)","(10)","(11)"],
 null, "A".($h+1)
);

$sheetR->getStyle("A$h:K".($h+1))->getFont()->setBold(true);
$sheetR->getStyle("A$h:K".($h+1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

/* DATA */
$row = $h + 2;
$no = 1;
$grand = 0;

foreach ($data as $r) {

    $sheetR->fromArray([
        $no,
        "#RT_P{$no}\n".$r['permasalahan'],
        $r['uraian'],
        $r['volume'],
        $r['satuan'],
        $r['harga'],
        $r['subtotal'],
        "Warga RT ".sprintf("%02d",$rt),
        $r['alamat'],
        $r['perangkat_daerah'],
        ""
    ], null, "A$row");

    if (!empty($r['gambar']) && file_exists($r['gambar'])) {
        $img = new Drawing();
        $img->setPath($r['gambar']);
        $img->setHeight(80);
        $img->setCoordinates("K$row");
        $img->setWorksheet($sheetR);
        $sheetR->getRowDimension($row)->setRowHeight(90);
    }

    $grand += $r['subtotal'];
    $row++; $no++;
}

$sheetR->getStyle("F".($h+2).":G".($row-1))
    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

borderAll($sheetR, "A$h:K".($row-1));

/* TOTAL */
$sheetR->mergeCells("A$row:F$row");
$sheetR->setCellValue("A$row", "Total Biaya");
$sheetR->setCellValue("G$row", $grand);
$sheetR->getStyle("A$row:G$row")->getFont()->setBold(true);
borderAll($sheetR, "A$row:G$row");

/* TANDA TANGAN FORM 1.4 */
$ttd = $row + 3;
$sheetR->setCellValue("B$ttd", "Ketua RT ".sprintf("%02d",$rt));
$sheetR->setCellValue("D$ttd", "Sekretaris RT ".sprintf("%02d",$rt));
$sheetR->setCellValue("B".($ttd+4), "(....................)");
$sheetR->setCellValue("D".($ttd+4), "(....................)");

$sheetR->setCellValue("G$ttd", "Malang, ".date('d F Y'));
$sheetR->setCellValue("G".($ttd+1), "Mengetahui dan menyetujui:");
$sheetR->setCellValue("G".($ttd+2), "Nama          Alamat          Tanda Tangan");

for ($i = 1; $i <= 5; $i++) {
    $sheetR->setCellValue(
        "G".($ttd+2+$i),
        "$i  ................................  ...............................  ..............................."
    );
}

$sheetR->setCellValue("B".($ttd+8), "Mengetahui:");
$sheetR->setCellValue("B".($ttd+9), "Ketua RW ".sprintf("%02d",$rw));
$sheetR->setCellValue("B".($ttd+13), "(....................)");

/* ======================================================
   SIMPAN FILE
====================================================== */
$filename = "RT_Berkelas_RW{$rw}_RT{$rt}.xlsx";
$writer = new Xlsx($spreadsheet);
$writer->save($filename);

$conn->close();

echo "<h3>Export Berhasil</h3><a href='$filename'>⬇ Download Excel</a>";
