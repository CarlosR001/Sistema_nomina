<?php
// bancos/store.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre_banco'] ?? '';

    if (empty($nombre)) {
        redirect_with_error('create.php', 'El nombre del banco es obligatorio.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO bancos (nombre_banco) VALUES (?)");
        $stmt->execute([$nombre]);

        redirect_with_success('index.php', 'Banco añadido correctamente.');
        exit;
    } catch (PDOException $e) {
        $message = urlencode('Error al guardar el banco: ' . $e->getMessage());
        header('Location: create.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
