<?php
require_once '../../database/conexion.php';
require_once __DIR__ . '/../../middlewares/cors.php';
$data = json_decode(file_get_contents("php://input"), true);
$pedidoId = $data['pedido_id'] ?? null;
if (!$pedidoId) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Falta el ID del pedido."
    ]);
    exit;
}
try {
    // Buscar las firmas del pedido
    $stmt = $pdo->prepare("
        SELECT 
            responsable_aprobacion_firma,
            proceso_compra_firma
        FROM cp_pedidos
        WHERE id = ?
    ");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) {
        echo json_encode([
            "success" => false,
            "message" => "Pedido no encontrado."
        ]);
        exit;
    }
    $basePath = __DIR__ . '/../../public/firmas/';
    $firmas = [];
    // Firma del responsable de aprobación
    if (!empty($pedido['responsable_aprobacion_firma'])) {
        $relativePath = 'public/firmas/' . basename($pedido['responsable_aprobacion_firma']);
        $accumulatedPath = $basePath . basename($pedido['responsable_aprobacion_firma']);

        if (file_exists($accumulatedPath)) {
            // Devolvemos la ruta relativa para que el frontend la use
            $firmas['responsable'] = $relativePath;
        }
    }
    // Firma del proceso de compra
    if (!empty($pedido['proceso_compra_firma'])) {
        $relativePath = 'public/firmas/' . basename($pedido['proceso_compra_firma']);
        $accumulatedPath = $basePath . basename($pedido['proceso_compra_firma']);

        if (file_exists($accumulatedPath)) {
            // Devolvemos la ruta relativa para que el frontend la use
            $firmas['compra'] = $relativePath;
        }
    }
    echo json_encode([
        "success" => true,
        "firmas" => $firmas
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error al obtener firmas: " . $e->getMessage()
    ]);
}
