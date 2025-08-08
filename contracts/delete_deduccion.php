<?php
// contracts/delete_deduccion.php
// Lógica para eliminar una deducción recurrente.

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

// Validar que los datos esenciales no estén vacíos
if (empty($id_deduccion) || empty($employee_id)) {
    redirect($employee_id, 'error', 'Faltan datos para procesar la solicitud.');
}

try {
    // Preparar la consulta SQL de eliminación
    $sql = "DELETE FROM DeduccionesRecurrentes WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    // Ejecutar la consulta
    $stmt->execute([$id_deduccion]);

    if ($stmt->rowCount() > 0) {
        redirect($employee_id, 'success', 'Deducción eliminada permanentemente.');
    } else {
        redirect($employee_id, 'error', 'No se encontró la deducción o ya fue eliminada.');
    }

} catch (PDOException $e) {
    error_log('Error al eliminar deducción recurrente: ' . $e->getMessage());
    redirect($employee_id, 'error', 'Ocurrió un error de base de datos al intentar eliminar la deducción.');
}
?>
