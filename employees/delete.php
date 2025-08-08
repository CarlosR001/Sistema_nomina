<?php
// employees/delete.php

require_once '../auth.php';
require_login();
require_role(['Admin']); // Solo Admin puede borrar empleados

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        header('Location: index.php?status=error&message=' . urlencode('No se proporcionó un ID válido.'));
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Verificar si el empleado tiene contratos asociados
        $stmt_check_contracts = $pdo->prepare("SELECT COUNT(*) FROM contratos WHERE id_empleado = ?");
        $stmt_check_contracts->execute([$id]);
        if ($stmt_check_contracts->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el empleado porque tiene uno o más contratos (activos o pasados) asociados. Considere inactivar al empleado en su lugar.");
        }

        // 2. Verificar si el empleado tiene una cuenta de usuario asociada
        $stmt_check_users = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_empleado = ?");
        $stmt_check_users->execute([$id]);
        if ($stmt_check_users->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el empleado porque tiene una cuenta de usuario. Elimine primero la cuenta de usuario.");
        }

        // Si no hay dependencias, proceder a eliminar
        $stmt = $pdo->prepare("DELETE FROM empleados WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();

        header('Location: index.php?status=success&message=' . urlencode('Empleado eliminado correctamente.'));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = urlencode('Error al eliminar el empleado: ' . $e->getMessage());
        header('Location: index.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
