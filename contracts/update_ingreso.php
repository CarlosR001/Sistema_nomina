<?php
// contracts/update_ingreso.php
// Lógica para actualizar un ingreso recurrente.

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

$id_ingreso = $_POST['id_ingreso'] ?? null;
$employee_id = $_POST['employee_id'] ?? null;
$monto_ingreso = $_POST['monto_ingreso'] ?? null;
$quincena_aplicacion = $_POST['quincena_aplicacion'] ?? null;

// Validar que los datos esenciales no estén vacíos
if (empty($id_ingreso) || empty($employee_id) || !is_numeric($monto_ingreso) || !isset($quincena_aplicacion)) {
    redirect($employee_id, 'error', 'Faltan datos obligatorios para actualizar el ingreso.');
}

try {
    // Preparar la consulta SQL de actualización
    $sql = "UPDATE ingresosrecurrentes SET monto_ingreso = ?, quincena_aplicacion = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    // Ejecutar la consulta
    $stmt->execute([$monto_ingreso, $quincena_aplicacion, $id_ingreso]);

    redirect($employee_id, 'success', 'Ingreso actualizado correctamente.');

} catch (PDOException $e) {
    error_log('Error al actualizar ingreso recurrente: ' . $e->getMessage());
    redirect($employee_id, 'error', 'Ocurrió un error de base de datos al intentar actualizar el ingreso.');
}
?>
