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
    $tope_salarial_tss = (float)($configs_db['TSS_TOPE_SALARIAL'] ?? 265840.00); // Valor por defecto
    $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287);
    $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304);
    $isr_exento_anual = (float)($configs_db['ISR_EXENTO_ANUAL'] ?? 416220.00);

    // Limpiar detalles de nóminas previas para este período si se está reprocesando
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

    if ($tipo_nomina === 'Inspectores') {
        $sql_contratos = "SELECT DISTINCT c.id, c.id_empleado, c.tarifa_por_hora FROM Contratos c JOIN RegistroHoras rh ON c.id = rh.id_contrato WHERE c.tipo_nomina = 'Inspectores' AND c.estado_contrato = 'Vigente' AND rh.estado_registro = 'Aprobado' AND rh.fecha_trabajada BETWEEN ? AND ?";
        $stmt_contratos = $pdo->prepare($sql_contratos);
        $stmt_contratos->execute([$fecha_inicio, $fecha_fin]);
        $contratos = $stmt_contratos->fetchAll();
    } else {
        $sql_contratos = "SELECT id, id_empleado, salario_mensual_bruto FROM Contratos WHERE tipo_nomina = 'Administrativa' AND estado_contrato = 'Vigente'";
        $contratos = $pdo->query($sql_contratos)->fetchAll();
    }

    foreach ($contratos as $contrato) {
        $id_contrato = $contrato['id'];
        $ingresos = [];

        if ($tipo_nomina === 'Inspectores') {
            $feriados_stmt = $pdo->prepare("SELECT fecha FROM CalendarioLaboralRD WHERE fecha BETWEEN ? AND ?");
            $feriados_stmt->execute([$fecha_inicio, $fecha_fin]);
            $dias_feriados = $feriados_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $tarifas_transporte_stmt = $pdo->query("SELECT id, monto_transporte_completo FROM ZonasTransporte");
            $tarifas_transporte = $tarifas_transporte_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $tarifa_hora = (float)$contrato['tarifa_por_hora'];

            $horas_stmt = $pdo->prepare("SELECT fecha_trabajada, hora_inicio, hora_fin, id_zona_trabajo FROM RegistroHoras WHERE id_contrato = ? AND estado_registro = 'Aprobado' AND fecha_trabajada BETWEEN ? AND ?");
            $horas_stmt->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
            $registros_horas = $horas_stmt->fetchAll();

            if (empty($registros_horas)) continue;

            $total_horas_laborales = 0;
            $total_horas_feriado = 0;
            $total_horas_nocturnas = 0;
            $zonas_por_dia = [];

            foreach ($registros_horas as $registro) {
                $inicio_dt = new DateTime($registro['fecha_trabajada'] . ' ' . $registro['hora_inicio']);
                $fin_dt = new DateTime($registro['fecha_trabajada'] . ' ' . $registro['hora_fin']);
                
                if ($fin_dt < $inicio_dt) {
                    $fin_dt->modify('+1 day');
                }

                $duracion_total_horas = ($fin_dt->getTimestamp() - $inicio_dt->getTimestamp()) / 3600;

                if (in_array($registro['fecha_trabajada'], $dias_feriados)) {
                    $total_horas_feriado += $duracion_total_horas;
                } else {
                    $total_horas_laborales += $duracion_total_horas;
                }
                
                $inicio_nocturno_dia1 = (clone $inicio_dt)->setTime(21, 0);
                $fin_nocturno_dia1 = (clone $inicio_dt)->setTime(23, 59, 59);

                $inicio_nocturno_dia2 = (clone $inicio_dt)->setTime(0, 0);
                $fin_nocturno_dia2 = (clone $inicio_dt)->setTime(7, 0);

                // Nocturnas del primer día (21:00 a 23:59:59)
                $overlap1_start = max($inicio_dt, $inicio_nocturno_dia1);
                $overlap1_end = min($fin_dt, $fin_nocturno_dia1);
                if ($overlap1_start < $overlap1_end) {
                    $total_horas_nocturnas += ($overlap1_end->getTimestamp() - $overlap1_start->getTimestamp()) / 3600;
                }

                // Nocturnas del segundo día (00:00 a 07:00)
                $overlap2_start = max($inicio_dt, $inicio_nocturno_dia2);
                $overlap2_end = min($fin_dt, $fin_nocturno_dia2);
                if ($overlap2_start < $overlap2_end) {
                    $total_horas_nocturnas += ($overlap2_end->getTimestamp() - $overlap2_start->getTimestamp()) / 3600;
                }
                
                $zonas_por_dia[$registro['fecha_trabajada']][$registro['id_zona_trabajo']] = true;
            }

            $horas_normales = min($total_horas_laborales, 44);
            $horas_extra_35 = max(0, min($total_horas_laborales - 44, 24));
            $horas_extra_100 = max(0, $total_horas_laborales - 68);

            $pago_transporte = 0;
            foreach ($zonas_por_dia as $zonas) {
                $ids_zonas = array_keys($zonas);
                if (!empty($ids_zonas)) {
                    $pago_transporte += $tarifas_transporte[$ids_zonas[0]] ?? 0;
                    if (count($ids_zonas) > 1) {
                        for ($i = 1; $i < count($ids_zonas); $i++) {
                            $pago_transporte += ($tarifas_transporte[$ids_zonas[$i]] ?? 0) * 0.5;
                        }
                    }
                }
            }

            $ingresos[] = ['ING-HN', 'Ingreso Horas Normales', $horas_normales * $tarifa_hora];
            $ingresos[] = ['ING-HE35', 'Ingreso Horas Extras 35%', $horas_extra_35 * $tarifa_hora * 1.35];
            $ingresos[] = ['ING-HE100', 'Ingreso Horas Extras 100%', $horas_extra_100 * $tarifa_hora * 2.0];
            $ingresos[] = ['ING-HFER', 'Ingreso Horas Feriados', $total_horas_feriado * $tarifa_hora * 2.0];
            $ingresos[] = ['ING-HNOT', 'Bono Horas Nocturnas', $total_horas_nocturnas * $tarifa_hora * 0.15];
            $ingresos[] = ['ING-TRANSP', 'Pago de Transporte', $pago_transporte];
        } else {
            $salario_mensual = (float)$contrato['salario_mensual_bruto'];
            $ingresos[] = ['ING-SALARIO', 'Salario Base Quincenal', $salario_mensual / 2];
        }

        $stmt_novedades = $pdo->prepare("SELECT n.*, c.descripcion_publica, c.codigo_concepto FROM NovedadesPeriodo n JOIN ConceptosNomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND n.estado_novedad = 'Pendiente' AND n.periodo_aplicacion BETWEEN ? AND ?");
        $stmt_novedades->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
        foreach ($stmt_novedades->fetchAll() as $novedad) {
            $ingresos[] = [$novedad['codigo_concepto'], $novedad['descripcion_publica'], (float)$novedad['monto_valor']];
            $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Aplicada' WHERE id = ?")->execute([$novedad['id']]);
        }

        $total_ingresos_bruto = 0;
        $salario_para_tss = 0;
        foreach ($ingresos as $ingreso) {
            $monto = $ingreso[2];
            $total_ingresos_bruto += $monto;
            if (!in_array($ingreso[0], ['ING-TRANSP'])) {
                $salario_para_tss += $monto;
            }
        }

        if ($total_ingresos_bruto <= 0) continue;

        $deducciones = [];
        $proyeccion_mensual_tss = $salario_para_tss * (52/12);
        $salario_cotizable_tss_final = min($proyeccion_mensual_tss, $tope_salarial_tss);
        
        $deduccion_afp_mensual = $salario_cotizable_tss_final * $porcentaje_afp;
        $deduccion_sfs_mensual = $salario_cotizable_tss_final * $porcentaje_sfs;

        $deduccion_afp = $deduccion_afp_mensual / (52/12);
        $deduccion_sfs = $deduccion_sfs_mensual / (52/12);

        $deducciones[] = ['DED-AFP', 'Aporte AFP (2.87%)', $deduccion_afp];
        $deducciones[] = ['DED-SFS', 'Aporte SFS (3.04%)', $deduccion_sfs];

        $base_para_isr = $total_ingresos_bruto - ($deduccion_afp + $deduccion_sfs);
        $ingreso_anual_proyectado = $base_para_isr * 52;
        $deduccion_isr = 0;
        
        if ($ingreso_anual_proyectado > $isr_exento_anual) {
            if ($ingreso_anual_proyectado <= 624329) {
                $isr_anual = ($ingreso_anual_proyectado - 416220.01) * 0.15;
            } elseif ($ingreso_anual_proyectado <= 867123) {
                $isr_anual = 31216.00 + ($ingreso_anual_proyectado - 624329.01) * 0.20;
            } else {
                $isr_anual = 79776.00 + ($ingreso_anual_proyectado - 867123.01) * 0.25;
            }
            $deduccion_isr = max(0, $isr_anual / 52);
        }
        $deducciones[] = ['DED-ISR', 'Impuesto Sobre la Renta (ISR)', $deduccion_isr];

        $todos_los_conceptos = array_merge($ingresos, $deducciones);
        $sql_detalle = "INSERT INTO NominaDetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detalle = $pdo->prepare($sql_detalle);
        foreach ($todos_los_conceptos as $item) {
            if ($item[2] > 0.01) {
                $tipo = (strpos($item[0], 'DED-') === 0) ? 'Deducción' : 'Ingreso';
                $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $item[0], $item[1], $tipo, $item[2]]);
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
