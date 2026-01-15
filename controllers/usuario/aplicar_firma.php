<?php
require_once '../../database/conexion.php';
require_once __DIR__ . '/../../middlewares/headers_post.php';

// Leer cuerpo JSON
$data = json_decode(file_get_contents("php://input"), true);

$usuario_id = intval($data["usuario_id"] ?? 0);

// Validar parámetros
if (!$usuario_id) {
    echo json_encode([
        "status" => false,
        "message" => "Faltan datos (usuario_id)"
    ]);
    exit;
}

// Buscar usuario
$stmt = $pdo->prepare("SELECT firma_digital 
                       FROM usuarios 
                       WHERE id = :id AND estado = 1");
$stmt->execute(["id" => $usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo json_encode([
        "status" => false,
        "message" => "Usuario no encontrado o inactivo"
    ]);
    exit;
}

// Validar que tenga firma guardada
if (empty($usuario["firma_digital"])) {
    echo json_encode([
        "status" => false,
        "message" => "El usuario no tiene firma registrada"
    ]);
    exit;
}

$ruta_firma = __DIR__ . '/../../' . $usuario["firma_digital"];

if (!file_exists($ruta_firma)) {
    echo json_encode([
        "status" => false,
        "message" => "No se encontró el archivo de la firma"
    ]);
    exit;
}

// Convertir la imagen a base64
$contenido = file_get_contents($ruta_firma);
$base64 = "data:image/png;base64," . base64_encode($contenido);

echo json_encode([
    "status" => true,
    "message" => "Firma recuperada correctamente",
    "firma" => $base64
]);
