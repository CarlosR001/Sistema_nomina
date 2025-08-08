<?php
// ordenes/delete.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        header('Location: index.php?status=error&message=' . urlencode('No se proporcionó un ID válido.'));
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Verificar si la orden tiene horas registradas
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM RegistroHoras WHERE id_orden = ?");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar la orden porque ya tiene horas de trabajo registradas.");
        }

        // Eliminar las asignaciones de inspectores para esta orden.
        // La base de datos también podría hacer esto en cascada si se configuró así.
        $stmt_assign = $pdo->prepare("DELETE FROM orden_asignaciones WHERE id_orden = ?");
        $stmt_assign->execute([$id]);

        // Eliminar la orden principal
        $stmt_orden = $pdo->prepare("DELETE FROM ordenes WHERE id = ?");
        $stmt_orden->execute([$id]);

        $pdo->commit();

        header('Location: index.php?status=success&message=' . urlencode('Orden eliminada correctamente.'));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = urlencode('Error al eliminar la orden: ' . $e->getMessage());
        header('Location: index.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
