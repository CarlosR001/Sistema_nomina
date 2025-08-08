<?php
// divisiones/delete.php

require_once '../auth.php';
require_login();
require_role(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        header('Location: index.php?status=error&message=' . urlencode('No se proporcionó un ID válido.'));
        exit;
    }

    try {
        // Verificar si la división está siendo usada en alguna orden
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM ordenes WHERE id_division = ?");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar la división porque está asignada a una o más órdenes.");
        }

        // Si no está en uso, proceder a eliminar
        $stmt = $pdo->prepare("DELETE FROM divisiones WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: index.php?status=success&message=' . urlencode('División eliminada correctamente.'));
        exit;

    } catch (Exception $e) {
        $message = urlencode('Error al eliminar la división: ' . $e->getMessage());
        header('Location: index.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
