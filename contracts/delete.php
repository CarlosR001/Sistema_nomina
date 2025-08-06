<?php
// contracts/delete.php
// Lógica para eliminar un contrato.

require_once '../auth.php';
require_login();
require_role('Admin');

// -- Función para redirigir con mensajes --
function redirect_with_message($employee_id, $status, $message) {
    $url = BASE_URL . 'contracts/index.php?employee_id=' . urlencode($employee_id) . '&status=' . $status . '&message=' . urlencode($message);
    header('Location: ' . $url);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $employee_id_for_redirect = isset($_POST['employee_id']) ? $_POST['employee_id'] : (isset($_GET['employee_id']) ? $_GET['employee_id'] : 0);
    redirect_with_message($employee_id_for_redirect, 'error', 'Método no permitido.');
}

$contract_id = $_POST['id'] ?? null;
$employee_id = $_POST['employee_id'] ?? null;

if (empty($contract_id) || empty($employee_id)) {
    redirect_with_message($employee_id, 'error', 'Faltan datos para procesar la solicitud.');
}

try {
    // IMPORTANTE: Antes de eliminar el contrato, podrías necesitar eliminar o
    // desvincular registros dependientes en otras tablas, como:
    // - DeduccionesRecurrentes
    // - RegistrosHoras (si los hay asociados directamente al contrato)
    // - NominaDetalles (si los hay)
    // Por simplicidad, esta versión solo elimina el contrato.
    // En una aplicación real, se debería implementar una cascada de eliminación
    // o una lógica de archivado.

    $stmt_delete_deductions = $pdo->prepare("DELETE FROM DeduccionesRecurrentes WHERE id_contrato = ?");
    $stmt_delete_deductions->execute([$contract_id]);
    
    // Ahora, eliminar el contrato principal
    $stmt = $pdo->prepare("DELETE FROM Contratos WHERE id = ?");
    $stmt->execute([$contract_id]);

    if ($stmt->rowCount() > 0) {
        redirect_with_message($employee_id, 'success', 'Contrato eliminado correctamente.');
    } else {
        redirect_with_message($employee_id, 'error', 'No se encontró el contrato o ya fue eliminado.');
    }

} catch (PDOException $e) {
    // Si hay una restricción de clave externa (foreign key), la eliminación fallará.
    // Esto es una salvaguarda importante.
    error_log('Error al eliminar contrato: ' . $e->getMessage());
    redirect_with_message($employee_id, 'error', 'Error de base de datos. Es posible que este contrato tenga registros asociados (como nóminas procesadas) y no pueda ser eliminado directamente.');
}
?>
