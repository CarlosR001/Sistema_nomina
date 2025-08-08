<?php
// approvals/add_movimiento.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$id_registro_horas = $_POST['id_registro_horas'] ?? null;
$id_sub_lugar = $_POST['id_sub_lugar'] ?? null;

if (!$id_registro_horas || !$id_sub_lugar) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO registro_movimientos_transporte (id_registro_horas, id_sub_lugar) VALUES (?, ?)"
    );
    $stmt->execute([$id_registro_horas, $id_sub_lugar]);

    echo json_encode(['success' => true, 'message' => 'Movimiento añadido.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
