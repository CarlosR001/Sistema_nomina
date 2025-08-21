<?php
// clientes/store.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre_cliente'] ?? '';
    $rnc = $_POST['rnc_cliente'] ?? null;
    $estado = $_POST['estado'] ?? 'Activo';
    $adress = $_POST['Adress'] ?? null;
    $country = $_POST['Country'] ?? null;
    $phoneNumber = $_POST['Phone_Number'] ?? null;

    if (empty($nombre)) {
        header('Location: create.php?status=error&message=' . urlencode('El nombre del cliente es obligatorio.'));
        exit;
    }

    // Validar que el RNC, si se proporciona, no esté ya registrado
    if (!empty($rnc)) {
        $stmt_check = $pdo->prepare("SELECT id FROM clientes WHERE rnc_cliente = ?");
        $stmt_check->execute([$rnc]);
        if ($stmt_check->fetch()) {
            header('Location: create.php?status=error&message=' . urlencode('Error: Ya existe un cliente con ese RNC.'));
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO clientes (nombre_cliente, rnc_cliente, estado, Adress, Country, Phone_Number) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $rnc, $estado, $adress, $country, $phoneNumber]);

        header('Location: index.php?status=success&message=' . urlencode('Cliente añadido correctamente.'));
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = urlencode('Error: Ya existe un cliente con ese nombre.');
        } else {
            $message = urlencode('Error al guardar el cliente: ' . $e->getMessage());
        }
        header('Location: create.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
