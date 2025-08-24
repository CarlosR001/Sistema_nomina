<?php
// novedades/guardar_ajuste_isr.php - v2.0 (Soporte para Múltiples Tipos de Nómina)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

try {
    $id_empleado = filter_input(INPUT_POST, 'id_empleado', FILTER_VALIDATE_INT);
    $monto_ajuste = filter_input(INPUT_POST, 'monto_ajuste', FILTER_VALIDATE_FLOAT);
    
    if (!$id_empleado || !$monto_ajuste || $monto_ajuste <= 0) {
        throw new Exception("Datos inválidos. El monto debe ser un valor positivo.");
    }

    // Obtener el contrato y tipo de nómina del empleado
    $stmt_contrato = $pdo->prepare("SELECT id, tipo_nomina FROM contratos WHERE id_empleado = ? AND estado_contrato = 'Vigente'");
    $stmt_contrato->execute([$id_empleado]);
    $contrato = $stmt_contrato->fetch(PDO::FETCH_ASSOC);
    if (!$contrato) {
        throw new Exception("No se encontró un contrato vigente para el empleado seleccionado.");
    }
    $id_contrato = $contrato['id'];
    $tipo_nomina = $contrato['tipo_nomina'];

    $pdo->beginTransaction();

    $stmt_insert_novedad = $pdo->prepare(
        "INSERT INTO novedadesperiodo (id_contrato, id_concepto, periodo_aplicacion, monto_valor, descripcion_adicional) VALUES (?, ?, ?, ?, ?)"
    );

    // IDs de los conceptos de ajuste (deben existir en la tabla conceptosnomina)
    $id_concepto_deduccion = 24; // Asumiendo que DED-AJUSTE-ISR es 24
    $id_concepto_ingreso = 25;   // Asumiendo que ING-AJUSTE-ISR es 25

    if ($tipo_nomina === 'Inspectores') {
        // Lógica existente para Inspectores (semanal)
        $periodo_origen_id = filter_input(INPUT_POST, 'periodo_origen_insp', FILTER_VALIDATE_INT);
        $periodo_destino_id = filter_input(INPUT_POST, 'periodo_destino_insp', FILTER_VALIDATE_INT);
        if (!$periodo_origen_id || !$periodo_destino_id) {
            throw new Exception("Debe seleccionar los períodos de origen y destino para inspectores.");
        }

        $stmt_periodo = $pdo->prepare("SELECT fecha_fin_periodo FROM periodosdereporte WHERE id = ?");
        $stmt_periodo->execute([$periodo_origen_id]);
        $fecha_origen = $stmt_periodo->fetchColumn();
        $stmt_periodo->execute([$periodo_destino_id]);
        $fecha_destino = $stmt_periodo->fetchColumn();
        
        if (!$fecha_origen || !$fecha_destino) {
            throw new Exception("Períodos seleccionados no válidos.");
        }

        // Crear deducción en período de origen
        $stmt_insert_novedad->execute([$id_contrato, $id_concepto_deduccion, $fecha_origen, $monto_ajuste, 'Ajuste manual de ISR']);
        // Crear ingreso en período de destino
        $stmt_insert_novedad->execute([$id_contrato, $id_concepto_ingreso, $fecha_destino, $monto_ajuste, 'Ajuste manual de ISR']);

    } elseif ($tipo_nomina === 'Administrativa' || $tipo_nomina === 'Directiva') {
        // Nueva lógica para Administrativos (quincenal)
        $mes_admin = $_POST['mes_admin'] ?? null;
        if (!$mes_admin) {
            throw new Exception("Debe seleccionar el mes y año para el ajuste.");
        }
        
        $fecha_base = new DateTime($mes_admin . '-01');
        // Por defecto, la deducción se aplica en la 1ra quincena y el ingreso en la 2da.
        $fecha_deduccion = $fecha_base->format('Y-m-15');
        $fecha_ingreso = $fecha_base->format('Y-m-t'); // 't' da el último día del mes

        // Crear deducción en la primera quincena
        $stmt_insert_novedad->execute([$id_contrato, $id_concepto_deduccion, $fecha_deduccion, $monto_ajuste, 'Ajuste manual de ISR quincenal']);
        // Crear ingreso en la segunda quincena
        $stmt_insert_novedad->execute([$id_contrato, $id_concepto_ingreso, $fecha_ingreso, $monto_ajuste, 'Ajuste manual de ISR quincenal']);

    } else {
        throw new Exception("Tipo de nómina no soportado para esta operación.");
    }

    $pdo->commit();
    redirect_with_success('index.php', 'El ajuste de ISR se ha guardado correctamente como dos novedades de período.');
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ajuste_isr.php?status=error&message=' . urlencode('Error: ' . $e->getMessage()));
    exit();
}
?>
