<?php
require_once '../../database/conexion.php';
require_once __DIR__ . '/../../middlewares/headers_post.php';
// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
    exit;
}
// Obtener datos (JSON o FormData)
// Asumiendo que axios envía JSON, si es FormData usar $_POST directamente
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_POST; // Fallback a FormData normal
}
$pedidoId = $data['id_pedido'] ?? null;
$usuarioId = $data['id_usuario'] ?? null;
$tipoFirma = $data['tipo_firma'] ?? null; // 'proceso_compra_firma' o 'responsable_aprobacion_firma'
if (!$pedidoId || !$usuarioId || !$tipoFirma) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan datos requeridos (id_pedido, id_usuario, tipo_firma)"]);
    exit;
}
// Validar tipo de firma para evitar inyeccion SQL en nombre de columna
$columnasPermitidas = ['proceso_compra_firma', 'responsable_aprobacion_firma'];
if (!in_array($tipoFirma, $columnasPermitidas)) {
    http_response_code(400);
    echo json_encode(["error" => "Tipo de firma no válido"]);
    exit;
}
try {
    // 1. Obtener la firma digital del usuario
    $stmtUser = $pdo->prepare("SELECT firma_digital FROM usuarios WHERE id = ? AND estado = 1");
    $stmtUser->execute([$usuarioId]);
    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$usuario || empty($usuario['firma_digital'])) {
        http_response_code(404);
        echo json_encode(["error" => "Usuario no encontrado o no tiene firma digital configurada"]);
        exit;
    }
    $rutaFirmaUser = $usuario['firma_digital'];
    // 2. Actualizar el pedido
    // Nota: Usamos una variable variable o concatenación segura porque PDO no permite usar ? para nombres de columnas
    $sql = "UPDATE cp_pedidos SET $tipoFirma = ? WHERE id = ?";
    $stmtUpdate = $pdo->prepare($sql);
    $ok = $stmtUpdate->execute([$rutaFirmaUser, $pedidoId]);
    if ($ok) {
        echo json_encode([
            "success" => true,
            "mensaje" => "Firma actualizada correctamente",
            "campo_actualizado" => $tipoFirma,
            "valor" => $rutaFirmaUser
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "No se pudo actualizar el registro del pedido"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error de base de datos: " . $e->getMessage()]);
}
?>