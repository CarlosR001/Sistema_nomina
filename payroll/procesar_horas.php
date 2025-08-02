<?php
// payroll/procesar_horas.php - v2.9
// Añade la detección de horas no aprobadas durante la previsualización.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

// ... (función de horas nocturnas sin cambios) ...
function esTurnoNocturno($hora_inicio, $hora_fin) {
    $inicio_H = (int)date('H', strtotime($hora_inicio));
    $fin_H = (int)date('H', strtotime($hora_fin));
    $fin_M = (int)date('i', strtotime($hora_fin));
    if ($fin_H == 0 && $fin_M == 0 && strtotime($hora_fin) > strtotime($hora_inicio)) {
        $fin_H = 24;
    }
    return !($inicio_H >= 7 && $fin_H <= 21);
}

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

    // --- NUEVA LÓGICA DE VERIFICACIÓN ---
    if ($mode === 'preview') {
        $stmt_check_pendientes = $pdo->prepare("SELECT COUNT(id) FROM RegistroHoras WHERE estado_registro IN ('Pendiente', 'Rechazado') AND fecha_trabajada BETWEEN ? AND ?");
        $stmt_check_pendientes->execute([$fecha_inicio, $fecha_fin]);
        $_SESSION['pending_hours_check'] = $stmt_check_pendientes->fetchColumn();
    }
    // --- FIN DE LA NUEVA LÓGICA ---

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
            $duracion_horas = round((strtotime($reg['hora_fin']) - strtotime($reg['hora_inicio'])) / 3600, 2);
            if (in_array($reg['fecha_trabajada'], $feriados)) { $total_horas_feriado += $duracion_horas; } 
            else { $total_horas_laborales += $duracion_horas; }
            if (esTurnoNocturno($reg['hora_inicio'], $reg['hora_fin'])) { $total_horas_nocturnas_bono += $duracion_horas; }
            if ($reg['transporte_aprobado']) { $zonas_trabajadas_por_dia[$reg['fecha_trabajada']][] = $reg['id_zona_trabajo']; }
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
        // Lógica de guardado
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    header('Location: generar_novedades.php?status=error&message=' . urlencode('Error crítico: ' . $e->getMessage()));
    exit();
}
?>
