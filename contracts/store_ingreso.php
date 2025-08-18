<?php
// contracts/store_ingreso.php
// Lógica para crear un nuevo ingreso recurrente

require_once '../auth.php';
require_login();
require_permission('empleados.gestionar');

function redirect($employee_id, $status, $message) {
    $url = BASE_URL . 'contracts/index.php?employee_id=' . urlencode($employee_id) . '&status=' . $status . '&message=' . urlencode($message);
    header('Location: ' . $url);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(0, 'error', 'Método no permitido.');
}

$employee_id = $_POST['employee_id'] ?? null;
$id_contrato = $_POST['id_contrato'] ?? null;
$id_concepto_ingreso = $_POST['id_concepto_ingreso'] ?? null;
$monto_ingreso = $_POST['monto_ingreso'] ?? null;
$quincena_aplicacion = $_POST['quincena_aplicacion'] ?? null;

// Validar que los datos esenciales no estén vacíos
if (empty($employee_id) || empty($id_contrato) || empty($id_concepto_ingreso) || !is_numeric($monto_ingreso) || !isset($quincena_aplicacion)) {
    redirect($employee_id, 'error', 'Faltan datos obligatorios para crear el ingreso.');
}

try {
    $sql = "INSERT INTO ingresosrecurrentes (id_contrato, id_concepto_ingreso, monto_ingreso, quincena_aplicacion, estado) VALUES (?, ?, ?, ?, 'Activa')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_contrato, $id_concepto_ingreso, $monto_ingreso, $quincena_aplicacion]);

    redirect($employee_id, 'success', 'Ingreso recurrente agregado correctamente.');

} catch (PDOException $e) {
    error_log('Error al crear ingreso recurrente: ' . $e->getMessage());
    redirect($employee_id, 'error', 'Ocurrió un error de base de datos al intentar crear el ingreso.');
}
?>
