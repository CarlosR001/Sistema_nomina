<?php
// ordenes/assign_inspector.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_orden = $_POST['id_orden'] ?? null;
    $id_contrato = $_POST['id_contrato'] ?? null;

    if (!$id_orden || !$id_contrato) {
        redirect_with_error('index.php', 'Datos insuficientes.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO orden_asignaciones (id_orden, id_contrato_inspector) VALUES (?, ?)");
        $stmt->execute([$id_orden, $id_contrato]);

        header('Location: assign.php?id=' . $id_orden . '&status=success&message=' . urlencode('Inspector asignado correctamente.'));
        exit;
    } catch (PDOException $e) {
        $message = urlencode('Error al asignar el inspector. Es posible que ya estuviera asignado.');
        header('Location: assign.php?id=' . $id_orden . '&status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
