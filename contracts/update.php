<?php
// contracts/update.php - v2.0 DEFINITIVA
// Versión funcional. El error anterior era causado por un bloqueo en la base de datos.

require_once '../auth.php';
require_login();
require_role('Admin');

// -- Función para redirigir con mensajes claros --
function redirect_with_error($employee_id, $message) {
    $url = BASE_URL . 'contracts/index.php?employee_id=' . urlencode($employee_id) . '&status=error&message=' . urlencode($message);
    header('Location: ' . $url);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $employee_id_for_redirect = isset($_POST['employee_id']) ? $_POST['employee_id'] : 0;
    redirect_with_error($employee_id_for_redirect, 'Método no permitido.');
}

// --- Recoger y validar los datos del formulario ---
$id = $_POST['id'] ?? null;
$employee_id = $_POST['employee_id'] ?? null;
$id_posicion = $_POST['id_posicion'] ?? null;
$tipo_contrato = $_POST['tipo_contrato'] ?? null;
$tipo_nomina = $_POST['tipo_nomina'] ?? null;
$frecuencia_pago = $_POST['frecuencia_pago'] ?? null;
$fecha_inicio = $_POST['fecha_inicio'] ?? null;
$estado_contrato = $_POST['estado_contrato'] ?? null;
$permite_horas_extras = isset($_POST['permite_horas_extras']) ? 1 : 0;

// Campos opcionales
$fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
$salario_mensual_bruto = !empty($_POST['salario_mensual_bruto']) ? (float)$_POST['salario_mensual_bruto'] : null;
$tarifa_por_hora = !empty($_POST['tarifa_por_hora']) ? (float)$_POST['tarifa_por_hora'] : null;

if (empty($id) || empty($employee_id) || empty($id_posicion) || empty($tipo_contrato) || empty($tipo_nomina) || empty($frecuencia_pago) || empty($fecha_inicio) || empty($estado_contrato)) {
    redirect_with_error($employee_id, 'Faltan campos obligatorios.');
}


// --- APLICAR LA NUEVA REGLA DE NEGOCIO ---
if ($tipo_nomina === 'Inspectores') {
    $salario_mensual_bruto = null; 
    if (empty($tarifa_por_hora)) {
        redirect_with_error($employee_id, 'Para la nómina de Inspectores, la Tarifa por Hora es obligatoria.');
    }
} else { 
    $tarifa_por_hora = null; 
    if (empty($salario_mensual_bruto)) {
        redirect_with_error($employee_id, 'Para este tipo de nómina, el Salario Mensual es obligatorio.');
    }
}


try {
    // Verificar que no haya otro contrato vigente para el mismo empleado
    if ($estado_contrato === 'Vigente') {
        $stmt_check = $pdo->prepare("SELECT id FROM Contratos WHERE id_empleado = ? AND estado_contrato = 'Vigente' AND id != ?");
        $stmt_check->execute([$employee_id, $id]);
        if ($stmt_check->fetch()) {
            redirect_with_error($employee_id, 'Ya existe otro contrato vigente para este empleado. Por favor, finalice el anterior primero.');
        }
    }
    
$sql = "UPDATE Contratos SET id_posicion = ?, tipo_contrato = ?, tipo_nomina = ?, frecuencia_pago = ?, fecha_inicio = ?, fecha_fin = ?, salario_mensual_bruto = ?, tarifa_por_hora = ?, estado_contrato = ?, permite_horas_extras = ? WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_posicion, $tipo_contrato, $tipo_nomina, $frecuencia_pago, $fecha_inicio, $fecha_fin, $salario_mensual_bruto, $tarifa_por_hora, $estado_contrato, $permite_horas_extras, $id]);


    $success_url = BASE_URL . 'contracts/index.php?employee_id=' . urlencode($employee_id) . '&status=success&message=Contrato actualizado correctamente.';
    header('Location: ' . $success_url);
    exit();

} catch (PDOException $e) {
    error_log('Error al actualizar contrato: ' . $e->getMessage());
    redirect_with_error($employee_id, 'Ocurrió un error de base de datos al guardar el contrato.');
}
?>
