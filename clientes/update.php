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
    $adress = $_POST['Adress'] ?? null;
    $country = $_POST['Country'] ?? null;
    $phoneNumber = $_POST['Phone_Number'] ?? null;

    if (empty($nombre) || !$id) {
        redirect_with_error('index.php', 'Faltan datos para actualizar.');
        exit;
    }

    // Validar que el RNC, si se proporciona, no pertenezca a OTRO cliente
    if (!empty($rnc)) {
        $stmt_check = $pdo->prepare("SELECT id FROM clientes WHERE rnc_cliente = ? AND id != ?");
        $stmt_check->execute([$rnc, $id]);
        if ($stmt_check->fetch()) {
            header('Location: edit.php?id=' . $id . '&status=error&message=' . urlencode('Error: El RNC ya está registrado para otro cliente.'));
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("UPDATE clientes SET nombre_cliente = ?, rnc_cliente = ?, estado = ?, Adress = ?, Country = ?, Phone_Number = ? WHERE id = ?");
        $stmt->execute([$nombre, $rnc, $estado, $adress, $country, $phoneNumber, $id]);

        redirect_with_success('index.php', 'Cliente actualizado correctamente.');
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
