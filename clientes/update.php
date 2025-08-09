<?php
// clientes/update.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre_cliente'] ?? '';
    $rnc = $_POST['rnc_cliente'] ?? null;
    $estado = $_POST['estado'] ?? 'Activo';

    if (empty($nombre) || !$id) {
        // Manejo de error
        header('Location: index.php?status=error&message=' . urlencode('Faltan datos para actualizar.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE clientes SET nombre_cliente = ?, rnc_cliente = ?, estado = ? WHERE id = ?");
        $stmt->execute([$nombre, $rnc, $estado, $id]);

        header('Location: index.php?status=success&message=' . urlencode('Cliente actualizado correctamente.'));
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = urlencode('Error: Ya existe otro cliente con ese nombre.');
        } else {
            $message = urlencode('Error al actualizar el cliente: ' . $e->getMessage());
        }
        header('Location: edit.php?id=' . $id . '&status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
