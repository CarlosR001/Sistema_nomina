<?php
// contracts/store.php - v2.0 (con Permite Horas Extras y Horarios)

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../employees/');
    exit();
}

$id_empleado = filter_input(INPUT_POST, 'id_empleado', FILTER_VALIDATE_INT);
$id_posicion = filter_input(INPUT_POST, 'id_posicion', FILTER_VALIDATE_INT);
$tipo_contrato = $_POST['tipo_contrato'];
$tipo_nomina = $_POST['tipo_nomina'];
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin = $_POST['fecha_fin'] ?? null; // Opcional
$salario_mensual_bruto = filter_input(INPUT_POST, 'salario_mensual_bruto', FILTER_VALIDATE_FLOAT) ?? null;
$tarifa_por_hora = filter_input(INPUT_POST, 'tarifa_por_hora', FILTER_VALIDATE_FLOAT) ?? null;
$frecuencia_pago = $_POST['frecuencia_pago'];
$estado_contrato = $_POST['estado_contrato'];

// Nuevos campos
$horario_entrada = $_POST['horario_entrada'] ?? '08:00:00'; // Valor por defecto si no se envía
$horario_salida = $_POST['horario_salida'] ?? '17:00:00'; // Valor por defecto si no se envía
$permite_horas_extras = isset($_POST['permite_horas_extras']) ? 1 : 0;

$redirect_url = 'index.php?employee_id=' . $id_empleado;

if (!$id_empleado || !$id_posicion || empty($tipo_contrato) || empty($tipo_nomina) || empty($fecha_inicio) || empty($frecuencia_pago) || empty($estado_contrato)) {
    header('Location: ' . $redirect_url . '&status=error&message=' . urlencode('Faltan campos obligatorios.'));
    exit();
}

try {
    $sql = "INSERT INTO Contratos (id_empleado, id_posicion, tipo_contrato, tipo_nomina, fecha_inicio, fecha_fin, salario_mensual_bruto, tarifa_por_hora, frecuencia_pago, estado_contrato, horario_entrada, horario_salida, permite_horas_extras) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $id_empleado, $id_posicion, $tipo_contrato, $tipo_nomina, $fecha_inicio, $fecha_fin,
        $salario_mensual_bruto, $tarifa_por_hora, $frecuencia_pago, $estado_contrato,
        $horario_entrada, $horario_salida, $permite_horas_extras
    ]);
    
    header('Location: ' . $redirect_url . '&status=success&message=' . urlencode('Contrato añadido correctamente.'));
    exit();

} catch (PDOException $e) {
    header('Location: ' . $redirect_url . '&status=error&message=' . urlencode('Error al añadir el contrato: ' . $e->getMessage()));
    exit();
}
