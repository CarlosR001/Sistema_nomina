<?php
// positions/delete.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar'); // Solo Admin puede borrar posiciones

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        header('Location: index.php?status=error&message=' . urlencode('No se proporcionó un ID válido.'));
        exit;
    }

    try {
        // Verificar si la posición está siendo usada en algún contrato
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM contratos WHERE id_posicion = ? AND estado_contrato = 'Vigente'");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar la posición porque está en uso en uno o más contratos vigentes.");
        }

        // Si no está en uso, proceder a eliminar
        $stmt = $pdo->prepare("DELETE FROM posiciones WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: index.php?status=success&message=' . urlencode('Posición eliminada correctamente.'));
        exit;

    } catch (Exception $e) {
        $message = urlencode('Error al eliminar la posición: ' . $e->getMessage());
        header('Location: index.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
