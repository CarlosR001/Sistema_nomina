<?php
// productos/update.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre_producto'] ?? '';

    if (empty($nombre) || !$id) {
        redirect_with_error('index.php', 'Faltan datos para actualizar.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE productos SET nombre_producto = ? WHERE id = ?");
        $stmt->execute([$nombre, $id]);

        redirect_with_success('index.php', 'Producto actualizado correctamente.');
        exit;
    } catch (PDOException $e) {
        $message = urlencode('Error al actualizar el producto: ' . $e->getMessage());
        header('Location: edit.php?id=' . $id . '&status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
