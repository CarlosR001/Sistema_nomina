<?php
// bancos/delete.php

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
        // Primero, verificar si algún empleado está usando este banco.
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM empleados WHERE id_banco = ?");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el banco porque está asignado a uno o más empleados.");
        }

        // Si no está en uso, proceder a eliminar.
        $stmt = $pdo->prepare("DELETE FROM bancos WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: index.php?status=success&message=' . urlencode('Banco eliminado correctamente.'));
        exit;

    } catch (Exception $e) {
        $message = urlencode('Error al eliminar el banco: ' . $e->getMessage());
        header('Location: index.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
