<?php
// bancos/store.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre_banco'] ?? '';

    if (empty($nombre)) {
        header('Location: create.php?status=error&message=' . urlencode('El nombre del banco es obligatorio.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO bancos (nombre_banco) VALUES (?)");
        $stmt->execute([$nombre]);

        header('Location: index.php?status=success&message=' . urlencode('Banco añadido correctamente.'));
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
