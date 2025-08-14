<?php
// reports/generate_inspector_report.php - v2.1 (Análisis de Datos Completo)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// --- 1. Recepción y Validación de Datos ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Acceso no permitido.");
$id_empleado_seleccionado = $_POST['id_empleado'] ?? null;
$periodo_id = filter_input(INPUT_POST, 'periodo_id', FILTER_VALIDATE_INT);
if (!$periodo_id) die("Error: Debe seleccionar un período válido.");

// --- 2. Funciones de Cálculo (Reutilizadas) ---
function calcular_horas_nocturnas_reales(DateTime $inicio, DateTime $fin) {
    $horas = 0; $cursor = clone $inicio;
    while ($cursor < $fin) {
        $h = (int)$cursor->format('G');
        if ($h >= 21 || $h < 7) { $horas += 1 / 60.0; }
        $cursor->modify('+1 minute');
    }
    return round($horas, 2);
}

// --- 3. Obtención de Datos Maestros ---
$configs_db = $pdo->query("SELECT clave, valor FROM configuracionglobal")->fetchAll(PDO::FETCH_KEY_PAIR);
$company_name = $configs_db['COMPANY_NAME'] ?? 'Empresa no Configurada';

$stmt_periodo = $pdo->prepare("SELECT fecha_inicio_periodo, fecha_fin_periodo FROM periodosdereporte WHERE id = ?");
$stmt_periodo->execute([$periodo_id]);
$periodo = $stmt_periodo->fetch();
if (!$periodo) die("Período no encontrado.");
$fecha_desde = $periodo['fecha_inicio_periodo'];
$fecha_hasta = $periodo['fecha_fin_periodo'];

$feriados_stmt = $pdo->prepare("SELECT fecha FROM calendariolaboralrd WHERE fecha BETWEEN ? AND ?");
$feriados_stmt->execute([$fecha_desde, $fecha_hasta]);
$feriados = $feriados_stmt->fetchAll(PDO::FETCH_COLUMN);

