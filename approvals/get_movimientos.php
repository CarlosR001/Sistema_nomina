<?php
// approvals/get_movimientos.php

require_once '../auth.php';
require_login();
require_permission('aprobaciones.gestionar');

header('Content-Type: application/json');

$registro_id = $_GET['registro_id'] ?? null;

if (!$registro_id) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            rmt.id,
            l.nombre_zona_o_muelle AS nombre_sub_lugar
        FROM registro_movimientos_transporte rmt
        JOIN lugares l ON rmt.id_sub_lugar = l.id
        WHERE rmt.id_registro_horas = ?
        ORDER BY rmt.fecha_hora_movimiento ASC
    ");
    $stmt->execute([$registro_id]);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($movimientos);

} catch (PDOException $e) {
    // En caso de error, devolver un array vacío para no romper el front-end
    http_response_code(500);
    echo json_encode([]);
}
