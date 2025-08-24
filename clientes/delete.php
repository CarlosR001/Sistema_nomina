<?php
// clientes/delete.php

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
        // Verificar si el cliente está siendo usado en alguna orden
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM ordenes WHERE id_cliente = ?");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el cliente porque está asignado a una o más órdenes.");
        }

        // Si no está en uso, proceder a eliminar
        $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
        $stmt->execute([$id]);

        redirect_with_success('index.php', 'Cliente eliminado correctamente.');
        exit;

    } catch (Exception $e) {
        $message = urlencode('Error al eliminar el cliente: ' . $e->getMessage());
        header('Location: index.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
