<?php
// Test de la nueva estrategia: Excel -> HTML -> PDF
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../database/conexion.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

echo "=== TEST: EXCEL -> HTML -> PDF ===\n\n";

// 1. Obtener un pedido
echo "1. Obteniendo pedido...\n";
$sql = "SELECT id FROM cp_pedidos LIMIT 1";
$stmt = $pdo->query($sql);
$pedidoInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedidoInfo) {
    echo "   ✗ No hay pedidos\n";
    exit;
}

$idPedido = $pedidoInfo['id'];
echo "   ✓ Pedido ID: $idPedido\n\n";

// 2. Cargar plantilla
echo "2. Cargando plantilla...\n";
$templatePath = __DIR__ . "/../public/plantilla_pedidos.xlsx";
$spreadsheet = IOFactory::load($templatePath);
$sheet = $spreadsheet->getActiveSheet();
echo "   ✓ Plantilla cargada\n\n";

// 3. Agregar datos de prueba
echo "3. Agregando datos de prueba...\n";
$sheet->setCellValue("E6", "29/01/2026");
$sheet->setCellValue("E7", "PROCESO DE PRUEBA");
$sheet->setCellValue("I6", "TEST-001");
$sheet->setCellValue("I7", "SEDE PRINCIPAL");
$sheet->setCellValue("B13", "1");
$sheet->setCellValue("C13", "Item de prueba");
$sheet->setCellValue("I13", "UND");
$sheet->setCellValue("J13", "10");
echo "   ✓ Datos agregados\n\n";

// 4. Convertir a HTML
echo "4. Convirtiendo Excel a HTML...\n";
try {
    $htmlWriter = new \PhpOffice\PhpSpreadsheet\Writer\Html($spreadsheet);
    $htmlWriter->setSheetIndex(0);
    $htmlContent = $htmlWriter->generateHTMLAll();

    $htmlSize = strlen($htmlContent);
    echo "   ✓ HTML generado: " . number_format($htmlSize) . " bytes\n\n";

    // Guardar HTML para inspección
    file_put_contents(__DIR__ . "/../public/test_output.html", $htmlContent);
    echo "   >> HTML guardado en: public/test_output.html\n\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    exit;
}

// 5. Convertir HTML a PDF con DomPDF
echo "5. Convirtiendo HTML a PDF con DomPDF...\n";
try {
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($htmlContent);
    $dompdf->setPaper('letter', 'landscape');
    $dompdf->render();

    $pdfOutput = $dompdf->output();
    $pdfPath = __DIR__ . "/../public/test_dompdf.pdf";
    file_put_contents($pdfPath, $pdfOutput);

    $pdfSize = filesize($pdfPath);
    echo "   ✓ PDF generado: " . number_format($pdfSize) . " bytes\n";
    echo "   >> PDF guardado en: public/test_dompdf.pdf\n\n";

    if ($pdfSize < 1000) {
        echo "   ⚠ ADVERTENCIA: El PDF es muy pequeño\n";
    } else {
        echo "   ✓ El tamaño parece correcto\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEL TEST ===\n";
echo "\nAbre el archivo public/test_dompdf.pdf para verificar el contenido\n";
