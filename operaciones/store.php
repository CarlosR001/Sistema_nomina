<?php
// operaciones/store.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre_operacion'] ?? '';
    $descripcion = $_POST['descripcion'] ?? null;

    if (empty($nombre)) {
        redirect_with_error('create.php', 'El nombre de la operación es obligatorio.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO operaciones (nombre_operacion, descripcion) VALUES (?, ?)");
        $stmt->execute([$nombre, $descripcion]);

        redirect_with_success('index.php', 'Operación añadida correctamente.');
        exit;
    } catch (PDOException $e) {
        $message = urlencode('Error al guardar la operación: ' . $e->getMessage());
        header('Location: create.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
