<?php
// clientes/store.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre_cliente'] ?? '';
    $rnc = $_POST['rnc_cliente'] ?? null;
    $estado = $_POST['estado'] ?? 'Activo';

    if (empty($nombre)) {
        // Manejo de error si el nombre está vacío
        header('Location: create.php?status=error&message=' . urlencode('El nombre del cliente es obligatorio.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO clientes (nombre_cliente, rnc_cliente, estado) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $rnc, $estado]);

        header('Location: index.php?status=success&message=' . urlencode('Cliente añadido correctamente.'));
        exit;
    } catch (PDOException $e) {
        // Manejo de error de duplicado o cualquier otro error de BD
        if ($e->getCode() == 23000) { // Código para violación de restricción de unicidad
            $message = urlencode('Error: Ya existe un cliente con ese nombre.');
        } else {
            $message = urlencode('Error al guardar el cliente: ' . $e->getMessage());
        }
        header('Location: create.php?status=error&message=' . $message);
        exit;
    }
} else {
    // Redirigir si no es un POST
    header('Location: index.php');
    exit;
}
