<?php
// contracts/store_deduccion.php - v2.0 (con Frecuencia de Deducción)

require_once '../auth.php';
require_login();
require_role('Admin');

// Función de redirección para mantener el código limpio
function redirect($employee_id, $status, $message) {
    header('Location: ' . BASE_URL . 'contracts/index.php?employee_id=' . $employee_id . '&status=' . $status . '&message=' . urlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(0, 'error', 'Método no permitido.');
}

$employee_id = $_POST['employee_id'] ?? null;
$id_contrato = $_POST['id_contrato'] ?? null;
$id_concepto_deduccion = $_POST['id_concepto_deduccion'] ?? null;
$monto_deduccion = $_POST['monto_deduccion'] ?? null;
$quincena_aplicacion = $_POST['quincena_aplicacion'] ?? 0; // Se recoge el nuevo campo

// Validar que los datos esenciales no estén vacíos
if (empty($employee_id) || empty($id_contrato) || empty($id_concepto_deduccion) || !is_numeric($monto_deduccion)) {
    redirect($employee_id, 'error', 'Faltan datos obligatorios para crear la deducción.');
}

try {
    // Verificar si ya existe una deducción del mismo tipo para el mismo contrato
    $stmt_check = $pdo->prepare("SELECT id FROM DeduccionesRecurrentes WHERE id_contrato = ? AND id_concepto_deduccion = ?");
    $stmt_check->execute([$id_contrato, $id_concepto_deduccion]);
    if ($stmt_check->fetch()) {
        redirect($employee_id, 'error', 'Ya existe una deducción de este tipo para el contrato seleccionado.');
    }

    // Preparar la consulta SQL incluyendo el nuevo campo
    $sql = "INSERT INTO DeduccionesRecurrentes (id_contrato, id_concepto_deduccion, monto_deduccion, estado, quincena_aplicacion) VALUES (?, ?, ?, 'Activa', ?)";
    $stmt = $pdo->prepare($sql);
    
    // Ejecutar la consulta con el nuevo valor
    $stmt->execute([$id_contrato, $id_concepto_deduccion, $monto_deduccion, $quincena_aplicacion]);

    redirect($employee_id, 'success', 'Deducción recurrente añadida correctamente.');

} catch (PDOException $e) {
    error_log('Error al añadir deducción recurrente: ' . $e->getMessage());
    redirect($employee_id, 'error', 'Ocurrió un error de base de datos al intentar guardar la deducción.');
}
?>
