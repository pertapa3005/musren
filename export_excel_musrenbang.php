<?php
session_start();
ob_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/* ===============================
   VALIDASI SESSION
================================ */
if (!isset($_SESSION['rw'])) die('Akses ditolak');
$rw = (int) $_SESSION['rw'];

/* ===============================
   HELPER
================================ */
function isKelurahan($pd) {
    $pd = strtolower(trim((string)$pd));
    return (
        $pd === '' ||
        strpos($pd, 'kelurahan') !== false ||
        strpos($pd, 'kecamatan') !== false
    );
}

function setKolom($sheet) {
    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(25);
    $sheet->getColumnDimension('C')->setWidth(35);
    $sheet->getColumnDimension('D')->setWidth(8);
    $sheet->getColumnDimension('E')->setWidth(10);
    $sheet->getColumnDimension('F')->setWidth(18);
    $sheet->getColumnDimension('G')->setWidth(20);
    $sheet->getColumnDimension('H')->setWidth(20);
    $sheet->getColumnDimension('I')->setWidth(35);
    $sheet->getColumnDimension('J')->setWidth(30);
}

function borderAll($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ]);
}

/* ===============================
   AMBIL DATA
================================ */
$sql = "
SELECT u.*, g.nama_file, m.uraian, m.satuan, m.harga
FROM usulan_warga u
LEFT JOIN usulan_warga_gambar g ON g.usulan_warga_id = u.id
LEFT JOIN usulan_musrenbang m ON m.id = u.usulan_id
WHERE u.jenis_usulan='musrenbang' AND u.rw=?
ORDER BY u.id ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $rw);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) die('Tidak ada data');

/* ===============================
   SPREADSHEET
================================ */
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);
$sheetIndex = 0;

/* ===============================
   LOOP PER USULAN
================================ */
while ($r = $res->fetch_assoc()) {

    $sheet = $spreadsheet->createSheet($sheetIndex);
    $form25 = isKelurahan($r['perangkat_daerah']);

    $sheet->setTitle(substr(
        ($form25 ? 'Form 2.5' : 'Form 2.4') . ' - RW' . $rw . ' - ' . ($sheetIndex + 1),
        0, 31
    ));

    /* ===== JUDUL ===== */
    $judul = [
        $form25 ? 'FORM 2.5' : 'FORM 2.4',
        'DAFTAR USULAN KEGIATAN PEMBANGUNAN PRIORITAS KELURAHAN TAHUN 2027',
        'DENGAN SUMBER PENDANAAN APBD ' . ($form25 ? 'DI KECAMATAN-KELURAHAN' : 'PERANGKAT DAERAH'),
        '(MUSRENBANG 2026)'
    ];

    $row = 1;
    foreach ($judul as $j) {
        $sheet->mergeCells("A{$row}:J{$row}");
        $sheet->setCellValue("A{$row}", $j);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
    }

    /* ===== IDENTITAS ===== */
    $row += 1;
    $sheet->setCellValue("A{$row}", 'Kelurahan : Pandanwangi'); $row++;
    $sheet->setCellValue("A{$row}", 'Kecamatan : Blimbing'); $row++;
    $sheet->setCellValue("A{$row}", 'Kota      : Kota Malang'); $row++;
    $row++;
    if (!$form25) {
    	$sheet->setCellValue(
        	"A{$row}",
        	'PERANGKAT DAERAH TUJUAN USULAN : ' . $r['perangkat_daerah']
    	);
    	$sheet->getStyle("A{$row}")->getFont()->setBold(true);
    }

    /* $sheet->setCellValue(
        "A{$row}",
        'PERANGKAT DAERAH TUJUAN USULAN : ' .
        ($form25 ? 'KECAMATAN-KELURAHAN' : $r['perangkat_daerah'])
    );
    $sheet->getStyle("A{$row}")->getFont()->setBold(true); */

    /* ===== HEADER TABEL ===== */
    $row += 2;
    $startTable = $row;

    $sheet->fromArray([
        'No','Permasalahan','Uraian Usulan','Volume','Satuan',
        'Harga Satuan','Subtotal Biaya','Kelompok Sasaran','Alamat / Lokasi','Foto'
    ], null, "A{$row}");

    $row++;
    $sheet->fromArray(['(1)','(2)','(3)','(4)','(5)','(6)','(7)','(8)','(9)','(10)'], null, "A{$row}");

    setKolom($sheet);
    $sheet->getStyle("A{$startTable}:J{$row}")->getFont()->setBold(true);
    $sheet->getStyle("A{$startTable}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    /* ===== ISI DATA (1 BARIS) ===== */
    $row++;
    $sheet->setCellValue("A{$row}", 1);
    $sheet->setCellValue("B{$row}", $r['permasalahan']);
    $sheet->setCellValue("C{$row}", $r['uraian']);
    $sheet->setCellValue("D{$row}", $r['volume']);
    $sheet->setCellValue("E{$row}", $r['satuan']);
    $sheet->setCellValue("F{$row}", $r['harga']);
    $sheet->setCellValue("G{$row}", $r['subtotal']);
    $sheet->setCellValue("H{$row}", $r['kelompok_sasaran']);
    $sheet->setCellValue("I{$row}", $r['alamat']);

    $sheet->getStyle("F{$row}:G{$row}")
          ->getNumberFormat()->setFormatCode('#,##0');

    if (!empty($r['nama_file'])) {
        $path = __DIR__ . '/uploads/' . $r['nama_file'];
        if (file_exists($path)) {
            $img = new Drawing();
            $img->setPath($path);
            $img->setHeight(80);
            $img->setCoordinates("J{$row}");
            $img->setWorksheet($sheet);
            $sheet->getRowDimension($row)->setRowHeight(90);
        }
    }

    borderAll($sheet, "A{$startTable}:J{$row}");

    /* ===== TTD ===== */
    $row += 3;
    $sheet->setCellValue("H{$row}", 'Mengetahui,');
    $sheet->setCellValue("H" . ($row+1), 'Lurah Pandanwangi');

    $row += 5;
    $sheet->setCellValue("H{$row}", 'FAUZAN INDRA SAPUTRA, S.STP., M.Si');
    $sheet->getStyle("H{$row}")->getFont()->setBold(true)->setUnderline(true);
    $sheet->setCellValue("H" . ($row+1), 'Pembina');
    $sheet->setCellValue("H" . ($row+2), 'NIP : 19820225 200012 1001');

    $sheetIndex++;
}

/* ===============================
   OUTPUT
================================ */
if (ob_get_length()) ob_end_clean();

$filename = 'Musrenbang_RW_' . $rw . '_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
