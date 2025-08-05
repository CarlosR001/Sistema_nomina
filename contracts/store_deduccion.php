<?php
// contracts/store_deduccion.php

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../employees/');
    exit();
}

$id_contrato = filter_input(INPUT_POST, 'id_contrato', FILTER_VALIDATE_INT);
$id_concepto = filter_input(INPUT_POST, 'id_concepto_deduccion', FILTER_VALIDATE_INT);
$monto = filter_input(INPUT_POST, 'monto_deduccion', FILTER_VALIDATE_FLOAT);
$employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);

$redirect_url = 'index.php?employee_id=' . $employee_id;

if (!$id_contrato || !$id_concepto || !$monto || !$employee_id) {
    header('Location: ' . $redirect_url . '&status=error&message=' . urlencode('Todos los campos son obligatorios.'));
    exit();
}

try {
    $sql = "INSERT INTO DeduccionesRecurrentes (id_contrato, id_concepto_deduccion, monto_deduccion, estado) VALUES (?, ?, ?, 'Activa')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_contrato, $id_concepto, $monto]);
    
    header('Location: ' . $redirect_url . '&status=success&message=' . urlencode('Deducción recurrente añadida correctamente.'));
    exit();

} catch (PDOException $e) {
    header('Location: ' . $redirect_url . '&status=error&message=' . urlencode('Error al añadir la deducción: ' . $e->getMessage()));
    exit();
}
