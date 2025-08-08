<?php
// users/delete.php

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar'); // Solo Admin puede borrar usuarios

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $current_user_id = $_SESSION['user_id'] ?? null;

    if (!$id || !$current_user_id) {
        header('Location: index.php?status=error&message=' . urlencode('No se proporcionó un ID válido.'));
        exit;
    }

    if ($id == $current_user_id) {
        header('Location: index.php?status=error&message=' . urlencode('No puedes eliminar tu propio usuario.'));
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Verificar si el usuario ha procesado nóminas
        $stmt_check1 = $pdo->prepare("SELECT COUNT(*) FROM nominasprocesadas WHERE id_usuario_ejecutor = ?");
        $stmt_check1->execute([$id]);
        if ($stmt_check1->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el usuario porque ha procesado nóminas.");
        }

        // Verificar si el usuario ha aprobado horas
        $stmt_check2 = $pdo->prepare("SELECT COUNT(*) FROM registrohoras WHERE id_usuario_aprobador = ?");
        $stmt_check2->execute([$id]);
        if ($stmt_check2->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el usuario porque ha aprobado horas.");
        }

        // Si no está en uso, proceder a eliminar
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();

        header('Location: index.php?status=success&message=' . urlencode('Usuario eliminado correctamente.'));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = urlencode('Error al eliminar el usuario: ' . $e->getMessage());
        header('Location: index.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
