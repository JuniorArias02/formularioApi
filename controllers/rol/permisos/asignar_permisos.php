<?php
require_once __DIR__ . '/../../../database/conexion.php';
require_once __DIR__ . '/../../../middlewares/headers_post.php';

$data = json_decode(file_get_contents('php://input'), true);

// Validaciones
if (!isset($data['rol_id']) || !isset($data['permisos'])) {
    http_response_code(400);
    echo json_encode(["error" => "Datos incompletos"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Eliminar permisos actuales
    $stmt = $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id = ?");
    $stmt->execute([$data['rol_id']]);

    // 2. Insertar nuevos permisos (si hay)
    if (!empty($data['permisos'])) {
        $values = [];
        foreach ($data['permisos'] as $permiso_id) {
            $values[] = "({$data['rol_id']}, $permiso_id)";
        }
        $pdo->exec("INSERT INTO rol_permisos (rol_id, permiso_id) VALUES " . implode(',', $values));
    }

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Permisos actualizados correctamente"]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => "Error al actualizar permisos: " . $e->getMessage()]);
}
