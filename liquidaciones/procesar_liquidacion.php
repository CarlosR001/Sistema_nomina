<?php
// liquidaciones/procesar_liquidacion.php - v1.0 (Procesador Final de Liquidaciones)

require_once '../auth.php';
require_login();
require_role('Admin');

// 1. Validar y rescatar datos de la sesión
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['liquidacion_data'])) {
    header('Location: index.php?status=error&message=No hay datos de liquidación para procesar.');
    exit();
}
$data = $_SESSION['liquidacion_data'];

try {
    $pdo->beginTransaction();

    // 2. Asegurar que los conceptos de nómina para liquidación existan
    $conceptos_a_verificar = [
        'ING-PREAVISO' => ['descripcion' => 'Pago por Omisión de Preaviso', 'afecta_tss' => 0, 'afecta_isr' => 0],
        'ING-CESANTIA' => ['descripcion' => 'Pago por Auxilio de Cesantía', 'afecta_tss' => 0, 'afecta_isr' => 0],
        'ING-VAC-LIQ' => ['descripcion' => 'Compensación por Vacaciones no Disfrutadas', 'afecta_tss' => 1, 'afecta_isr' => 1],
        'ING-REG-LIQ' => ['descripcion' => 'Proporción Regalía Pascual por Liquidación', 'afecta_tss' => 0, 'afecta_isr' => 1]
    ];
    foreach ($conceptos_a_verificar as $codigo => $detalles) {
        $stmt = $pdo->prepare("SELECT id FROM ConceptosNomina WHERE codigo_concepto = ?");
        $stmt->execute([$codigo]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO ConceptosNomina (codigo_concepto, descripcion_publica, tipo_concepto, origen_calculo, afecta_tss, afecta_isr) VALUES (?, ?, 'Ingreso', 'Formula', ?, ?)")
                ->execute([$codigo, $detalles['descripcion'], $detalles['afecta_tss'], $detalles['afecta_isr']]);
        }
    }

    // 3. Crear la cabecera de la nómina especial de liquidación
    $sql_nomina = "INSERT INTO NominasProcesadas (tipo_nomina_procesada, tipo_calculo_nomina, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) 
                   VALUES ('Directiva', 'Especial', ?, ?, ?, 'Calculada')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    $stmt_nomina->execute([$data['fecha_salida'], $data['fecha_salida'], $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();

    // 4. Insertar los detalles de la liquidación en la nómina
    $sql_detalle = "INSERT INTO NominaDetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, 'Ingreso', ?)";
    $stmt_detalle = $pdo->prepare($sql_detalle);
    
    // Mapeo de conceptos del cálculo a códigos de la BD
    $mapa_conceptos = [
        'Preaviso' => 'ING-PREAVISO',
        'Cesantía' => 'ING-CESANTIA',
        'Vacaciones' => 'ING-VAC-LIQ',
        'Regalía Proporcional' => 'ING-REG-LIQ'
    ];

    foreach ($data['calculos'] as $concepto_nombre => $monto) {
        if ($monto > 0) {
            $codigo_bd = $mapa_conceptos[$concepto_nombre];
            $stmt_detalle->execute([$id_nomina_procesada, $data['id_contrato'], $codigo_bd, $concepto_nombre, $monto]);
        }
    }

    // 5. Actualizar el contrato del empleado a "Finalizado"
    $sql_update_contrato = "UPDATE Contratos SET estado_contrato = 'Finalizado', fecha_fin = ? WHERE id = ?";
    $stmt_update = $pdo->prepare($sql_update_contrato);
    $stmt_update->execute([$data['fecha_salida'], $data['id_contrato']]);

    // 6. Limpiar la sesión y confirmar
    unset($_SESSION['liquidacion_data']);
    $pdo->commit();

    header('Location: ../payroll/review.php?status=success&message=' . urlencode('Liquidación procesada y contrato finalizado exitosamente.'));
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: index.php?status=error&message=' . urlencode('Error al procesar la liquidación: ' . $e->getMessage()));
    exit();
}
