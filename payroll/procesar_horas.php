<?php
// payroll/procesar_horas.php - v3.1 (ESTABLE Y COMPLETO)
// Combina el motor de cálculo funcional (v2.8) con la lógica de borrado selectivo.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

// --- Funciones de ayuda (si las necesitamos en el futuro) ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo_id'], $_POST['mode'])) {
    header('Location: generar_novedades.php?status=error&message=' . urlencode('Solicitud no válida.'));
    exit();
}
$periodo_id = $_POST['periodo_id'];
$mode = $_POST['mode'];

try {
    if ($mode === 'final') $pdo->beginTransaction();

    $stmt_periodo = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE id = ?");
    $stmt_periodo->execute([$periodo_id]);
    $periodo = $stmt_periodo->fetch();
    if (!$periodo) throw new Exception("Período no válido.");
    $fecha_inicio = $periodo['fecha_inicio_periodo'];
    $fecha_fin = $periodo['fecha_fin_periodo'];

    if ($mode === 'preview') {
        $stmt_check_pendientes = $pdo->prepare("SELECT COUNT(id) FROM RegistroHoras WHERE estado_registro IN ('Pendiente', 'Rechazado') AND fecha_trabajada BETWEEN ? AND ?");
        $stmt_check_pendientes->execute([$fecha_inicio, $fecha_fin]);
        $_SESSION['pending_hours_check'] = $stmt_check_pendientes->fetchColumn();
    }
    
    $feriados_stmt = $pdo->prepare("SELECT fecha FROM CalendarioLaboralRD WHERE fecha BETWEEN ? AND ?");
    $feriados_stmt->execute([$fecha_inicio, $fecha_fin]);
    $feriados = $feriados_stmt->fetchAll(PDO::FETCH_COLUMN);
    $conceptos = $pdo->query("SELECT codigo_concepto, id FROM ConceptosNomina")->fetchAll(PDO::FETCH_KEY_PAIR);
    $zonas = $pdo->query("SELECT id, monto_transporte_completo FROM ZonasTransporte")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $sql_horas = "SELECT c.id as contrato_id, c.tarifa_por_hora, e.nombres, e.primer_apellido, rh.fecha_trabajada, rh.hora_inicio, rh.hora_fin, rh.id_zona_trabajo, rh.transporte_aprobado FROM Contratos c JOIN Empleados e ON c.id_empleado = e.id JOIN RegistroHoras rh ON c.id = rh.id_contrato WHERE c.tipo_nomina = 'Inspectores' AND rh.estado_registro = 'Aprobado' AND rh.fecha_trabajada BETWEEN ? AND ? ORDER BY c.id, rh.fecha_trabajada, rh.hora_inicio";
    $stmt_horas = $pdo->prepare($sql_horas);
    $stmt_horas->execute([$fecha_inicio, $fecha_fin]);
    $horas_aprobadas = $stmt_horas->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    
    $results = [];

    foreach ($horas_aprobadas as $contrato_id => $registros) {
        $tarifa_hora = (float)$registros[0]['tarifa_por_hora'];
        $total_horas_feriado = 0; $total_horas_laborales = 0; $total_horas_nocturnas_bono = 0;
        $zonas_trabajadas_por_dia = [];

        foreach ($registros as $reg) {
            $inicio = new DateTime($reg['hora_inicio']);
            $fin = new DateTime($reg['hora_fin']);
            if ($fin <= $inicio) $fin->modify('+1 day');
            $duracion_horas = round(($fin->getTimestamp() - $inicio->getTimestamp()) / 3600, 2);

            if (in_array($reg['fecha_trabajada'], $feriados)) { 
                $total_horas_feriado += $duracion_horas; 
            } else { 
                $total_horas_laborales += $duracion_horas; 
            }
            
            $inicio_H = (int)$inicio->format('H');
            $fin_H = (int)$fin->format('H');
            $fin_M = (int)$fin->format('i');
            if ($fin_H == 0 && $fin_M == 0 && $fin > $inicio) { $fin_H = 24; }
            $es_diurno = ($inicio_H >= 7 && $fin_H <= 21);
            if (!$es_diurno) { $total_horas_nocturnas_bono += $duracion_horas; }

            if ($reg['transporte_aprobado']) {
                $zonas_trabajadas_por_dia[$reg['fecha_trabajada']][] = $reg['id_zona_trabajo'];
            }
        }

        $pago_feriado = $total_horas_feriado * $tarifa_hora * 2.0;
        $horas_normales = min($total_horas_laborales, 44);
        $pago_normal = $horas_normales * $tarifa_hora;
        $horas_extra_35 = max(0, min($total_horas_laborales - 44, 24));
        $pago_extra_35 = $horas_extra_35 * $tarifa_hora * 1.35;
        $horas_extra_100 = max(0, $total_horas_laborales - 68);
        $pago_extra_100 = $horas_extra_100 * $tarifa_hora * 2.0;
        $pago_extra_total = $pago_extra_35 + $pago_extra_100;
        $pago_nocturno = $total_horas_nocturnas_bono * $tarifa_hora * 0.15;
        $pago_transporte = 0;
        foreach ($zonas_trabajadas_por_dia as $zonas_dia) {
            $zonas_unicas = array_unique($zonas_dia);
            if (empty($zonas_unicas)) continue;
            $pago_transporte += (float)($zonas[array_shift($zonas_unicas)] ?? 0);
            foreach ($zonas_unicas as $zona_id) { $pago_transporte += (float)($zonas[$zona_id] ?? 0) * 0.5; }
        }
        
        $results[$contrato_id] = [
            'nombre_empleado' => $registros[0]['nombres'] . ' ' . $registros[0]['primer_apellido'],
            'pago_normal' => round($pago_normal, 2), 'pago_extra' => round($pago_extra_total, 2),
            'pago_feriado' => round($pago_feriado, 2), 'pago_nocturno' => round($pago_nocturno, 2),
            'pago_transporte' => round($pago_transporte, 2),
            'ingreso_bruto' => round($pago_normal + $pago_extra_total + $pago_feriado + $pago_nocturno + $pago_transporte, 2)
        ];
    }

    if ($mode === 'preview') {
        $_SESSION['preview_results'] = $results;
        $_SESSION['preview_period_id'] = $periodo_id;
        header('Location: generar_novedades.php');
        exit();
    } 
    elseif ($mode === 'final') {
        // **INICIO DE LA LÓGICA DE BORRADO Y RE-INSERCIÓN SEGURA**
        
        // 1. Identificar los contratos que se van a procesar en este lote.
        $contratos_a_procesar = array_keys($results);
        if (empty($contratos_a_procesar)) {
            if ($pdo->inTransaction()) $pdo->commit(); // Asegurarse de cerrar la transacción si no hay nada que hacer.
            header('Location: generar_novedades.php?status=info&message=' . urlencode('No se encontraron horas aprobadas para generar novedades.'));
            exit();
        }
        $contratos_placeholders = implode(',', array_fill(0, count($contratos_a_procesar), '?'));

        // 2. Rescatar (guardar en memoria) TODAS las novedades MANUALES existentes para estos contratos en este período.
        $codigos_automaticos = ['ING-NORMAL', 'ING-EXTRA', 'ING-FERIADO', 'ING-NOCTURNO', 'ING-TRANSP'];
        $codigos_auto_placeholders = implode(',', array_fill(0, count($codigos_automaticos), '?'));
        
        $sql_rescate = "SELECT np.id_contrato, np.id_concepto, np.periodo_aplicacion, np.monto_valor FROM NovedadesPeriodo np JOIN ConceptosNomina cn ON np.id_concepto = cn.id WHERE np.id_contrato IN ($contratos_placeholders) AND np.periodo_aplicacion BETWEEN ? AND ? AND cn.codigo_concepto NOT IN ($codigos_auto_placeholders)";
        
        $stmt_rescate = $pdo->prepare($sql_rescate);
        $params_rescate = array_merge($contratos_a_procesar, [$fecha_inicio, $fecha_fin], $codigos_automaticos);
        $stmt_rescate->execute($params_rescate);
        $novedades_manuales_rescatadas = $stmt_rescate->fetchAll(PDO::FETCH_ASSOC);

        // 3. Borrar TODAS las novedades (automáticas Y manuales) de los empleados procesados en este período.
        // Esto limpia el "pizarrón" y previene duplicados.
        $sql_delete_total = "DELETE FROM NovedadesPeriodo WHERE id_contrato IN ($contratos_placeholders) AND periodo_aplicacion BETWEEN ? AND ?";
        $stmt_delete_total = $pdo->prepare($sql_delete_total);
        $stmt_delete_total->execute(array_merge($contratos_a_procesar, [$fecha_inicio, $fecha_fin]));

        // 4. Insertar las novedades recién calculadas a partir de las horas.
        $stmt_insert = $pdo->prepare("INSERT INTO NovedadesPeriodo (id_contrato, id_concepto, periodo_aplicacion, monto_valor, estado_novedad) VALUES (?, ?, ?, ?, 'Pendiente')");
        foreach ($results as $contrato_id => $res) {
            // Usamos la fecha de inicio del período para consistencia con los reportes.
            if ($res['pago_normal'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-NORMAL'], $fecha_inicio, $res['pago_normal']]);
            if ($res['pago_extra'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-EXTRA'], $fecha_inicio, $res['pago_extra']]);
            if ($res['pago_feriado'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-FERIADO'], $fecha_inicio, $res['pago_feriado']]);
            if ($res['pago_nocturno'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-NOCTURNO'], $fecha_inicio, $res['pago_nocturno']]);
            if ($res['pago_transporte'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-TRANSP'], $fecha_inicio, $res['pago_transporte']]);
        }

        // 5. Re-insertar las novedades manuales que fueron rescatadas.
        foreach ($novedades_manuales_rescatadas as $novedad_manual) {
            $stmt_insert->execute([
                $novedad_manual['id_contrato'],
                $novedad_manual['id_concepto'],
                $novedad_manual['periodo_aplicacion'], // Se preserva la fecha original de la novedad manual
                $novedad_manual['monto_valor'],
            ]);
        }
        
        // **FIN DE LA LÓGICA DE BORRADO Y RE-INSERCIÓN SEGURA**
        
        $pdo->commit();
        header('Location: generar_novedades.php?status=success&message=' . urlencode('Proceso completado. Las novedades de horas han sido generadas y las novedades manuales han sido preservadas.'));
        exit();
    }

    }
 catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    header('Location: generar_novedades.php?status=error&message=' . urlencode('Error crítico: ' . $e->getMessage()));
    exit();
}
?>