// --- 4. Determinar para qué empleados se generará el reporte ---
$ids_empleados_a_procesar = [];
if ($id_empleado_seleccionado === 'all') {
    $stmt_todos = $pdo->prepare("SELECT DISTINCT c.id_empleado FROM registrohoras rh JOIN contratos c ON rh.id_contrato = c.id WHERE rh.fecha_trabajada BETWEEN ? AND ? AND rh.estado_registro = 'Aprobado'");
    $stmt_todos->execute([$fecha_desde, $fecha_hasta]);
    $ids_empleados_a_procesar = $stmt_todos->fetchAll(PDO::FETCH_COLUMN);
} else {
    $ids_empleados_a_procesar[] = (int)$id_empleado_seleccionado;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Horas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; } .container { max-width: 900px; }
        .header { text-align: center; margin-bottom: 2rem; } .header h1 { margin: 0; } .header p { margin: 0; font-size: 1.2rem; }
        .table thead th { background-color: #f2f2f2; } .summary-table td { font-weight: bold; }
        .analysis { margin-top: 2rem; padding: 1rem; background-color: #f8f9fa; border-radius: .25rem; }
        .page-break { page-break-after: always; }
        @media print {
            body { font-size: 10px; } .no-print { display: none; } .container { max-width: 100%; }
            .report-block:last-child .page-break { page-break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="text-center my-4 no-print">
            <button onclick="window.print()" class="btn btn-secondary">Imprimir Reporte</button>
        </div>
        <?php foreach ($ids_empleados_a_procesar as $id_empleado): 
            $stmt_inspector = $pdo->prepare("SELECT e.nombres, e.primer_apellido, c.tarifa_por_hora FROM empleados e JOIN contratos c ON e.id = c.id_empleado WHERE e.id = ? AND c.tipo_nomina = 'Inspectores' AND c.estado_contrato = 'Vigente' LIMIT 1");
            $stmt_inspector->execute([$id_empleado]);
            $inspector = $stmt_inspector->fetch();
            if (!$inspector) continue;
            $tarifa_hora = (float)$inspector['tarifa_por_hora'];

            $sql_registros = "
                SELECT rh.*, l.nombre_zona_o_muelle as lugar, op.nombre_operacion as operacion,
                CONCAT(sup.nombres, ' ', sup.primer_apellido) as supervisor, o.codigo_orden, 
                l.monto_transporte_completo, CONCAT(e_aprob.nombres, ' ', e_aprob.primer_apellido) as aprobador
                FROM registrohoras rh JOIN contratos c ON rh.id_contrato = c.id
                LEFT JOIN ordenes o ON rh.id_orden = o.id LEFT JOIN lugares l ON rh.id_zona_trabajo = l.id
                LEFT JOIN operaciones op ON o.id_operacion = op.id LEFT JOIN empleados sup ON o.id_supervisor = sup.id
                LEFT JOIN empleados e_aprob ON rh.id_usuario_aprobador = e_aprob.id
                WHERE c.id_empleado = ? AND rh.fecha_trabajada BETWEEN ? AND ? AND rh.estado_registro = 'Aprobado'
                ORDER BY rh.fecha_trabajada, rh.hora_inicio";
            $stmt_registros = $pdo->prepare($sql_registros);
            $stmt_registros->execute([$id_empleado, $fecha_desde, $fecha_hasta]);
            $registros = $stmt_registros->fetchAll();
            if (empty($registros)) continue;

            $total_horas_laborales = 0; $total_horas_feriado = 0; $total_horas_nocturnas = 0;
            $total_transporte = 0; $total_horas_gracia = 0;

            foreach ($registros as $reg) {
                $inicio = new DateTime($reg['hora_inicio']); $fin = new DateTime($reg['hora_fin']);
                if ($fin <= $inicio) $fin->modify('+1 day');
                $duracion_horas = round(($fin->getTimestamp() - $inicio->getTimestamp()) / 3600, 2);
                if (in_array($reg['fecha_trabajada'], $feriados)) { $total_horas_feriado += $duracion_horas; } else { $total_horas_laborales += $duracion_horas; }
                $total_horas_nocturnas += calcular_horas_nocturnas_reales($inicio, $fin);
                if ($reg['transporte_aprobado']) { $monto = (float)$reg['monto_transporte_completo']; $total_transporte += $reg['transporte_mitad'] ? ($monto * 0.5) : $monto; }
                $total_horas_gracia += ($reg['hora_gracia_antes'] ? 1 : 0) + ($reg['hora_gracia_despues'] ? 1 : 0);
            }
            $total_horas_brutas = $total_horas_laborales + $total_horas_feriado;
            $horas_normales = min($total_horas_laborales, 44);
            $horas_extras_35 = max(0, min($total_horas_laborales - 44, 24));
            $horas_extras_100 = max(0, $total_horas_laborales - 68);
            $monto_normal = $horas_normales * $tarifa_hora;
            $monto_extra_35 = $horas_extras_35 * $tarifa_hora * 1.35;
            $monto_extra_100 = $horas_extras_100 * $tarifa_hora * 2.0;
            $monto_feriado = $total_horas_feriado * $tarifa_hora * 2.0;
            $monto_nocturno = $total_horas_nocturnas * $tarifa_hora * 0.15;
            $monto_incentivos = $total_horas_gracia * $tarifa_hora;
            $monto_dieta = 0;
            $monto_total = $monto_normal + $monto_extra_35 + $monto_extra_100 + $monto_feriado + $monto_nocturno + $total_transporte + $monto_incentivos;
        ?>
        <div class="report-block">
            <div class="header">
                <h1><?php echo htmlspecialchars($company_name); ?></h1><p>Reporte de Horas Trabajadas</p>
                <p class="lead">Inspector: <?php echo htmlspecialchars($inspector['nombres'] . ' ' . $inspector['primer_apellido']); ?></p>
                <small>Período: <?php echo htmlspecialchars($fecha_desde); ?> al <?php echo htmlspecialchars($fecha_hasta); ?></small>
            </div>
            <h4 class="mt-5">Detalle de Horas Reportadas</h4>
            <table class="table table-bordered table-sm">
                <thead><tr><th>Fecha</th><th>Lugar</th><th>Operación</th><th>Orden</th><th>Horario</th><th>Transporte</th><th>Gracia</th><th>Aprobado Por</th></tr></thead>
                <tbody><?php foreach ($registros as $reg): ?><tr>
                    <td><?php echo htmlspecialchars($reg['fecha_trabajada']); ?></td><td><?php echo htmlspecialchars($reg['lugar'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($reg['operacion'] ?? 'N/A'); ?></td><td><?php echo htmlspecialchars($reg['codigo_orden'] ?? 'N/A'); ?></td>
                    <td><?php echo date('H:i', strtotime($reg['hora_inicio'])) . ' - ' . date('H:i', strtotime($reg['hora_fin'])); ?></td>
                    <td>$<?php echo number_format($reg['transporte_aprobado'] ? ($reg['monto_transporte_completo'] * ($reg['transporte_mitad'] ? 0.5 : 1)) : 0, 2); ?></td>
                    <td><?php echo ($reg['hora_gracia_antes'] ? 1 : 0) + ($reg['hora_gracia_despues'] ? 1 : 0); ?> H</td><td><?php echo htmlspecialchars($reg['aprobador'] ?? 'N/A'); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table>
            <div class="row mt-5"><div class="col-md-6">
                <h4>Resumen de Horas</h4>
                <table class="table table-sm summary-table">
                    <tr><td>Total Horas Brutas</td><td class="text-end"><?php echo number_format($total_horas_brutas, 2); ?></td></tr>
                    <tr><td>Total Horas Normales</td><td class="text-end"><?php echo number_format($horas_normales, 2); ?></td></tr>
                    <tr><td>Total Horas Extras al 35%</td><td class="text-end"><?php echo number_format($horas_extras_35, 2); ?></td></tr>
                    <tr><td>Total Horas Extras al 100%</td><td class="text-end"><?php echo number_format($horas_extras_100, 2); ?></td></tr>
                    <tr><td>Total Horas Feriado</td><td class="text-end"><?php echo number_format($total_horas_feriado, 2); ?></td></tr>
                    <tr><td>Total Horas Nocturnas</td><td class="text-end"><?php echo number_format($total_horas_nocturnas, 2); ?></td></tr>
                </table>
            </div><div class="col-md-6">
                <h4>Resumen de Montos</h4>
                <table class="table table-sm summary-table">
                    <tr><td>Monto Horas Normales</td><td class="text-end">$<?php echo number_format($monto_normal, 2); ?></td></tr>
                    <tr><td>Monto Extras 35%</td><td class="text-end">$<?php echo number_format($monto_extra_35, 2); ?></td></tr>
                    <tr><td>Monto Extras 100% (+ Feriado)</td><td class="text-end">$<?php echo number_format($monto_extra_100 + $monto_feriado, 2); ?></td></tr>
                    <tr><td>Monto Bono Nocturno</td><td class="text-end">$<?php echo number_format($monto_nocturno, 2); ?></td></tr>
                    <tr><td>Monto Transporte</td><td class="text-end">$<?php echo number_format($total_transporte, 2); ?></td></tr>
                    <tr><td>Monto Incentivos (H. Gracia)</td><td class="text-end">$<?php echo number_format($monto_incentivos, 2); ?></td></tr>
                    <tr class="table-dark"><td><strong>TOTAL BRUTO</strong></td><td class="text-end"><strong>$<?php echo number_format($monto_total, 2); ?></strong></td></tr>
                </table>
            </div></div>
            <div class="analysis">
                <h5>Análisis de Datos</h5>
                <p class="mb-1">Reporte generado a partir de <strong><?php echo count($registros); ?></strong> registros aprobados, usando una tarifa base de <strong>$<?php echo number_format($tarifa_hora, 2); ?>/hora</strong>.</p>
                <ul>
                    <li><strong>Horas Normales/Extras:</strong> Se totalizan las horas laborables. Las primeras 44 se consideran normales, las siguientes 24 se consideran extras al 35%, y el resto al 100%.</li>
                    <li><strong>Extras 100% (+ Feriado):</strong> Incluye las horas extras laborables sobre 68 y la totalidad de horas en días feriados, ambas pagadas al 200% (x2.0).</li>
                    <li><strong>Bono Nocturno:</strong> Es un 15% (x0.15) adicional sobre el valor de la hora normal por cada hora trabajada entre 21:00 y 07:00.</li>
                    <li><strong>Transporte:</strong> Es la suma de los montos de transporte aprobados para cada registro, aplicando el 50% de descuento cuando fue especificado por un supervisor.</li>
                    <li><strong>Incentivos (H. Gracia):</strong> Corresponde al pago de las horas de gracia aprobadas, valoradas a la tarifa de una hora normal cada una.</li>
                </ul>
            </div>
            <div class="page-break"></div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
