<?php
// ordenes/update.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $id = $_POST['id'] ?? null;
    $codigo_orden = $_POST['codigo_orden'] ?? '';
    $id_cliente = $_POST['id_cliente'] ?? null;
    $id_lugar = $_POST['id_lugar'] ?? null;
    $id_producto = $_POST['id_producto'] ?? null;
    $id_operacion = $_POST['id_operacion'] ?? null;
    $id_supervisor = $_POST['id_supervisor'] ?? null; // <-- NUEVO
    $id_division = $_POST['id_division'] ?? null;
    $fecha_creacion = $_POST['fecha_creacion'] ?? null;
    $fecha_finalizacion = !empty($_POST['fecha_finalizacion']) ? $_POST['fecha_finalizacion'] : null;
    $estado_orden = $_POST['estado_orden'] ?? 'Pendiente';

    if (!$id || empty($codigo_orden) || !$id_cliente || !$id_lugar || !$id_producto || !$id_operacion || !$fecha_creacion) {
        header('Location: edit.php?id=' . $id . '&status=error&message=' . urlencode('Todos los campos son obligatorios.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE ordenes SET 
                codigo_orden = ?, id_cliente = ?, id_lugar = ?, 
                id_producto = ?, id_operacion = ?, id_supervisor = ?, 
                id_division = ?, fecha_creacion = ?, fecha_finalizacion = ?, 
                estado_orden = ? 
             WHERE id = ?"
        );
        $stmt->execute([
            $codigo_orden, $id_cliente, $id_lugar, 
            $id_producto, $id_operacion, $id_supervisor, 
            $id_division, $fecha_creacion, $fecha_finalizacion, 
            $estado_orden, $id
        ]);

        header('Location: index.php?status=success&message=' . urlencode('Orden actualizada correctamente.'));
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = urlencode('Error: Ya existe otra orden con ese código.');
        } else {
            $message = urlencode('Error al actualizar la orden: ' . $e->getMessage());
        }
        header('Location: edit.php?id=' . $id . '&status=error&message=' . $message);
        exit;
    }


