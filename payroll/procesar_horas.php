<?php
// payroll/procesar_horas.php
// El "Motor de Cálculo de Horas". Lee horas aprobadas y genera las novedades de ingreso.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

// --- 1. Recolección y Validación de Datos Iniciales ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo_id'])) {
    header('Location: generar_novedades.php?status=error&message=' . urlencode('Solicitud no válida.'));
    exit();
}
$periodo_id = $_POST['periodo_id'];

try {
    $pdo->beginTransaction();

    $stmt_periodo = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE id = ? AND estado_periodo = 'Abierto'");
    $stmt_periodo->execute([$periodo_id]);
    $periodo = $stmt_periodo->fetch();

    if (!$periodo) {
        throw new Exception("El período seleccionado no es válido o ya no está abierto.");
    }

    $fecha_inicio = $periodo['fecha_inicio_periodo'];
    $fecha_fin = $periodo['fecha_fin_periodo'];

    // Obtener todos los datos necesarios en consultas eficientes
    $feriados_stmt = $pdo->prepare("SELECT fecha FROM CalendarioLaboralRD WHERE fecha BETWEEN ? AND ?");
    $feriados_stmt->execute([$fecha_inicio, $fecha_fin]);
    $feriados = $feriados_stmt->fetchAll(PDO::FETCH_COLUMN);

    $conceptos_stmt = $pdo->query("SELECT codigo_concepto, id FROM ConceptosNomina");
    $conceptos = $conceptos_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $zonas_stmt = $pdo->query("SELECT id, monto_transporte_completo FROM ZonasTransporte");
    $zonas = $zonas_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Obtener todos los contratos de inspectores con sus horas aprobadas para el período
    $sql_horas = "SELECT 
                    c.id as contrato_id, c.tarifa_por_hora,
                    rh.fecha_trabajada, rh.hora_inicio, rh.hora_fin, rh.id_zona_trabajo
                  FROM Contratos c
                  JOIN RegistroHoras rh ON c.id = rh.id_contrato
                  WHERE c.tipo_nomina = 'Inspectores'
                  AND rh.estado_registro = 'Aprobado'
                  AND rh.fecha_trabajada BETWEEN ? AND ?
                  ORDER BY c.id, rh.fecha_trabajada, rh.hora_inicio";
    $stmt_horas = $pdo->prepare($sql_horas);
    $stmt_horas->execute([$fecha_inicio, $fecha_fin]);
    $horas_aprobadas = $stmt_horas->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    
    // Antes de insertar, borrar las novedades de ingresos calculadas previamente para este período
    $codigos_a_borrar = "'ING-NORMAL', 'ING-EXTRA', 'ING-FERIADO', 'ING-NOCTURNO', 'ING-TRANSP'";
    $stmt_delete = $pdo->prepare(
        "DELETE n FROM NovedadesPeriodo n 
         JOIN ConceptosNomina c ON n.id_concepto = c.id
         WHERE n.periodo_aplicacion = ? AND c.codigo_concepto IN ($codigos_a_borrar)"
    );
    $stmt_delete->execute([$fecha_inicio]);

    $stmt_insert = $pdo->prepare(
        "INSERT INTO NovedadesPeriodo (id_contrato, id_concepto, periodo_aplicacion, monto_valor, estado_novedad) 
         VALUES (?, ?, ?, ?, 'Pendiente')"
    );

    // --- 2. Iterar sobre cada Contrato y Procesar sus Horas ---
    foreach ($horas_aprobadas as $contrato_id => $registros) {
        $tarifa_hora = (float)$registros[0]['tarifa_por_hora'];
        
        $total_horas_feriado = 0;
        $total_horas_laborales = 0;
        $total_horas_nocturnas = 0;
        $zonas_trabajadas_por_dia = [];

        // --- 2.1. Clasificación y Suma de Horas ---
        foreach ($registros as $reg) {
            $inicio = new DateTime($reg['hora_inicio']);
            $fin = new DateTime($reg['hora_fin']);
            $duracion_horas = ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;

            if (in_array($reg['fecha_trabajada'], $feriados)) {
                $total_horas_feriado += $duracion_horas;
            } else {
                $total_horas_laborales += $duracion_horas;
            }
            
            // Cálculo de horas nocturnas (aproximación simple)
            $inicio_nocturno = (new DateTime($reg['fecha_trabajada']))->setTime(21, 0);
            $fin_nocturno = (new DateTime($reg['fecha_trabajada']))->modify('+1 day')->setTime(7, 0);
            if ($inicio >= $inicio_nocturno || $fin <= $fin_nocturno) {
                 $total_horas_nocturnas += $duracion_horas;
            }

            // Agrupar zonas por día para el cálculo de transporte
            $zonas_trabajadas_por_dia[$reg['fecha_trabajada']][] = $reg['id_zona_trabajo'];
        }

        // --- 2.2. Cálculo de los Montos de Pago (Ingresos) ---
        $pago_feriado = $total_horas_feriado * $tarifa_hora * 2.0;
        
        $horas_normales = min($total_horas_laborales, 44);
        $pago_normal = $horas_normales * $tarifa_hora;

        $horas_extra_35 = max(0, min($total_horas_laborales - 44, 24)); // 68 - 44 = 24
        $pago_extra_35 = $horas_extra_35 * $tarifa_hora * 1.35;
        
        $horas_extra_100 = max(0, $total_horas_laborales - 68);
        $pago_extra_100 = $horas_extra_100 * $tarifa_hora * 2.0;

        $pago_nocturno = $total_horas_nocturnas * $tarifa_hora * 0.15;

        // Cálculo de Transporte
        $pago_transporte = 0;
        foreach ($zonas_trabajadas_por_dia as $dia => $zonas_dia) {
            $zonas_unicas = array_unique($zonas_dia);
            $primera_zona = true;
            foreach ($zonas_unicas as $zona_id) {
                if ($primera_zona) {
                    $pago_transporte += (float)($zonas[$zona_id] ?? 0);
                    $primera_zona = false;
                } else {
                    $pago_transporte += (float)($zonas[$zona_id] ?? 0) * 0.5;
                }
            }
        }
        
        // --- 2.3. Inserción de Novedades ---
        if ($pago_normal > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-NORMAL'], $fecha_inicio, round($pago_normal, 2)]);
        if ($pago_extra_35 + $pago_extra_100 > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-EXTRA'], $fecha_inicio, round($pago_extra_35 + $pago_extra_100, 2)]);
        if ($pago_feriado > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-FERIADO'], $fecha_inicio, round($pago_feriado, 2)]);
        if ($pago_nocturno > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-NOCTURNO'], $fecha_inicio, round($pago_nocturno, 2)]);
        if ($pago_transporte > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-TRANSP'], $fecha_inicio, round($pago_transporte, 2)]);
    }

    $pdo->commit();
    header('Location: generar_novedades.php?status=success&message=' . urlencode('Proceso completado. Se generaron las novedades de ingreso para los inspectores con horas aprobadas.'));
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: generar_novedades.php?status=error&message=' . urlencode('Error crítico durante el proceso: ' . $e->getMessage()));
    exit();
}
?>
