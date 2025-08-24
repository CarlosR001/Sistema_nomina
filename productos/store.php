<?php
// productos/store.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre_producto'] ?? '';

    if (empty($nombre)) {
        redirect_with_error('create.php', 'El nombre del producto es obligatorio.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO productos (nombre_producto) VALUES (?)");
        $stmt->execute([$nombre]);

        redirect_with_success('index.php', 'Producto añadido correctamente.');
        exit;
    } catch (PDOException $e) {
        $message = urlencode('Error al guardar el producto: ' . $e->getMessage());
        header('Location: create.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
