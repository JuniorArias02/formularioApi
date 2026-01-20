<?php
require_once '../../database/conexion.php';
require_once __DIR__ . '/../../middlewares/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Método no permitido"]);
    exit;
}

// Obtener datos JSON del body
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos obligatorios
$usuario_id = isset($data['usuario_id']) ? intval($data['usuario_id']) : null;
$codigo_producto = isset($data['codigo_producto']) ? trim($data['codigo_producto']) : null;
$nombre = isset($data['nombre']) ? trim($data['nombre']) : null;

if (!$usuario_id || !$codigo_producto || !$nombre) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "El usuario_id, código_producto y nombre son obligatorios"
    ]);
    exit;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        
}

try {
    // Validar que el usuario exista y esté activo
    $stmt = $pdo->prepare("SELECT id, nombre_completo, estado FROM usuarios WHERE id = :usuario_id");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "El usuario no existe"
        ]);
        exit;
    }

    if ($usuario['estado'] != 1) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "error" => "El usuario no está activo"
        ]);
        exit;
    }

    // Validar que el código de producto no exista
    $stmt = $pdo->prepare("SELECT id FROM cp_productos_servicios WHERE codigo_producto = :codigo");
    $stmt->execute([':codigo' => $codigo_producto]);

    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "error" => "El código de producto ya existe"
        ]);
        exit;
    }

    // Crear producto/servicio
    $stmt = $pdo->prepare("
        INSERT INTO cp_productos_servicios (codigo_producto, nombre)
        VALUES (:codigo_producto, :nombre)
    ");

    $stmt->execute([
        ':codigo_producto' => $codigo_producto,
        ':nombre' => $nombre
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Producto/Servicio creado con éxito",
        "producto_servicio" => [
            "id" => $pdo->lastInsertId(),
            "codigo_producto" => $codigo_producto,
            "nombre" => $nombre
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error en el servidor: " . $e->getMessage()
    ]);
}
