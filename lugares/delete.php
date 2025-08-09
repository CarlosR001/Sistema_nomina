<?php
// zones/delete.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        header('Location: index.php?status=error&message=' . urlencode('No se proporcionó un ID válido.'));
        exit;
    }

    try {
        // Verificar si el lugar (o sub-lugar) está siendo usado en alguna orden
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM ordenes WHERE id_lugar = ?");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el lugar principal porque está asignado a una o más órdenes.");
        }
        
        // Adicionalmente, verificar si es un sub-lugar y está en uso en registro_movimientos_transporte
        $stmt_check_mov = $pdo->prepare("SELECT COUNT(*) FROM registro_movimientos_transporte WHERE id_sub_lugar = ?");
        $stmt_check_mov->execute([$id]);
        if ($stmt_check_mov->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el sub-lugar porque tiene movimientos de transporte registrados.");
        }

        // Si no está en uso, proceder a eliminar de la tabla 'lugares'
        $stmt = $pdo->prepare("DELETE FROM lugares WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: index.php?status=success&message=' . urlencode('Lugar/Sub-Lugar eliminado correctamente.'));
        exit;

    } catch (Exception $e) {
        $message = urlencode('Error al eliminar: ' . $e->getMessage());
        header('Location: index.php?status=error&message=' . $message);
        exit;
    }

} else {
    header('Location: index.php');
    exit;
}
