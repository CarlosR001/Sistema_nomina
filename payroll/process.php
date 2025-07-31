<?php
// payroll/process.php - v3.0 con Lógica de ISR Acumulativo Mensual

require_once '../auth.php';
require_login();
require_role('Admin');

// --- Helper Function ---
function esUltimaSemanaDelMes($fecha_fin_periodo) {
    $fecha = new DateTime($fecha_fin_periodo);
    $dia = (int)$fecha->format('d');
    $dias_en_mes = (int)$fecha->format('t');
    return ($dias_en_mes - $dia) < 7;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo_id'])) {
    header('Location: ' . BASE_URL . 'payroll/index.php?status=error&message=Solicitud%20inv%C3%A1lida.');
    exit();
}

$periodo_id = $_POST['periodo_id'];
$stmt_periodo = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE id = ?");
$stmt_periodo->execute([$periodo_id]);
$periodo = $stmt_periodo->fetch();

if (!$periodo) {
    header('Location: ' . BASE_URL . 'payroll/index.php?status=error&message=Per%C3%ADodo%20no%20encontrado.');
    exit();
}

$tipo_nomina = $periodo['tipo_nomina'];
$fecha_inicio = $periodo['fecha_inicio_periodo'];
$fecha_fin = $periodo['fecha_fin_periodo'];
$es_ultima_semana = esUltimaSemanaDelMes($fecha_fin);
$mes_actual = date('m', strtotime($fecha_fin));
$anio_actual = date('Y', strtotime($fecha_fin));

