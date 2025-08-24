<?php
// departments/delete.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar'); // Solo Admin puede borrar departamentos

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        redirect_with_error('index.php', 'No se proporcionó un ID válido.');
        exit;
    }

    try {
        // Verificar si el departamento tiene posiciones asignadas
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM posiciones WHERE id_departamento = ?");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el departamento porque tiene posiciones asignadas.");
        }

        // Si no está en uso, proceder a eliminar
        $stmt = $pdo->prepare("DELETE FROM departamentos WHERE id = ?");
        $stmt->execute([$id]);

        redirect_with_success('index.php', 'Departamento eliminado correctamente.');
        exit;

    } catch (Exception $e) {
        $message = urlencode('Error al eliminar el departamento: ' . $e->getMessage());
        header('Location: index.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
