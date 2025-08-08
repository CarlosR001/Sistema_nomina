<?php
// ordenes/store.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $codigo_orden = $_POST['codigo_orden'] ?? '';
    $id_cliente = $_POST['id_cliente'] ?? null;
    $id_lugar = $_POST['id_lugar'] ?? null;
    $id_producto = $_POST['id_producto'] ?? null;
    $id_operacion = $_POST['id_operacion'] ?? null;
    $id_division = $_POST['id_division'] ?? null; // <-- NUEVO
    $fecha_creacion = $_POST['fecha_creacion'] ?? null;
    $fecha_finalizacion = !empty($_POST['fecha_finalizacion']) ? $_POST['fecha_finalizacion'] : null; // <-- NUEVO (maneja opcional)
    $estado_orden = $_POST['estado_orden'] ?? 'Pendiente';
    $id_usuario_creador = $_SESSION['user_id'];


    // Validaciones básicas
    if (empty($codigo_orden) || !$id_cliente || !$id_lugar || !$id_producto || !$id_operacion || !$fecha_creacion) {
        header('Location: create.php?status=error&message=' . urlencode('Todos los campos son obligatorios.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO ordenes (codigo_orden, id_cliente, id_lugar, id_producto, id_operacion, id_division, fecha_creacion, fecha_finalizacion, id_usuario_creador, estado_orden) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$codigo_orden, $id_cliente, $id_lugar, $id_producto, $id_operacion, $id_division, $fecha_creacion, $fecha_finalizacion, $id_usuario_creador, $estado_orden]);

        $id_orden_creada = $pdo->lastInsertId();

        // Redirigir a la página de asignación de inspectores para la nueva orden
        header('Location: assign.php?id=' . $id_orden_creada . '&status=success&message=' . urlencode('Orden creada. Ahora asigne los inspectores.'));
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = urlencode('Error: Ya existe una orden con ese código.');
        } else {
            $message = urlencode('Error al guardar la orden: ' . $e->getMessage());
        }
        header('Location: create.php?status=error&message=' . $message);
        exit;
    }


} else {
    header('Location: index.php');
    exit;
}
