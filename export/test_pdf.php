<?php
// Script de prueba para diagnosticar problemas con PDF
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;

echo "=== TEST DE GENERACIÓN PDF ===\n\n";

// 1. Verificar que mpdf está instalado
echo "1. Verificando mPDF...\n";
if (class_exists('Mpdf\Mpdf')) {
    echo "   ✓ mPDF está instalado\n\n";
} else {
    echo "   ✗ mPDF NO está instalado\n\n";
    exit;
}

// 2. Verificar que la plantilla existe
$templatePath = __DIR__ . "/../public/plantilla_pedidos.xlsx";
echo "2. Verificando plantilla...\n";
echo "   Ruta: $templatePath\n";
if (file_exists($templatePath)) {
    echo "   ✓ Plantilla existe\n\n";
} else {
    echo "   ✗ Plantilla NO existe\n\n";
    exit;
}

// 3. Cargar la plantilla
echo "3. Cargando plantilla...\n";
try {
    $spreadsheet = IOFactory::load($templatePath);
    echo "   ✓ Plantilla cargada correctamente\n\n";
} catch (Exception $e) {
    echo "   ✗ Error al cargar: " . $e->getMessage() . "\n\n";
    exit;
}

// 4. Obtener la hoja activa
echo "4. Obteniendo hoja activa...\n";
$sheet = $spreadsheet->getActiveSheet();
echo "   ✓ Hoja activa obtenida\n";
echo "   Nombre: " . $sheet->getTitle() . "\n\n";

// 5. Verificar contenido
echo "5. Verificando contenido de la plantilla...\n";
$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();
echo "   Última fila: $highestRow\n";
echo "   Última columna: $highestColumn\n";
echo "   Valor en A1: " . $sheet->getCell('A1')->getValue() . "\n\n";

// 6. Configurar para PDF
echo "6. Configurando para PDF...\n";
try {
    \PhpOffice\PhpSpreadsheet\IOFactory::registerWriter('Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class);

    $sheet->getPageSetup()->setPrintArea("A1:K50");
    $sheet->getPageSetup()
        ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER)
        ->setFitToPage(true)
        ->setFitToWidth(1)
        ->setFitToHeight(0);

    echo "   ✓ Configuración aplicada\n\n";
} catch (Exception $e) {
    echo "   ✗ Error en configuración: " . $e->getMessage() . "\n\n";
    exit;
}

// 7. Intentar generar PDF
echo "7. Generando PDF de prueba...\n";
try {
    $outputPath = __DIR__ . "/../public/test_output.pdf";

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($spreadsheet);
    $writer->save($outputPath);

    if (file_exists($outputPath)) {
        $fileSize = filesize($outputPath);
        echo "   ✓ PDF generado exitosamente\n";
        echo "   Ruta: $outputPath\n";
        echo "   Tamaño: " . number_format($fileSize) . " bytes\n";

        if ($fileSize < 1000) {
            echo "   ⚠ ADVERTENCIA: El archivo es muy pequeño, puede estar vacío\n";
        }
    } else {
        echo "   ✗ El archivo no se creó\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error al generar PDF: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEL TEST ===\n";
