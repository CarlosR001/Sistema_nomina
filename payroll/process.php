<?php
// payroll/process.php

require_once '../auth.php';
require_login();
require_role('Admin');

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

try {
    $pdo->beginTransaction();

    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $tope_salarial_tss = (float)($configs_db['TSS_TOPE_SALARIAL'] ?? 265840.00);
    $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287);
    $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304);
    $isr_exento_anual = (float)($configs_db['ISR_EXENTO_ANUAL'] ?? 416220.00);

    // Limpiar nómina previa si se está reprocesando
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

    $sql_contratos = ($tipo_nomina === 'Inspectores')
        ? "SELECT DISTINCT c.id, c.id_empleado, c.tarifa_por_hora FROM Contratos c JOIN RegistroHoras rh ON c.id = rh.id_contrato WHERE c.tipo_nomina = 'Inspectores' AND c.estado_contrato = 'Vigente' AND rh.estado_registro = 'Aprobado' AND rh.fecha_trabajada BETWEEN ? AND ?"
        : "SELECT id, id_empleado, salario_mensual_bruto FROM Contratos WHERE tipo_nomina = 'Administrativa' AND estado_contrato = 'Vigente'";
    
    $stmt_contratos = $pdo->prepare($sql_contratos);
    if ($tipo_nomina === 'Inspectores') $stmt_contratos->execute([$fecha_inicio, $fecha_fin]);
    else $stmt_contratos->execute();
    $contratos = $stmt_contratos->fetchAll();

    foreach ($contratos as $contrato) {
        $id_contrato = $contrato['id'];
        $ingresos = [];
        $deducciones = [];
        $bases_calculo = [];

        if ($tipo_nomina === 'Inspectores') {
            $feriados_stmt = $pdo->prepare("SELECT fecha FROM CalendarioLaboralRD WHERE fecha BETWEEN ? AND ?");
            $feriados_stmt->execute([$fecha_inicio, $fecha_fin]);
            $dias_feriados = $feriados_stmt->fetchAll(PDO::FETCH_COLUMN);
            $tarifas_transporte = $pdo->query("SELECT id, monto_transporte_completo FROM ZonasTransporte")->fetchAll(PDO::FETCH_KEY_PAIR);
            $tarifa_hora = (float)$contrato['tarifa_por_hora'];
            $horas_stmt = $pdo->prepare("SELECT fecha_trabajada, hora_inicio, hora_fin, id_zona_trabajo FROM RegistroHoras WHERE id_contrato = ? AND estado_registro = 'Aprobado' AND fecha_trabajada BETWEEN ? AND ?");
            $horas_stmt->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
            $registros_horas = $horas_stmt->fetchAll();

            if (empty($registros_horas)) continue;

            $total_horas_laborales = 0; $total_horas_feriado = 0; $total_horas_nocturnas = 0; $zonas_por_dia = [];
            foreach ($registros_horas as $registro) {
                $inicio_dt = new DateTime($registro['fecha_trabajada'] . ' ' . $registro['hora_inicio']);
                $fin_dt = new DateTime($registro['fecha_trabajada'] . ' ' . $registro['hora_fin']);
                if ($fin_dt < $inicio_dt) $fin_dt->modify('+1 day');
                $duracion_total_horas = ($fin_dt->getTimestamp() - $inicio_dt->getTimestamp()) / 3600;

                if (in_array($registro['fecha_trabajada'], $dias_feriados)) $total_horas_feriado += $duracion_total_horas;
                else $total_horas_laborales += $duracion_total_horas;

                $inicio_nocturno_dia1 = (clone $inicio_dt)->setTime(21, 0); $fin_nocturno_dia1 = (clone $inicio_dt)->setTime(23, 59, 59);
                $inicio_nocturno_dia2 = (clone $inicio_dt)->setTime(0, 0); $fin_nocturno_dia2 = (clone $inicio_dt)->setTime(7, 0);
                $overlap1_start = max($inicio_dt, $inicio_nocturno_dia1); $overlap1_end = min($fin_dt, $fin_nocturno_dia1);
                if ($overlap1_start < $overlap1_end) $total_horas_nocturnas += ($overlap1_end->getTimestamp() - $overlap1_start->getTimestamp()) / 3600;
                $overlap2_start = max($inicio_dt, $inicio_nocturno_dia2); $overlap2_end = min($fin_dt, $fin_nocturno_dia2);
                if ($overlap2_start < $overlap2_end) $total_horas_nocturnas += ($overlap2_end->getTimestamp() - $overlap2_start->getTimestamp()) / 3600;
                
                $zonas_por_dia[$registro['fecha_trabajada']][$registro['id_zona_trabajo']] = true;
            }

            $horas_normales = min($total_horas_laborales, 44);
            $horas_extra_35 = max(0, min($total_horas_laborales - 44, 24));
            $horas_extra_100 = max(0, $total_horas_laborales - 68);
            $pago_transporte = 0;
            foreach ($zonas_por_dia as $zonas) { $ids_zonas = array_keys($zonas); if (!empty($ids_zonas)) { $pago_transporte += $tarifas_transporte[$ids_zonas[0]] ?? 0; if (count($ids_zonas) > 1) { for ($i = 1; $i < count($ids_zonas); $i++) { $pago_transporte += ($tarifas_transporte[$ids_zonas[$i]] ?? 0) * 0.5; } } } }

            $bases_calculo[] = ['BASE-H-NORM', 'Horas Normales', 'Base de Cálculo', $horas_normales];
            $bases_calculo[] = ['BASE-H-EXT35', 'Horas Extras (35%)', 'Base de Cálculo', $horas_extra_35];
            $bases_calculo[] = ['BASE-H-EXT100', 'Horas Extras (100%)', 'Base de Cálculo', $horas_extra_100];
            $bases_calculo[] = ['BASE-H-FER', 'Horas Feriado', 'Base de Cálculo', $total_horas_feriado];
            $bases_calculo[] = ['BASE-H-NOCT', 'Horas Nocturnas (Plus)', 'Base de Cálculo', $total_horas_nocturnas];
            $bases_calculo[] = ['BASE-H-TOTAL', 'Total Horas Periodo', 'Base de Cálculo', $total_horas_laborales + $total_horas_feriado];

            $ingresos[] = ['ING-HN', 'Ingreso Horas Normales', 'Ingreso', $horas_normales * $tarifa_hora];
            $ingresos[] = ['ING-HE35', 'Ingreso Horas Extras 35%', 'Ingreso', $horas_extra_35 * $tarifa_hora * 1.35];
            $ingresos[] = ['ING-HE100', 'Ingreso Horas Extras 100%', 'Ingreso', $horas_extra_100 * $tarifa_hora * 2.0];
            $ingresos[] = ['ING-HFER', 'Ingreso Horas Feriados', 'Ingreso', $total_horas_feriado * $tarifa_hora * 2.0];
            $ingresos[] = ['ING-HNOT', 'Bono Horas Nocturnas', 'Ingreso', $total_horas_nocturnas * $tarifa_hora * 0.15];
            $ingresos[] = ['ING-TRANSP', 'Pago de Transporte', 'Ingreso', $pago_transporte];
        } else {
            $salario_mensual = (float)$contrato['salario_mensual_bruto'];
            $ingresos[] = ['ING-SALARIO', 'Salario Base Quincenal', 'Ingreso', $salario_mensual / 2];
        }

        $stmt_novedades = $pdo->prepare("SELECT n.*, c.descripcion_publica, c.codigo_concepto, c.tipo_concepto FROM NovedadesPeriodo n JOIN ConceptosNomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND n.estado_novedad = 'Pendiente' AND n.periodo_aplicacion BETWEEN ? AND ?");
        $stmt_novedades->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
        foreach ($stmt_novedades->fetchAll() as $novedad) {
            if ($novedad['tipo_concepto'] === 'Ingreso') {
                $ingresos[] = [$novedad['codigo_concepto'], $novedad['descripcion_publica'], 'Ingreso', (float)$novedad['monto_valor']];
            } else {
                $deducciones[] = [$novedad['codigo_concepto'], $novedad['descripcion_publica'], 'Deducción', (float)$novedad['monto_valor']];
            }
            $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Aplicada' WHERE id = ?")->execute([$novedad['id']]);
        }

        $total_ingresos_bruto = 0; $salario_para_tss = 0;
        foreach ($ingresos as $ingreso) { $total_ingresos_bruto += $ingreso[3]; if (!in_array($ingreso[0], ['ING-TRANSP'])) { $salario_para_tss += $ingreso[3]; } }
        
        if ($total_ingresos_bruto <= 0) continue;
        
        $proyeccion_mensual_tss = $salario_para_tss * (52/12);
        $salario_cotizable_tss_final = min($proyeccion_mensual_tss, $tope_salarial_tss);
        $deduccion_afp = ($salario_cotizable_tss_final * $porcentaje_afp) / (52/12);
        $deduccion_sfs = ($salario_cotizable_tss_final * $porcentaje_sfs) / (52/12);
        
        $deducciones[] = ['DED-AFP', 'Aporte AFP (2.87%)', 'Deducción', $deduccion_afp];
        $deducciones[] = ['DED-SFS', 'Aporte SFS (3.04%)', 'Deducción', $deduccion_sfs];

        $total_deducciones_previas = 0;
        foreach($deducciones as $ded) { $total_deducciones_previas += $ded[3];}

        $base_para_isr = $total_ingresos_bruto - $total_deducciones_previas;
        $ingreso_anual_proyectado = $base_para_isr * 52;
        $deduccion_isr = 0;
        
        if ($ingreso_anual_proyectado > $isr_exento_anual) {
            if ($ingreso_anual_proyectado <= 624329) { $isr_anual = ($ingreso_anual_proyectado - 416220.01) * 0.15; }
            elseif ($ingreso_anual_proyectado <= 867123) { $isr_anual = 31216.00 + ($ingreso_anual_proyectado - 624329.01) * 0.20; }
            else { $isr_anual = 79776.00 + ($ingreso_anual_proyectado - 867123.01) * 0.25; }
            $deduccion_isr = max(0, $isr_anual / 52);
        }
        $deducciones[] = ['DED-ISR', 'Impuesto Sobre la Renta (ISR)', 'Deducción', $deduccion_isr];

        $todos_los_conceptos = array_merge($bases_calculo, $ingresos, $deducciones);
        $sql_detalle = "INSERT INTO NominaDetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detalle = $pdo->prepare($sql_detalle);
        foreach ($todos_los_conceptos as $item) {
            if ($item[3] > 0.001) {
                $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $item[0], $item[1], $item[2], $item[3]]);
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
