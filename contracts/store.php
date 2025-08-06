<?php
// contracts/store.php - v2.1 (con Lógica de Inspector)

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../employees/');
    exit();
}

// --- Recoger y validar los datos del formulario ---
$employee_id = $_POST['employee_id'] ?? null;
$id_posicion = $_POST['id_posicion'] ?? null;
$tipo_contrato = $_POST['tipo_contrato'] ?? null;
$frecuencia_pago = $_POST['frecuencia_pago'] ?? null;
$fecha_inicio = $_POST['fecha_inicio'] ?? null;

// --- Campos que dependen de la lógica de Inspector ---
$tipo_nomina = $_POST['tipo_nomina'] ?? null; // Puede llegar vacío si está deshabilitado
$salario_mensual_bruto = !empty($_POST['salario_mensual_bruto']) ? (float)$_POST['salario_mensual_bruto'] : null;
$tarifa_por_hora = !empty($_POST['tarifa_por_hora']) ? (float)$_POST['tarifa_por_hora'] : null;

// --- Campos Opcionales y con valores por defecto ---
$fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
$permite_horas_extras = isset($_POST['permite_horas_extras']) ? 1 : 0;
$estado_contrato = 'Vigente'; // Por defecto, un contrato nuevo siempre está vigente

$redirect_url = 'index.php?employee_id=' . $employee_id;

if (empty($employee_id) || empty($id_posicion) || empty($tipo_contrato) || empty($frecuencia_pago) || empty($fecha_inicio)) {
    header('Location: ' . $redirect_url . '&status=error&message=' . urlencode('Faltan campos obligatorios.'));
    exit();
}

try {
    // --- LÓGICA DE INSPECTOR ---
    // 1. Determinar si la posición es de inspector consultando la BD
    $stmt_pos = $pdo->prepare("SELECT CASE WHEN LOWER(nombre_posicion) LIKE '%inspector%' THEN 1 ELSE 0 END as es_inspector FROM Posiciones WHERE id = ?");
    $stmt_pos->execute([$id_posicion]);
    $es_inspector = $stmt_pos->fetchColumn();

    // 2. Aplicar reglas de negocio
    if ($es_inspector) {
        $tipo_nomina = 'Inspectores'; // Forzamos el tipo de nómina correcto
        $salario_mensual_bruto = null; // Los inspectores no tienen salario
        if (empty($tarifa_por_hora)) {
            header('Location: ' . $redirect_url . '&status=error&message=' . urlencode('Para la nómina de Inspectores, la Tarifa por Hora es obligatoria.'));
            exit();
        }
    } else {
        // Si no es inspector, el tipo_nomina debe venir del formulario
        if (empty($tipo_nomina)) {
            // Esto ocurrirá si el campo está deshabilitado y no es inspector (un estado imposible si el JS funciona)
            $tipo_nomina = 'Administrativa'; // Asignamos un valor seguro por si acaso
        }
        $tarifa_por_hora = null; // Los no-inspectores no tienen tarifa
        if (empty($salario_mensual_bruto)) {
            header('Location: ' . $redirect_url . '&status=error&message=' . urlencode('Para este tipo de nómina, el Salario Mensual es obligatorio.'));
            exit();
        }
    }

    // Verificar que no haya otro contrato vigente para el mismo empleado
    $stmt_check = $pdo->prepare("SELECT id FROM Contratos WHERE id_empleado = ? AND estado_contrato = 'Vigente'");
    $stmt_check->execute([$employee_id]);
    if ($stmt_check->fetch()) {
        header('Location: ' . $redirect_url . '&status=error&message=' . urlencode('Ya existe otro contrato vigente para este empleado. Por favor, finalice o cancele el anterior primero.'));
        exit();
    }
    
    // Preparar y ejecutar la inserción
    $sql = "INSERT INTO Contratos (id_empleado, id_posicion, tipo_contrato, tipo_nomina, fecha_inicio, fecha_fin, salario_mensual_bruto, tarifa_por_hora, frecuencia_pago, estado_contrato, permite_horas_extras) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $employee_id, $id_posicion, $tipo_contrato, $tipo_nomina, $fecha_inicio, $fecha_fin,
        $salario_mensual_bruto, $tarifa_por_hora, $frecuencia_pago, $estado_contrato,
        $permite_horas_extras
    ]);
    
    header('Location: ' . $redirect_url . '&status=success&message=' . urlencode('Contrato añadido correctamente.'));
    exit();

} catch (PDOException $e) {
    // Para depuración: error_log($e->getMessage());
    header('Location: ' . $redirect_url . '&status=error&message=' . urlencode('Error de base de datos al añadir el contrato: ' . $e->getMessage()));
    exit();
}
?>
