<?php
require_once '../../database/conexion.php';
require_once __DIR__ . '/../../middlewares/headers_post.php';

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido. Use POST."]);
    exit;
}

// Obtener datos de entrada
$data = json_decode(file_get_contents("php://input"), true);
$usuario_id = $data['id'] ?? null;

if (!$usuario_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Falta el parámetro 'id' del usuario."]);
    exit;
}

try {
    // 1. Validar que el usuario exista y obtener la ruta de la firma
    $stmt = $pdo->prepare("SELECT id, firma_digital FROM usuarios WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Usuario no encontrado."]);
        exit;
    }

    if (empty($usuario['firma_digital'])) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "El usuario no tiene una firma digital registrada."]);
        exit;
    }

    $nombre_archivo = basename($usuario['firma_digital']);
    $ruta_base = __DIR__ . '/../../public/firmas/';
    $ruta_completa = $ruta_base . $nombre_archivo;

    if (!file_exists($ruta_completa)) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Archivo de firma no encontrado en el servidor.",
        ]);
        exit;
    }

    $mime_type = mime_content_type($ruta_completa);
    if (!$mime_type || strpos($mime_type, 'image') === false) {
        $mime_type = 'image/png';
    }

    if (ob_get_length()) ob_clean();

    header("Content-Type: $mime_type");
    header("Content-Length: " . filesize($ruta_completa));
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    readfile($ruta_completa);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error del servidor: " . $e->getMessage()]);
    exit;
}
