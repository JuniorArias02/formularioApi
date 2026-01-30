<?php
// Test completo con datos reales de un pedido
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../database/conexion.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

define("BASE_PATH", __DIR__ . "/../");

echo "=== TEST CON DATOS REALES ===\n\n";

// 1. Obtener un pedido de la base de datos
echo "1. Consultando pedidos en la base de datos...\n";
try {
    $sql = "SELECT id, consecutivo FROM cp_pedidos LIMIT 1";
    $stmt = $pdo->query($sql);
    $pedidoInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pedidoInfo) {
        echo "   ✓ Pedido encontrado: ID={$pedidoInfo['id']}, Consecutivo={$pedidoInfo['consecutivo']}\n\n";
        $idPedido = $pedidoInfo['id'];
    } else {
        echo "   ✗ No hay pedidos en la base de datos\n";
        exit;
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    exit;
}

// 2. Obtener datos completos del pedido
echo "2. Obteniendo datos del pedido...\n";
$sql = "
    SELECT 
        p.fecha_solicitud AS fecha,
        p.consecutivo,
        dp.nombre AS proceso_solicitante,
        ts.nombre AS tipo_solicitud,
        s.nombre AS sede,
        p.observacion AS observaciones
    FROM cp_pedidos p
    INNER JOIN cp_tipo_solicitud ts ON ts.id = p.tipo_solicitud
    LEFT JOIN sedes s ON s.id = p.sede_id
    LEFT JOIN dependencias_sedes dp ON dp.id = p.proceso_solicitante
    WHERE p.id = :id;
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $idPedido]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pedido) {
    echo "   ✓ Datos obtenidos\n";
    echo "   - Consecutivo: {$pedido['consecutivo']}\n";
    echo "   - Proceso: {$pedido['proceso_solicitante']}\n";
    echo "   - Sede: {$pedido['sede']}\n\n";
} else {
    echo "   ✗ No se pudieron obtener los datos\n";
    exit;
}

// 3. Obtener items
echo "3. Obteniendo items del pedido...\n";
$sql = "
    SELECT 
        i.nombre,
        i.cantidad,
        i.unidad_medida,
        i.referencia_items AS referencia
    FROM cp_items_pedidos i
    WHERE i.cp_pedido = :id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $idPedido]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   ✓ " . count($items) . " items encontrados\n\n";

// 4. Cargar plantilla
echo "4. Cargando plantilla...\n";
$templatePath = __DIR__ . "/../public/plantilla_pedidos.xlsx";
$spreadsheet = IOFactory::load($templatePath);
$sheet = $spreadsheet->getActiveSheet();
echo "   ✓ Plantilla cargada\n\n";

// 5. Llenar datos básicos
echo "5. Llenando datos en la plantilla...\n";
try {
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

    echo "   ✓ Encabezado llenado\n";

    // Items
    $startRow = 13;
    foreach ($items as $i => $item) {
        $row = $startRow + $i;
        $sheet->setCellValue("B{$row}", ($i + 1));
        $sheet->setCellValue("C{$row}", $item['nombre']);
        $sheet->setCellValue("I{$row}", $item['unidad_medida']);
        $sheet->setCellValue("J{$row}", $item['cantidad']);
    }
    echo "   ✓ Items llenados\n\n";
} catch (Exception $e) {
    echo "   ✗ Error llenando datos: " . $e->getMessage() . "\n";
    exit;
}

// 6. Configurar y generar PDF
echo "6. Generando PDF...\n";
try {
    \PhpOffice\PhpSpreadsheet\IOFactory::registerWriter('Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class);

    $lastRow = 42;
    $sheet->getPageSetup()->setPrintArea("A1:K{$lastRow}");
    $sheet->getPageSetup()
        ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER)
        ->setFitToPage(true)
        ->setFitToWidth(1)
        ->setFitToHeight(0);

    $sheet->getPageMargins()
        ->setTop(0.3)
        ->setRight(0.3)
        ->setLeft(0.3)
        ->setBottom(0.3);

    $sheet->setShowGridlines(false);
    $sheet->setPrintGridlines(false);

    $outputPath = __DIR__ . "/../public/test_pedido_real.pdf";

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($spreadsheet);
    $writer->save($outputPath);

    if (file_exists($outputPath)) {
        $fileSize = filesize($outputPath);
        echo "   ✓ PDF generado exitosamente\n";
        echo "   Ruta: $outputPath\n";
        echo "   Tamaño: " . number_format($fileSize) . " bytes\n\n";
        echo "   >> Abre el archivo para verificar que tenga los datos <<\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEL TEST ===\n";
