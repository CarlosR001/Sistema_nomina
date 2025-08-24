<?php
// divisiones/store.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre_division'] ?? '';

    if (empty($nombre)) {
        redirect_with_error('create.php', 'El nombre de la división es obligatorio.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO divisiones (nombre_division) VALUES (?)");
        $stmt->execute([$nombre]);

        redirect_with_success('index.php', 'División añadida correctamente.');
        exit;
    } catch (PDOException $e) {
        $message = urlencode('Error al guardar la división: ' . $e->getMessage());
        header('Location: create.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
