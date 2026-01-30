<?php
require_once '../vendor/autoload.php';
require_once '../database/conexion.php';
require_once '../middlewares/cors.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;  // 👈 AGREGAR ESTA LÍNEA
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

// --- Configuración base ---
define("BASE_PATH", __DIR__ . "/../");

/* ==========================================================
   FUNCIONES
========================================================== */

// 1. --- DB ---
function getPedido($pdo, $idPedido)
{
	$sql = "
        SELECT 
            p.fecha_solicitud AS fecha,
            p.consecutivo,
            dp.nombre AS proceso_solicitante,
            ts.nombre AS tipo_solicitud,
            s.nombre AS sede,
            p.observacion AS observaciones,
            p.fecha_compra,
            p.fecha_gerencia,
            u_elab.nombre_completo AS elaborado_nombre,
            r_elab.nombre AS elaborado_cargo,
            p.elaborado_por_firma AS elaborado_firma,
            u_compra.nombre_completo AS proceso_compra_nombre,
            r_compra.nombre AS proceso_compra_cargo,
            p.proceso_compra_firma AS proceso_compra_firma,
            p.fecha_solicitud AS proceso_compra_fecha,
            u_resp.nombre_completo AS responsable_nombre,
            r_resp.nombre AS responsable_cargo,
            p.responsable_aprobacion_firma AS responsable_firma,
            p.fecha_solicitud AS responsable_fecha
        FROM cp_pedidos p
        INNER JOIN cp_tipo_solicitud ts ON ts.id = p.tipo_solicitud
        LEFT JOIN usuarios u_elab ON u_elab.id = p.elaborado_por
        LEFT JOIN rol r_elab ON r_elab.id = u_elab.rol_id
        LEFT JOIN usuarios u_compra ON u_compra.id = p.proceso_compra
        LEFT JOIN rol r_compra ON r_compra.id = u_compra.rol_id
        LEFT JOIN usuarios u_resp ON u_resp.id = p.responsable_aprobacion
        LEFT JOIN rol r_resp ON r_resp.id = u_resp.rol_id
        LEFT JOIN sedes s ON s.id = p.sede_id
        LEFT JOIN dependencias_sedes dp ON dp.id = p.proceso_solicitante
        WHERE p.id = :id;
    ";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([':id' => $idPedido]);
	return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getItems($pdo, $idPedido)
{
	$sql = "
        SELECT 
            i.nombre,
            i.cantidad,
			i.unidad_medida,
            i.referencia_items AS referencia,
            i.productos_id,
            p.codigo AS codigo_producto
        FROM cp_items_pedidos i
        LEFT JOIN cp_productos p ON p.id = i.productos_id
        WHERE i.cp_pedido = :id
    ";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([':id' => $idPedido]);
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// 2. --- Excel helpers ---
function llenarEncabezado($sheet, $pedido, $extra)
{
	// Fecha
	$date = ExcelDate::PHPToExcel(new DateTime($pedido['fecha']));
	$sheet->setCellValue("E6", $date);
	$sheet->getStyle("E6")
		->getNumberFormat()
		->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

	// Otros datos
	$sheet->setCellValue("E7", $pedido['proceso_solicitante']);
	$sheet->setCellValue("I6", $pedido['consecutivo']);
	$sheet->setCellValue("I7", $pedido['sede']);

	// Marcar tipo de solicitud
	if ($pedido['tipo_solicitud'] === "Prioritaria") {
		$sheet->setCellValue("J9", "X");
	} elseif ($pedido['tipo_solicitud'] === "Recurrente") {
		$sheet->setCellValue("G9", "X");
	}

	// === Observaciones ===
	$obsRow  = 26 + $extra;
	$obsCell = "B{$obsRow}";

	$sheet->getStyle($obsCell)
		->getAlignment()
		->setWrapText(true)
		->setVertical(Alignment::VERTICAL_TOP);

	$textoObs = !empty($sheet->getCell($obsCell)->getValue())
		? $sheet->getCell($obsCell)->getValue() . ' ' . $pedido['observaciones']
		: $pedido['observaciones'];

	$sheet->setCellValue($obsCell, $textoObs);

	$anchoAprox = 50;
	$alturaLinea = 15;
	$lineas = ceil(strlen($textoObs) / $anchoAprox);
	if ($lineas < 1) $lineas = 1;

	$altura = $lineas * $alturaLinea;

	// Establecer altura calculada
	$sheet->getRowDimension($obsRow)->setRowHeight($altura);
}



function llenarItems($sheet, $items, $startRow = 13)
{
	foreach (['B', 'C', 'I', 'J'] as $col) {
		$sheet->getColumnDimension($col)->setAutoSize(true);
	}

	$count = 0;
	foreach ($items as $i => $item) {
		$row = $startRow + $i;
		$count++;

		$cellValue = !empty($item['codigo_producto']) ? $item['codigo_producto'] : $count;

		$sheet->setCellValue("B{$row}", $cellValue);
		$sheet->setCellValue("C{$row}", $item['nombre']);
		$sheet->setCellValue("I{$row}", $item['unidad_medida']);
		$sheet->setCellValue("J{$row}", $item['cantidad']);

		// 🪄 Ajustes visuales
		$sheet->getRowDimension($row)->setRowHeight(-1);
		$sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
	}
}

function responsableProceso($sheet, $pedidos, $offset = 0)
{
	$formatFecha = function ($fecha) {
		if (empty($fecha)) return "";
		return (new DateTime($fecha))->format("d/m/Y");
	};

	$concat = function ($cell, $value) use ($sheet) {
		if (!empty($value)) {
			$current = $sheet->getCell($cell)->getValue();
			if (!empty($current)) {
				$sheet->setCellValue($cell, $current . " " . $value);
			} else {
				$sheet->setCellValue($cell, $value);
			}
		}
	};

	// Ajuste con offset en filas
	$concat("B" . (42 + $offset), "" . $formatFecha($pedidos['fecha_compra']));
	$concat("G" . (42 + $offset), "" . $formatFecha($pedidos['fecha_gerencia']));
	$concat("B" . (33 + $offset), $pedidos['elaborado_nombre']);
	$concat("B" . (34 + $offset), $pedidos['elaborado_cargo']);
	$concat("B" . (40 + $offset), $pedidos['proceso_compra_nombre']);
	$concat("B" . (41 + $offset), $pedidos['proceso_compra_cargo']);
	$concat("G" . (40 + $offset), $pedidos['responsable_nombre']);
	$concat("G" . (41 + $offset), $pedidos['responsable_cargo']);
}

function insertarFirma($sheet, $rutaFirma, $celda)
{
	if (empty($rutaFirma)) return;

	$fullPath = BASE_PATH . $rutaFirma;
	if (!file_exists($fullPath)) return;

	$drawing = new Drawing();
	$drawing->setPath($fullPath);
	$drawing->setCoordinates($celda);
	$drawing->setHeight(75);
	$drawing->setResizeProportional(true);

	$drawing->setOffsetX(65);
	$drawing->setOffsetY(17);
	$drawing->setWorksheet($sheet);

	preg_match('/([A-Z]+)([0-9]+)/', $celda, $matches);
	$row = (int)$matches[2];
	$sheet->getRowDimension($row)->setRowHeight(67);
}

function insertarFirmas($sheet, $pedido, $offset = 0)
{
	insertarFirma($sheet, $pedido['elaborado_firma'], "B" . (31 + $offset));
	insertarFirma($sheet, $pedido['proceso_compra_firma'], "B" . (38 + $offset));
	insertarFirma($sheet, $pedido['responsable_firma'], "G" . (38 + $offset));
}

/* ==========================================================
   CONTROLADOR PRINCIPAL
========================================================== */
$data = json_decode(file_get_contents("php://input"), true);
$idPedido = isset($data['id']) ? intval($data['id']) : 0;
$formato = isset($data['formato']) ? strtolower($data['formato']) : 'excel'; // 👈 AGREGAR ESTA LÍNEA

// 👇 AGREGAR VALIDACIÓN
if (!in_array($formato, ['excel', 'pdf'])) {
	$formato = 'excel';
}

if ($idPedido <= 0) {
	http_response_code(400);
	echo json_encode(["error" => "ID de pedido inválido"]);
	exit;
}

$pedido = getPedido($pdo, $idPedido);
if (!$pedido) {
	http_response_code(404);
	echo json_encode(["error" => "No se encontró el pedido"]);
	exit;
}

$items = getItems($pdo, $idPedido);

// Plantilla
$templatePath = __DIR__ . "/../public/plantilla_pedidos.xlsx";
$spreadsheet = IOFactory::load($templatePath);
$sheet = $spreadsheet->getActiveSheet();


/* =======================
   Ajuste dinámico de filas
======================= */

// Detectar si hay más de 12 ítems
$extra = max(0, count($items) - 12);

// Si hay extra, insertamos filas debajo de la tabla de ítems
if ($extra > 0) {
	$insertStart = 25; // fila donde empiezan firmas
	$sheet->insertNewRowBefore($insertStart, $extra);

	//Combinar y centrar las celdas de las nuevas filas
	$startRow = 13 + 12;
	$endRow = $startRow + $extra - 1;

	for ($row = $startRow; $row <= $endRow; $row++) {
		// Combinar celdas para el nombre
		$sheet->mergeCells("C{$row}:H{$row}");
		$sheet->mergeCells("J{$row}:K{$row}");

		// Centrar texto
		$sheet->getStyle("C{$row}:H{$row}")
			->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
			->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

		$sheet->getStyle("J{$row}:K{$row}")
			->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
			->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
	}
}

// Llenar datos
llenarEncabezado($sheet, $pedido, $extra);
llenarItems($sheet, $items);
insertarFirmas($sheet, $pedido, $extra);
responsableProceso($sheet, $pedido, $extra);

// Nombre del archivo
$proceso = preg_replace('/[^A-Za-z0-9_\-]/', '_', $pedido['proceso_solicitante']);
$sede    = preg_replace('/[^A-Za-z0-9_\-]/', '_', $pedido['sede']);
$consecutivo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $pedido['consecutivo']);

/* ==========================================================
   EXPORTAR SEGÚN FORMATO
========================================================== */
// 👇 REEMPLAZAR TODO DESDE AQUÍ HASTA EL exit;

if ($formato === 'pdf') {
	// ===== GENERAR PDF =====
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
	
	$filename = "PEDIDO_{$proceso}_{$sede}_{$consecutivo}.pdf";
	
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment;filename="' . $filename . '"');
	header('Access-Control-Expose-Headers: Content-Disposition');
	header('Cache-Control: max-age=0');
	
	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($spreadsheet);
	$writer->save("php://output");
} else {
	// ===== GENERAR EXCEL =====
	$filename = "PEDIDO_{$proceso}_{$sede}_{$consecutivo}.xlsx";
	
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="' . $filename . '"');
	header('Access-Control-Expose-Headers: Content-Disposition');
	header('Cache-Control: max-age=0');
	
	$writer = new Xlsx($spreadsheet);
	$writer->save("php://output");
}

exit;