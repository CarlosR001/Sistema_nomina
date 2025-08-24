<?php
// lugares/store.php - v2.0 (Jerárquico)

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $nombre = $_POST['nombre_zona_o_muelle'] ?? '';
    $monto = $_POST['monto_transporte_completo'] ?? 0;

    if (empty($nombre) || !is_numeric($monto)) {
        redirect_with_error('create.php', 'El nombre y el monto son obligatorios.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO lugares (parent_id, nombre_zona_o_muelle, monto_transporte_completo) VALUES (?, ?, ?)");
        $stmt->execute([$parent_id, $nombre, $monto]);

        redirect_with_success('index.php', 'Registro guardado correctamente.');
        exit;
    } catch (PDOException $e) {
        $message = urlencode('Error al guardar el registro: ' . $e->getMessage());
        header('Location: create.php?status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
