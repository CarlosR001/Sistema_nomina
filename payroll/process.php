<?php
// payroll/process.php - v7.0 FINAL
// Corrige la selección de empleados y la lógica de cálculo de TSS.

require_once '../auth.php';
require_login();
require_role('Admin');

function esUltimaSemanaDelMes($fecha_fin_periodo) { /* ... */ }

// ... (código inicial que no cambia) ...

try {
    $pdo->beginTransaction();

    // ... (código de configuración y limpieza que no cambia) ...

    $sql_nomina = "INSERT INTO NominasProcesadas (tipo_nomina_procesada, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES (?, ?, ?, ?, 'Pendiente de Aprobación')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    $stmt_nomina->execute([$tipo_nomina, $fecha_inicio, $fecha_fin, $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();

    // CORRECCIÓN 1: Consulta robusta para obtener TODOS los empleados con datos en el período.
    $sql_contratos = "SELECT DISTINCT c.id, c.id_empleado, c.tarifa_por_hora 
                      FROM Contratos c 
                      LEFT JOIN RegistroHoras rh ON c.id = rh.id_contrato AND rh.fecha_trabajada BETWEEN :fecha_inicio AND :fecha_fin AND rh.estado_registro = 'Aprobado'
                      LEFT JOIN NovedadesPeriodo np ON c.id = np.id_contrato AND np.periodo_aplicacion = :fecha_inicio
                      WHERE c.tipo_nomina = 'Inspectores' AND (rh.id IS NOT NULL OR np.id IS NOT NULL)";
    $stmt_contratos = $pdo->prepare($sql_contratos);
    $stmt_contratos->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $contratos = $stmt_contratos->fetchAll();

    foreach ($contratos as $contrato) {
        // ... (código de cálculo de horas y carga de novedades que no cambia) ...

        // PASO B: CALCULAR BASE TSS y DEDUCCIONES TSS (LÓGICA CORREGIDA)
        $salario_cotizable_tss = 0;
        foreach ($conceptos as $data) { if ($data['tipo'] === 'Ingreso' && $data['aplica_tss']) { $salario_cotizable_tss += $data['monto']; } }
        
        $tope_salarial_semanal = $tope_salarial_tss / 4.333333; // Convertir tope mensual a semanal
        $salario_cotizable_final_semanal = min($salario_cotizable_tss, $tope_salarial_semanal);
        
        $deduccion_afp = $salario_cotizable_final_semanal * $porcentaje_afp;
        $deduccion_sfs = $salario_cotizable_final_semanal * $porcentaje_sfs;
        
        $conceptos['DED-AFP'] = ['desc' => 'Aporte AFP (2.87%)', 'monto' => $deduccion_afp, 'tipo' => 'Deducción'];
        $conceptos['DED-SFS'] = ['desc' => 'Aporte SFS (3.04%)', 'monto' => $deduccion_sfs, 'tipo' => 'Deducción'];

        // ... (resto del script: ISR, Guardado, que ya es correcto) ...
    }

    $pdo->commit();
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina_procesada . '&status=processed');
    exit();

} catch (Exception $e) {
    if($pdo->inTransaction()) { $pdo->rollBack(); }
    die("Error Crítico al procesar la nómina: " . $e->getMessage());
}
?>
