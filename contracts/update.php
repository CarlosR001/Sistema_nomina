<?php
// contracts/update.php
// Procesa la actualización de un contrato existente.

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'employees/index.php?status=error&message=Método no permitido.');
    exit();
}

// Recoger y validar los datos del formulario
$id = $_POST['id'] ?? null;
$employee_id = $_POST['employee_id'] ?? null;
$id_posicion = $_POST['id_posicion'] ?? null;
$tipo_contrato = $_POST['tipo_contrato'] ?? null;
$tipo_nomina = $_POST['tipo_nomina'] ?? null;
$frecuencia_pago = $_POST['frecuencia_pago'] ?? null;
$fecha_inicio = $_POST['fecha_inicio'] ?? null;
$estado_contrato = $_POST['estado_contrato'] ?? null;

// Campos opcionales
$fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
$salario_mensual_bruto = !empty($_POST['salario_mensual_bruto']) ? $_POST['salario_mensual_bruto'] : null;
$tarifa_por_hora = !empty($_POST['tarifa_por_hora']) ? $_POST['tarifa_por_hora'] : null;


if (empty($id) || empty($employee_id) || empty($id_posicion) || empty($tipo_contrato) || empty($tipo_nomina) || empty($frecuencia_pago) || empty($fecha_inicio) || empty($estado_contrato)) {
    header('Location: ' . BASE_URL . 'contracts/index.php?employee_id=' . $employee_id . '&status=error&message=Faltan campos obligatorios.');
    exit();
}

// Lógica de negocio: No puede haber más de un contrato 'Vigente' para el mismo empleado.
if ($estado_contrato === 'Vigente') {
    $stmt_check = $pdo->prepare("SELECT id FROM Contratos WHERE id_empleado = ? AND estado_contrato = 'Vigente' AND id != ?");
    $stmt_check->execute([$employee_id, $id]);
    if ($stmt_check->fetch()) {
        header('Location: ' . BASE_URL . 'contracts/index.php?employee_id=' . $employee_id . '&status=error&message=Ya existe otro contrato vigente para este empleado.');
        exit();
    }
}

try {
    $sql = "UPDATE Contratos SET
                id_posicion = :id_posicion,
                tipo_contrato = :tipo_contrato,
                tipo_nomina = :tipo_nomina,
                fecha_inicio = :fecha_inicio,
                fecha_fin = :fecha_fin,
                salario_mensual_bruto = :salario_mensual_bruto,
                tarifa_por_hora = :tarifa_por_hora,
                frecuencia_pago = :frecuencia_pago,
                estado_contrato = :estado_contrato
            WHERE id = :id";
            
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':id_posicion' => $id_posicion,
        ':tipo_contrato' => $tipo_contrato,
        ':tipo_nomina' => $tipo_nomina,
        ':fecha_inicio' => $fecha_inicio,
        ':fecha_fin' => $fecha_fin,
        ':salario_mensual_bruto' => $salario_mensual_bruto,
        ':tarifa_por_hora' => $tarifa_por_hora,
        ':frecuencia_pago' => $frecuencia_pago,
        ':estado_contrato' => $estado_contrato,
        ':id' => $id
    ]);

    header('Location: ' . BASE_URL . 'contracts/index.php?employee_id=' . $employee_id . '&status=success&message=Contrato actualizado correctamente.');
    exit();

} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'contracts/index.php?employee_id=' . $employee_id . '&status=error&message=' . urlencode('Error de base de datos: ' . $e->getMessage()));
    exit();
}
?>
