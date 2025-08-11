<?php
// contracts/toggle_deduccion.php

require_once '../auth.php';
require_login();
require_permission('empleados.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../employees/');
    exit();
}

$id_deduccion = filter_input(INPUT_POST, 'id_deduccion', FILTER_VALIDATE_INT);
$employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);

$redirect_url = 'index.php?employee_id=' . $employee_id;

if (!$id_deduccion || !$employee_id) {
    header('Location: ' . $redirect_url . '&status=error&message=' . urlencode('Datos inválidos.'));
    exit();
}

try {
    // Primero, obtener el estado actual
    $stmt_check = $pdo->prepare("SELECT estado FROM deduccionesrecurrentes WHERE id = ?");
    $stmt_check->execute([$id_deduccion]);
    $current_status = $stmt_check->fetchColumn();

    if ($current_status === false) {
        throw new Exception("No se encontró la deducción.");
    }

    // Cambiar al estado opuesto
    $new_status = ($current_status === 'Activa') ? 'Inactiva' : 'Activa';

    $stmt_update = $pdo->prepare("UPDATE deduccionesrecurrentes SET estado = ? WHERE id = ?");
    $stmt_update->execute([$new_status, $id_deduccion]);
    
    header('Location: ' . $redirect_url . '&status=success&message=' . urlencode('Estado de la deducción actualizado.'));
    exit();

} catch (Exception $e) {
    header('Location: ' . $redirect_url . '&status=error&message=' . urlencode('Error al cambiar el estado: ' . $e->getMessage()));
    exit();
}
