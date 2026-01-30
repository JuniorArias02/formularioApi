<?php
require_once '../vendor/autoload.php';
require_once '../middlewares/cors.php';
require_once '../database/conexion.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// ======================= PARÁMETROS =======================
$idEntrega = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$formato = strtolower($_GET['formato'] ?? $_POST['formato'] ?? 'excel'); // 'excel' o 'pdf'

if ($idEntrega <= 0) {
	die("ID de entrega inválido");
}

// Validar formato
if (!in_array($formato, ['excel', 'pdf'])) {
	$formato = 'excel';
}

// ======================= CARGAR PLANTILLA =======================
$templatePath = __DIR__ . "/../public/plantilla_entrega_activos_fijos.xlsx";
$spreadsheet = IOFactory::load($templatePath);
$sheet = $spreadsheet->getActiveSheet();

// ======================= CONSULTA ENTREGA =======================
$sqlEntrega = "SELECT 
    ef.fecha_entrega,
    ef.firma_quien_entrega,
    ef.firma_quien_recibe,
    s.nombre AS sede,
    p.nombre AS personal,
    p.cedula,
    c.nombre AS cargo,
    dep.nombre AS dependencia,
    coord.nombre AS coordinador
FROM cp_entrega_activos_fijos ef
LEFT JOIN personal p ON p.id = ef.personal_id
LEFT JOIN sedes s ON s.id = ef.sede_id
LEFT JOIN p_cargo c ON c.id = p.cargo_id
LEFT JOIN dependencias_sedes dep ON dep.id = ef.proceso_solicitante
LEFT JOIN personal coord ON coord.id = ef.coordinador_id
WHERE ef.id = :id";

$stmt = $pdo->prepare($sqlEntrega);
$stmt->execute([':id' => $idEntrega]);
$entrega = $stmt->fetch(PDO::FETCH_ASSOC);

// ======================= CONSULTA ITEMS =======================
$sqlItems = "SELECT 
    efi.es_accesorio,
    efi.accesorio_descripcion,
    i.nombre,
    i.marca,
	i.modelo,
    i.serial,
    i.codigo,
    i.proveedor,
    i.soporte,
	i.observaciones,
	i.num_factu,
	i.estado,
	i.descripcion_accesorio
FROM cp_entrega_activos_fijos_items efi
LEFT JOIN inventario i ON i.id = efi.item_id
WHERE efi.entrega_activos_id = :id";

$stmt2 = $pdo->prepare($sqlItems);
$stmt2->execute([':id' => $idEntrega]);
$items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// ======================= DATOS CABECERA =======================
if ($entrega) {
	$fecha = new DateTime($entrega['fecha_entrega']);

	$sheet->setCellValue("B8", $fecha->format("d"));
	$sheet->setCellValue("C8", $fecha->format("m"));
	$sheet->setCellValue("D8", $fecha->format("Y"));
	$sheet->setCellValue("O6", $entrega['coordinador']);
	$sheet->setCellValue("O8", $entrega['sede']);
	$sheet->setCellValue("H6", $entrega['personal']);
	$sheet->setCellValue("H7", $entrega['cedula']);
	$sheet->setCellValue("H8", $entrega['cargo']);
	$sheet->setCellValue("O7", $entrega['dependencia']);
	insertarFirma($sheet, $entrega['firma_quien_entrega'], "H20", 5, 10);
	insertarFirma($sheet, $entrega['firma_quien_recibe'], "S20", -10, 10);
}

// ======================= ITEMS DINÁMICOS =======================
$startRow = 14;
$filasPlantilla = 5;
$totalItems = count($items);

if ($totalItems > $filasPlantilla) {
	$filasExtra = $totalItems - $filasPlantilla;

	// Inserta filas
	$sheet->insertNewRowBefore($startRow + $filasPlantilla, $filasExtra);

	// Reaplica merges SOLO a filas nuevas
	for ($r = $startRow + $filasPlantilla; $r < $startRow + $totalItems; $r++) {
		$sheet->mergeCells("B{$r}:D{$r}");
		$sheet->mergeCells("E{$r}:F{$r}");
	}
}

foreach ($items as $i => $item) {
	$row = $startRow + $i;

	// ⚠️ NO merge aquí
	$sheet->getRowDimension($row)->setVisible(true);

	$sheet->setCellValue("B{$row}", $item['nombre']);
	$sheet->setCellValue("E{$row}", $item['proveedor']);
	$sheet->setCellValue("G{$row}", $item['num_factu']);
	$sheet->setCellValue("H{$row}", $item['marca']);
	$sheet->setCellValue("I{$row}", $item['modelo']);
	$sheet->setCellValue("J{$row}", $item['serial']);
	$sheet->setCellValue("K{$row}", $item['codigo']);

	$prefijo = preg_replace('/[^A-Z]/i', '', $item['codigo']);
	foreach (["EB" => "L", "MAQ" => "M", "ME" => "N", "EC" => "O", "MC" => "P"] as $key => $col) {
		if ($prefijo === $key) {
			$sheet->setCellValue("{$col}{$row}", "X");
		}
	}

	$sheet->setCellValue($item['es_accesorio'] ? "R{$row}" : "S{$row}", "X");
	$sheet->setCellValue("Q{$row}", $item['estado']);
	$sheet->setCellValue("T{$row}", $item['descripcion_accesorio']);
	$sheet->setCellValue("U{$row}", $item['observaciones']);
	$sheet->getStyle("B{$row}:T{$row}")
		->getAlignment()
		->setHorizontal(Alignment::HORIZONTAL_CENTER)
		->setVertical(Alignment::VERTICAL_CENTER);
}

// ======================= GENERAR ARCHIVO SEGÚN FORMATO =======================
if ($formato === 'pdf') {
	// Configurar para PDF
	// Asegúrate de tener instalado: composer require mpdf/mpdf
	\PhpOffice\PhpSpreadsheet\IOFactory::registerWriter('Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class);
	
	// Configurar orientación y tamaño de página
	$sheet->getPageSetup()
		->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
		->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
	
	// Ajustar márgenes
	$sheet->getPageMargins()
		->setTop(0.5)
		->setRight(0.5)
		->setLeft(0.5)
		->setBottom(0.5);
	
	// Configurar área de impresión si es necesario
	// $sheet->getPageSetup()->setPrintArea('A1:U30');
	
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment;filename="entrega_activos_fijos.pdf"');
	header('Cache-Control: max-age=0');
	
	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($spreadsheet);
	$writer->save("php://output");
} else {
	// Generar Excel (comportamiento original)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="entrega_activos_fijos.xlsx"');
	header('Cache-Control: max-age=0');
	
	$writer = new Xlsx($spreadsheet);
	$writer->save("php://output");
}

exit;

// ======================= FIRMA =======================
function insertarFirma($sheet, $rutaFirma, $celda, $offsetX = 250, $offsetY = 15)
{
	if (empty($rutaFirma)) return;

	$fullPath = __DIR__ . "/../" . ltrim($rutaFirma, '/');
	if (!file_exists($fullPath)) return;

	preg_match('/([A-Z]+)([0-9]+)/', $celda, $m);
	$row = (int)$m[2];

	$sheet->getRowDimension($row)->setRowHeight(56);

	$drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
	$drawing->setPath($fullPath);
	$drawing->setCoordinates($celda);
	$drawing->setResizeProportional(false);
	$drawing->setWidth(210);
	$drawing->setHeight(60);
	$drawing->setOffsetX($offsetX);
	$drawing->setOffsetY($offsetY);
	$drawing->setWorksheet($sheet);
}