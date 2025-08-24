<?php
// operaciones/delete.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        redirect_with_error('index.php', 'No se proporcionó un ID válido.');
        exit;
    }

    try {
        // Verificar si la operación está siendo usada en alguna orden
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM ordenes WHERE id_operacion = ?");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar la operación porque está asignada a una o más órdenes.");
        }

        // Si no está en uso, proceder a eliminar
        $stmt = $pdo->prepare("DELETE FROM operaciones WHERE id = ?");
        $stmt->execute([$id]);

        redirect_with_success('index.php', 'Operación eliminada correctamente.');
        exit;

    } catch (Exception $e) {
        $message = urlencode('Error al eliminar la operación: ' . $e->getMessage());
        header('Location: index.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
