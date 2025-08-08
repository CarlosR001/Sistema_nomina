<?php
// contracts/update_deduccion.php
// Lógica para actualizar una deducción recurrente.

require_once '../auth.php';
require_login();
require_permission('empleados.gestionar');

// Función de redirección para mantener el código limpio
function redirect($employee_id, $status, $message) {
    $url = BASE_URL . 'contracts/index.php?employee_id=' . urlencode($employee_id) . '&status=' . $status . '&message=' . urlencode($message);
    header('Location: ' . $url);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(0, 'error', 'Método no permitido.');
}

$id_deduccion = $_POST['id_deduccion'] ?? null;
$employee_id = $_POST['employee_id'] ?? null;
$monto_deduccion = $_POST['monto_deduccion'] ?? null;
$quincena_aplicacion = $_POST['quincena_aplicacion'] ?? null;

// Validar que los datos esenciales no estén vacíos
if (empty($id_deduccion) || empty($employee_id) || !is_numeric($monto_deduccion) || !isset($quincena_aplicacion)) {
    redirect($employee_id, 'error', 'Faltan datos obligatorios para actualizar la deducción.');
}

try {
    // Preparar la consulta SQL de actualización
    $sql = "UPDATE DeduccionesRecurrentes SET monto_deduccion = ?, quincena_aplicacion = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    // Ejecutar la consulta
    $stmt->execute([$monto_deduccion, $quincena_aplicacion, $id_deduccion]);

    redirect($employee_id, 'success', 'Deducción actualizada correctamente.');

} catch (PDOException $e) {
    error_log('Error al actualizar deducción recurrente: ' . $e->getMessage());
    redirect($employee_id, 'error', 'Ocurrió un error de base de datos al intentar actualizar la deducción.');
}
?>
