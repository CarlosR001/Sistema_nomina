<?php
// operaciones/update.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre_operacion'] ?? '';
    $descripcion = $_POST['descripcion'] ?? null;

    if (empty($nombre) || !$id) {
        redirect_with_error('index.php', 'Faltan datos para actualizar.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE operaciones SET nombre_operacion = ?, descripcion = ? WHERE id = ?");
        $stmt->execute([$nombre, $descripcion, $id]);

        redirect_with_success('index.php', 'Operación actualizada correctamente.');
        exit;
    } catch (PDOException $e) {
        $message = urlencode('Error al actualizar la operación: ' . $e->getMessage());
        header('Location: edit.php?id=' . $id . '&status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
