<?php
// payroll/procesar_horas.php - v2.2 (Lógica Nocturna Corregida)
// Implementa la regla de negocio correcta para el cálculo del bono nocturno.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

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

    $feriados_stmt = $pdo->prepare("SELECT fecha FROM CalendarioLaboralRD WHERE fecha BETWEEN ? AND ?");
    $feriados_stmt->execute([$fecha_inicio, $fecha_fin]);
    $feriados = $feriados_stmt->fetchAll(PDO::FETCH_COLUMN);
    $conceptos = $pdo->query("SELECT codigo_concepto, id FROM ConceptosNomina")->fetchAll(PDO::FETCH_KEY_PAIR);
    $zonas = $pdo->query("SELECT id, monto_transporte_completo FROM ZonasTransporte")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $sql_horas = "SELECT c.id as contrato_id, c.tarifa_por_hora, e.nombres, e.primer_apellido, rh.fecha_trabajada, rh.hora_inicio, rh.hora_fin, rh.id_zona_trabajo FROM Contratos c JOIN Empleados e ON c.id_empleado = e.id JOIN RegistroHoras rh ON c.id = rh.id_contrato WHERE c.tipo_nomina = 'Inspectores' AND rh.estado_registro = 'Aprobado' AND rh.fecha_trabajada BETWEEN ? AND ? ORDER BY c.id, rh.fecha_trabajada, rh.hora_inicio";
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
            $duracion_horas = ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;

            if (in_array($reg['fecha_trabajada'], $feriados)) { 
                $total_horas_feriado += $duracion_horas; 
            } else { 
                $total_horas_laborales += $duracion_horas; 
            }
            
            // --- LÓGICA DE HORAS NOCTURNAS CORREGIDA ---
            // Si el turno toca el horario nocturno, la duración completa del turno cuenta para el bono.
            $inicio_H = (int)$inicio->format('H');
            $fin_H = (int)$fin->format('H');
            $es_nocturno = false;
            if ($inicio < $fin) { // Turno dentro del mismo día
                 if ($inicio_H >= 21 || $fin_H <= 7) $es_nocturno = true;
            } else { // Turno que cruza la medianoche
                $es_nocturno = true;
            }
            if ($es_nocturno) {
                $total_horas_nocturnas_bono += $duracion_horas;
            }
            // --- FIN DE LA CORRECCIÓN ---

            $zonas_trabajadas_por_dia[$reg['fecha_trabajada']][] = $reg['id_zona_trabajo'];
        }

        $pago_feriado = $total_horas_feriado * $tarifa_hora * 2.0;
        $horas_normales = min($total_horas_laborales, 44);
        $pago_normal = $horas_normales * $tarifa_hora;
        $horas_extra_35 = max(0, min($total_horas_laborales - 44, 24));
        $pago_extra_35 = $horas_extra_35 * $tarifa_hora * 1.35;
        $horas_extra_100 = max(0, $total_horas_laborales - 68);
        $pago_extra_100 = $horas_extra_100 * $tarifa_hora * 2.0;
        $pago_extra_total = $pago_extra_35 + $pago_extra_100;
        $pago_nocturno = $total_horas_nocturnas_bono * $tarifa_hora * 0.15; // Usar el nuevo total
        $pago_transporte = 0;
        foreach ($zonas_trabajadas_por_dia as $zonas_dia) {
            $zonas_unicas = array_unique($zonas_dia);
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
        $codigos_a_borrar = "'ING-NORMAL', 'ING-EXTRA', 'ING-FERIADO', 'ING-NOCTURNO', 'ING-TRANSP'";
        $stmt_delete = $pdo->prepare("DELETE n FROM NovedadesPeriodo n JOIN ConceptosNomina c ON n.id_concepto = c.id WHERE n.periodo_aplicacion = ? AND c.codigo_concepto IN ($codigos_a_borrar)");
        $stmt_delete->execute([$fecha_inicio]);
        $stmt_insert = $pdo->prepare("INSERT INTO NovedadesPeriodo (id_contrato, id_concepto, periodo_aplicacion, monto_valor, estado_novedad) VALUES (?, ?, ?, ?, 'Pendiente')");
        foreach ($results as $contrato_id => $res) {
            if ($res['pago_normal'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-NORMAL'], $fecha_inicio, $res['pago_normal']]);
            if ($res['pago_extra'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-EXTRA'], $fecha_inicio, $res['pago_extra']]);
            if ($res['pago_feriado'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-FERIADO'], $fecha_inicio, $res['pago_feriado']]);
            if ($res['pago_nocturno'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-NOCTURNO'], $fecha_inicio, $res['pago_nocturno']]);
            if ($res['pago_transporte'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-TRANSP'], $fecha_inicio, $res['pago_transporte']]);
        }
        $pdo->commit();
        header('Location: generar_novedades.php?status=success&message=' . urlencode('Proceso completado. Se generaron las novedades de ingreso.'));
        exit();
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    header('Location: generar_novedades.php?status=error&message=' . urlencode('Error crítico: ' . $e->getMessage()));
    exit();
}
?>
