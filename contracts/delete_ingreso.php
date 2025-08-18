<?php
// contracts/delete_ingreso.php
// Lógica para eliminar un ingreso recurrente.

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

// Validar que los datos esenciales no estén vacíos
if (empty($id_ingreso) || empty($employee_id)) {
    redirect($employee_id, 'error', 'Faltan datos para eliminar el ingreso.');
}

try {
    $sql = "DELETE FROM ingresosrecurrentes WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_ingreso]);

    redirect($employee_id, 'success', 'Ingreso recurrente eliminado correctamente.');

} catch (PDOException $e) {
    error_log('Error al eliminar ingreso recurrente: ' . $e->getMessage());
    redirect($employee_id, 'error', 'Ocurrió un error de base de datos al intentar eliminar el ingreso.');
}
?>