try {
    $pdo->beginTransaction();

    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $tope_salarial_tss = (float)($configs_db['TSS_TOPE_SALARIAL'] ?? 265840.00);
    $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287);
    $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304);
    $escala_isr = $pdo->query("SELECT * FROM escalasisr WHERE anio_fiscal = {$anio_actual} ORDER BY desde_monto_anual ASC")->fetchAll();

    // Limpieza de nómina previa...
    $stmt_find_nomina = $pdo->prepare("SELECT id FROM NominasProcesadas WHERE periodo_inicio = ? AND periodo_fin = ? AND tipo_nomina_procesada = ?");
    $stmt_find_nomina->execute([$fecha_inicio, $fecha_fin, $tipo_nomina]);
    $existing_nomina = $stmt_find_nomina->fetch();
    if ($existing_nomina) {
        $pdo->prepare("DELETE FROM NominaDetalle WHERE id_nomina_procesada = ?")->execute([$existing_nomina['id']]);
        $pdo->prepare("DELETE FROM NominasProcesadas WHERE id = ?")->execute([$existing_nomina['id']]);
        $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Pendiente' WHERE periodo_aplicacion BETWEEN ? AND ?")->execute([$fecha_inicio, $fecha_fin]);
    }

    $sql_nomina = "INSERT INTO NominasProcesadas (tipo_nomina_procesada, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES (?, ?, ?, ?, 'Pendiente de Aprobación')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    $stmt_nomina->execute([$tipo_nomina, $fecha_inicio, $fecha_fin, $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();

    $sql_contratos = "SELECT DISTINCT c.id, c.id_empleado, c.tarifa_por_hora FROM Contratos c JOIN RegistroHoras rh ON c.id = rh.id_contrato WHERE c.tipo_nomina = 'Inspectores' AND c.estado_contrato = 'Vigente' AND rh.estado_registro = 'Aprobado' AND rh.fecha_trabajada BETWEEN ? AND ?";
    $stmt_contratos = $pdo->prepare($sql_contratos);
    $stmt_contratos->execute([$fecha_inicio, $fecha_fin]);
    $contratos = $stmt_contratos->fetchAll();

    foreach ($contratos as $contrato) {
        $id_contrato = $contrato['id'];
        $conceptos = [];

        // Lógica de cálculo de horas (sin cambios)
        $feriados_stmt = $pdo->prepare("SELECT fecha FROM CalendarioLaboralRD WHERE fecha BETWEEN ? AND ?");
        $feriados_stmt->execute([$fecha_inicio, $fecha_fin]);
        $dias_feriados = $feriados_stmt->fetchAll(PDO::FETCH_COLUMN);
        $tarifa_hora = (float)$contrato['tarifa_por_hora'];
        $horas_stmt = $pdo->prepare("SELECT fecha_trabajada, hora_inicio, hora_fin FROM RegistroHoras WHERE id_contrato = ? AND estado_registro = 'Aprobado' AND fecha_trabajada BETWEEN ? AND ?");
        $horas_stmt->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
        $registros_horas = $horas_stmt->fetchAll();
        if (empty($registros_horas)) continue;

        $total_horas_laborales = 0; $total_horas_feriado = 0; $total_horas_nocturnas = 0;
        foreach ($registros_horas as $registro) {
            $inicio_dt = new DateTime($registro['fecha_trabajada'] . ' ' . $registro['hora_inicio']);
            $fin_dt = new DateTime($registro['fecha_trabajada'] . ' ' . $registro['hora_fin']);
            if ($fin_dt < $inicio_dt) $fin_dt->modify('+1 day');
            $duracion_total_horas = ($fin_dt->getTimestamp() - $inicio_dt->getTimestamp()) / 3600;
            if (in_array($registro['fecha_trabajada'], $dias_feriados)) $total_horas_feriado += $duracion_total_horas; else $total_horas_laborales += $duracion_total_horas;
            $inicio_nocturno_dia1 = (clone $inicio_dt)->setTime(21, 0); $fin_nocturno_dia1 = (clone $inicio_dt)->setTime(23, 59, 59);
            $inicio_nocturno_dia2 = (clone $inicio_dt)->setTime(0, 0); $fin_nocturno_dia2 = (clone $inicio_dt)->setTime(7, 0);
            $overlap1_start = max($inicio_dt, $inicio_nocturno_dia1); $overlap1_end = min($fin_dt, $fin_nocturno_dia1);
            if ($overlap1_start < $overlap1_end) $total_horas_nocturnas += ($overlap1_end->getTimestamp() - $overlap1_start->getTimestamp()) / 3600;
            $overlap2_start = max($inicio_dt, $inicio_nocturno_dia2); $overlap2_end = min($fin_dt, $fin_nocturno_dia2);
            if ($overlap2_start < $overlap2_end) $total_horas_nocturnas += ($overlap2_end->getTimestamp() - $overlap2_start->getTimestamp()) / 3600;
        }
        $horas_normales = min($total_horas_laborales, 44);
        $horas_extra_35 = max(0, min($total_horas_laborales - 44, 24));
        $horas_extra_100 = max(0, $total_horas_laborales - 68);

        // PASO A: CALCULAR TODOS LOS INGRESOS Y CARGAR NOVEDADES
        $conceptos['ING-HN'] = ['desc' => 'Ingreso Horas Normales', 'monto' => $horas_normales * $tarifa_hora, 'aplica_tss' => true, 'aplica_isr' => true, 'tipo' => 'Ingreso'];
        $conceptos['ING-HE35'] = ['desc' => 'Ingreso Horas Extras 35%', 'monto' => $horas_extra_35 * $tarifa_hora * 1.35, 'aplica_tss' => false, 'aplica_isr' => true, 'tipo' => 'Ingreso'];
        $conceptos['ING-HE100'] = ['desc' => 'Ingreso Horas Extras 100%', 'monto' => $horas_extra_100 * $tarifa_hora * 2.0, 'aplica_tss' => false, 'aplica_isr' => true, 'tipo' => 'Ingreso'];
        $conceptos['ING-HFER'] = ['desc' => 'Ingreso Horas Feriados', 'monto' => $total_horas_feriado * $tarifa_hora * 2.0, 'aplica_tss' => false, 'aplica_isr' => true, 'tipo' => 'Ingreso'];
        $conceptos['ING-HNOT'] = ['desc' => 'Bono Horas Nocturnas', 'monto' => $total_horas_nocturnas * $tarifa_hora * 0.15, 'aplica_tss' => true, 'aplica_isr' => true, 'tipo' => 'Ingreso'];

        $stmt_novedades = $pdo->prepare("SELECT n.*, c.* FROM NovedadesPeriodo n JOIN ConceptosNomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND n.estado_novedad = 'Pendiente' AND n.periodo_aplicacion BETWEEN ? AND ?");
        $stmt_novedades->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
        foreach ($stmt_novedades->fetchAll() as $novedad) { $conceptos[$novedad['codigo_concepto']] = ['desc' => $novedad['descripcion_publica'], 'monto' => (float)$novedad['monto_valor'], 'aplica_tss' => (bool)$novedad['afecta_tss'], 'aplica_isr' => (bool)$novedad['afecta_isr'], 'tipo' => $novedad['tipo_concepto']]; $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Aplicada' WHERE id = ?")->execute([$novedad['id']]); }

        // PASO B: CALCULAR BASE TSS y DEDUCCIONES TSS
        $salario_cotizable_tss = 0;
        foreach ($conceptos as $data) { if ($data['tipo'] === 'Ingreso' && $data['aplica_tss']) { $salario_cotizable_tss += $data['monto']; } }
        $proyeccion_mensual_tss = $salario_cotizable_tss * (52/12);
        $salario_cotizable_final = min($proyeccion_mensual_tss, $tope_salarial_tss);
        $deduccion_afp = ($salario_cotizable_final * $porcentaje_afp) / (52/12);
        $deduccion_sfs = ($salario_cotizable_final * $porcentaje_sfs) / (52/12);
        $conceptos['DED-AFP'] = ['desc' => 'Aporte AFP (2.87%)', 'monto' => $deduccion_afp, 'tipo' => 'Deducción'];
        $conceptos['DED-SFS'] = ['desc' => 'Aporte SFS (3.04%)', 'monto' => $deduccion_sfs, 'tipo' => 'Deducción'];

        // PASO C y D: CALCULAR BASE ISR Y LA DEDUCCIÓN (SOLO EN LA ÚLTIMA SEMANA)
        $deduccion_isr = 0;
        if ($es_ultima_semana) {
            // C.1: Calcular la base ISR de la semana actual
            $base_isr_semana_actual = 0;
            foreach ($conceptos as $data) { if ($data['tipo'] === 'Ingreso' && $data['aplica_isr']) { $base_isr_semana_actual += $data['monto']; } }
            $base_isr_semana_actual -= ($deduccion_afp + $deduccion_sfs);

            // C.2: Buscar y sumar las bases ISR de semanas anteriores en el mismo mes
            $sql_prev_nominas = "SELECT nd.monto_resultado FROM NominaDetalle nd JOIN NominasProcesadas np ON nd.id_nomina_procesada = np.id WHERE np.id_empleado = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto LIKE 'BASE-ISR-SEMANAL'";
            $stmt_prev_nominas = $pdo->prepare($sql_prev_nominas);
            $stmt_prev_nominas->execute([$contrato['id_empleado'], $mes_actual, $anio_actual]);
            $base_isr_acumulada_previa = (float)$stmt_prev_nominas->fetchColumn();

            // C.3: Consolidar la base ISR del mes completo
            $base_isr_mensual_total = $base_isr_semana_actual + $base_isr_acumulada_previa;

            // D.1: Calcular el ISR mensual
            $ingreso_anual_proyectado = $base_isr_mensual_total * 12;
            $isr_anual = 0;
            foreach ($escala_isr as $tramo) {
                if ($ingreso_anual_proyectado >= $tramo['desde_monto_anual'] && ($tramo['hasta_monto_anual'] === null || $ingreso_anual_proyectado <= $tramo['hasta_monto_anual'])) {
                    $excedente = $ingreso_anual_proyectado - $tramo['desde_monto_anual'];
                    $isr_anual = ($excedente * ($tramo['tasa_porcentaje']/100)) + $tramo['monto_fijo_adicional']; // Tasa es %
                    break;
                }
            }
            $deduccion_isr = max(0, $isr_anual / 12);
        }
        
        // Se guarda el resultado, sea 0 o el cálculo mensual.
        $conceptos['DED-ISR'] = ['desc' => 'Impuesto Sobre la Renta (ISR)', 'monto' => $deduccion_isr, 'tipo' => 'Deducción'];

        // PASO E: GUARDAR TODO
        $sql_detalle = "INSERT INTO NominaDetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detalle = $pdo->prepare($sql_detalle);
        foreach ($conceptos as $codigo => $data) {
            if ($data['monto'] > 0.001) {
                $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $codigo, $data['desc'], $data['tipo'], $data['monto']]);
            }
        }
    }

    $stmt_cerrar = $pdo->prepare("UPDATE PeriodosDeReporte SET estado_periodo = 'Cerrado' WHERE id = ?");
    $stmt_cerrar->execute([$periodo_id]);

    $pdo->commit();
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina_procesada . '&status=processed');
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error Crítico al procesar la nómina: " . $e->getMessage());
}
?>
