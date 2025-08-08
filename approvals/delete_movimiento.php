<?php
// approvals/delete_movimiento.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$id_movimiento = $_POST['id_movimiento'] ?? null;

if (!$id_movimiento) {
    echo json_encode(['success' => false, 'message' => 'ID de movimiento no proporcionado.']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM registro_movimientos_transporte WHERE id = ?");
    $stmt->execute([$id_movimiento]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Movimiento eliminado.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró el movimiento a eliminar.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
