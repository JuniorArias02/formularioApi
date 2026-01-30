<?php
require_once '../../database/conexion.php';
require_once '../../middlewares/cors.php';
$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? intval($data['id']) : 0;
if ($id <= 0) {
    echo json_encode(["ok" => false, "mensaje" => "ID inválido"]);
    exit;
}
try {
    $pdo->beginTransaction();

    $sqlItems = "DELETE FROM cp_entrega_activos_fijos_items WHERE entrega_activos_id = :id";
    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->execute([':id' => $id]);

    $sqlEntrega = "DELETE FROM cp_entrega_activos_fijos WHERE id = :id";
    $stmtEntrega = $pdo->prepare($sqlEntrega);
    $stmtEntrega->execute([':id' => $id]);

    $pdo->commit();

    echo json_encode([
        "ok" => true,
        "mensaje" => "Acta eliminada correctamente"
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        "ok" => false,
        "mensaje" => "Error al eliminar: " . $e->getMessage()
    ]);
}
