<?php
// novedades/guardar_ajuste_isr.php
// Procesa y guarda los ajustes manuales de ISR.

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// 1. Validar la solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ajuste_isr.php?status=error&message=Método no permitido.');
    exit();
}

$id_empleado = $_POST['id_empleado'] ?? null;
$monto = $_POST['monto'] ?? null;
$id_periodo_origen = $_POST['periodo_origen'] ?? null;
$id_periodo_destino = $_POST['periodo_destino'] ?? null;

if (empty($id_empleado) || empty($monto) || empty($id_periodo_origen) || empty($id_periodo_destino)) {
    header('Location: ajuste_isr.php?status=error&message=Todos los campos son obligatorios.');
    exit();
}

if ($monto <= 0) {
    header('Location: ajuste_isr.php?status=error&message=El monto debe ser un valor positivo.');
    exit();
}

if ($id_periodo_origen == $id_periodo_destino) {
    header('Location: ajuste_isr.php?status=error&message=El período de origen y destino no pueden ser el mismo.');
    exit();
}


try {
    $pdo->beginTransaction();

    // 2. Obtener la información necesaria de la base de datos
    $stmt_contrato = $pdo->prepare("SELECT id FROM contratos WHERE id_empleado = ? AND estado_contrato = 'Vigente' AND tipo_nomina = 'Inspectores' LIMIT 1");
    $stmt_contrato->execute([$id_empleado]);
    $id_contrato = $stmt_contrato->fetchColumn();

    if (!$id_contrato) {
        throw new Exception("No se encontró un contrato vigente de tipo 'Inspectores' para el empleado seleccionado.");
    }

    $stmt_periodo_origen = $pdo->prepare("SELECT fecha_inicio_periodo FROM periodosdereporte WHERE id = ?");
    $stmt_periodo_origen->execute([$id_periodo_origen]);
    $fecha_origen = $stmt_periodo_origen->fetchColumn();

    $stmt_periodo_destino = $pdo->prepare("SELECT fecha_inicio_periodo FROM periodosdereporte WHERE id = ?");
    $stmt_periodo_destino->execute([$id_periodo_destino]);
    $fecha_destino = $stmt_periodo_destino->fetchColumn();

    $stmt_conceptos = $pdo->query("SELECT codigo_concepto, id FROM conceptosnomina WHERE codigo_concepto IN ('DED-AJUSTE-ISR', 'ING-AJUSTE-ISR')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $id_concepto_deduccion = $stmt_conceptos['DED-AJUSTE-ISR'] ?? null;
    $id_concepto_ingreso = $stmt_conceptos['ING-AJUSTE-ISR'] ?? null;

    if (!$id_concepto_deduccion || !$id_concepto_ingreso) {
        throw new Exception("Los conceptos de ajuste 'DED-AJUSTE-ISR' o 'ING-AJUSTE-ISR' no se encuentran en la base de datos.");
    }

    // 3. Crear las dos novedades (Deducción en Origen, Ingreso en Destino)
    $stmt_insert = $pdo->prepare(
        "INSERT INTO novedadesperiodo (id_contrato, id_concepto, periodo_aplicacion, monto_valor, descripcion_adicional, estado_novedad) 
         VALUES (?, ?, ?, ?, ?, 'Pendiente')"
    );

    // Insertar la deducción en el período de origen
    $stmt_insert->execute([$id_contrato, $id_concepto_deduccion, $fecha_origen, $monto, "Transferencia ISR a período $fecha_destino"]);

    // Insertar el ingreso en el período de destino
    $stmt_insert->execute([$id_contrato, $id_concepto_ingreso, $fecha_destino, $monto, "Contrapartida ISR desde período $fecha_origen"]);

    $pdo->commit();

    header('Location: ajuste_isr.php?status=success');
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ajuste_isr.php?status=error&message=' . urlencode($e->getMessage()));
    exit();
}
?>