<?php
// divisiones/update.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre_division'] ?? '';

    if (empty($nombre) || !$id) {
        redirect_with_error('index.php', 'Faltan datos para actualizar.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE divisiones SET nombre_division = ? WHERE id = ?");
        $stmt->execute([$nombre, $id]);

        redirect_with_success('index.php', 'División actualizada correctamente.');
        exit;
    } catch (PDOException $e) {
        $message = urlencode('Error al actualizar la división: ' . $e->getMessage());
        header('Location: edit.php?id=' . $id . '&status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
