<?php
// contracts/toggle_ingreso.php
// Lógica para cambiar el estado (activar/desactivar) de un ingreso recurrente.

require_once '../auth.php';
require_login();
require_permission('empleados.gestionar');

function redirect($employee_id, $status, $message) {
    $url = BASE_URL . 'contracts/index.php?employee_id=' . urlencode($employee_id) . '&status=' . $status . '&message=' . urlencode($message);
    header('Location: ' . $url);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['employee_id']) || !is_numeric($_GET['employee_id'])) {
    redirect(0, 'error', 'Datos insuficientes para cambiar el estado del ingreso.');
}

$id_ingreso = $_GET['id'];
$employee_id = $_GET['employee_id'];

try {
    // Primero, obtenemos el estado actual del ingreso
    $stmt_current_state = $pdo->prepare("SELECT estado FROM ingresosrecurrentes WHERE id = ?");
    $stmt_current_state->execute([$id_ingreso]);
    $current_state = $stmt_current_state->fetchColumn();

    if ($current_state === false) {
        redirect($employee_id, 'error', 'El ingreso recurrente no fue encontrado.');
    }

    // Calculamos el nuevo estado
    $new_state = ($current_state == 'Activa') ? 'Inactiva' : 'Activa';

    // Actualizamos el estado en la base de datos
    $sql = "UPDATE ingresosrecurrentes SET estado = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$new_state, $id_ingreso]);

    redirect($employee_id, 'success', 'El estado del ingreso ha sido actualizado a ' . strtolower($new_state) . '.');

} catch (PDOException $e) {
    error_log('Error al cambiar estado de ingreso recurrente: ' . $e->getMessage());
    redirect($employee_id, 'error', 'Ocurrió un error de base de datos al intentar cambiar el estado del ingreso.');
}
?>
