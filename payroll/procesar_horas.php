<?php
// payroll/procesar_horas.php - v3.2 (CORREGIDO)
// Combina el motor de cálculo funcional (v2.8) con la lógica de borrado selectivo.

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');


/**
 * Calcula la cantidad de horas de un turno que caen dentro del período nocturno legal (21:00 a 07:00).
 *
 * @param DateTime $inicio_turno La hora y fecha de inicio del turno.
 * @param DateTime $fin_turno La hora y fecha de fin del turno.
 * @return float El número de horas nocturnas trabajadas.
 */
function calcular_horas_nocturnas_reales(DateTime $inicio_turno, DateTime $fin_turno) {
    $horas_nocturnas = 0;
    
    // Clonar para no modificar los objetos originales
    $cursor = clone $inicio_turno;
    $fin = clone $fin_turno;
    
    // Definir los límites de la jornada nocturna (formato 24h)
    $hora_inicio_nocturna = 21;
    $hora_fin_nocturna = 7;

    // Iterar minuto a minuto a lo largo de la duración del turno
    while ($cursor < $fin) {
        $hora_actual = (int)$cursor->format('G'); // Obtener la hora del día (0-23)
        
        // Un minuto es nocturno si su hora es >= 21 o < 7.
        if ($hora_actual >= $hora_inicio_nocturna || $hora_actual < $hora_fin_nocturna) {
            // Sumamos el equivalente a un minuto en horas (1/60)
            $horas_nocturnas += 1 / 60.0;
        }
        
        // Avanzar el cursor un minuto
        $cursor->modify('+1 minute');
    }
    
    return round($horas_nocturnas, 2);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo_id'], $_POST['mode'])) {
    header('Location: generar_novedades.php?status=error&message=' . urlencode('Solicitud no válida.'));
    exit();
}
$periodo_id = $_POST['periodo_id'];
$mode = $_POST['mode'];

try {
    if ($mode === 'final') $pdo->beginTransaction();

    $stmt_periodo = $pdo->prepare("SELECT * FROM periodosdereporte WHERE id = ?");
    $stmt_periodo->execute([$periodo_id]);
    $periodo = $stmt_periodo->fetch();
    if (!$periodo) throw new Exception("Período no válido.");
    $fecha_inicio = $periodo['fecha_inicio_periodo'];
    $fecha_fin = $periodo['fecha_fin_periodo'];

    if ($mode === 'preview') {
        // CORRECCIÓN: La consulta ahora solo busca registros en estado 'Pendiente'.
        $stmt_check_pendientes = $pdo->prepare("SELECT COUNT(id) FROM registrohoras WHERE estado_registro = 'Pendiente' AND fecha_trabajada BETWEEN ? AND ?");
        $stmt_check_pendientes->execute([$fecha_inicio, $fecha_fin]);
        $_SESSION['pending_hours_check'] = $stmt_check_pendientes->fetchColumn();
    }
    
         // Cargar datos maestros (feriados, conceptos, lugares)
    $feriados_stmt = $pdo->prepare("SELECT fecha FROM calendariolaboralrd WHERE fecha BETWEEN ? AND ?");
    $feriados_stmt->execute([$fecha_inicio, $fecha_fin]);
    $feriados = $feriados_stmt->fetchAll(PDO::FETCH_COLUMN);
    $conceptos = $pdo->query("SELECT codigo_concepto, id FROM conceptosnomina")->fetchAll(PDO::FETCH_KEY_PAIR);
    // CORRECCIÓN: Se lee desde la tabla 'lugares'
    $zonas = $pdo->query("SELECT id, monto_transporte_completo FROM lugares")->fetchAll(PDO::FETCH_KEY_PAIR);

        // --- INICIO DE LA MODIFICACIÓN CLAVE ---
    // La consulta principal ahora se une con `ordenes` para obtener el `id_lugar` (zona).
    // Se usa un alias (AS) para que el resto del script no necesite cambios.
    $sql_horas = "SELECT 
    c.id as contrato_id, c.tarifa_por_hora, 
    e.nombres, e.primer_apellido, 
    rh.fecha_trabajada, rh.hora_inicio, rh.hora_fin, 
    o.id_lugar AS id_zona_trabajo,
    rh.transporte_aprobado,
    rh.transporte_mitad, -- <-- CAMBIO: Se añade la nueva columna
    rh.hora_gracia_antes, rh.hora_gracia_despues 
  FROM contratos c 
  JOIN empleados e ON c.id_empleado = e.id 
  JOIN registrohoras rh ON c.id = rh.id_contrato
  LEFT JOIN ordenes o ON rh.id_orden = o.id
  WHERE c.tipo_nomina = 'Inspectores' 
    AND rh.estado_registro = 'Aprobado' 
    AND rh.fecha_trabajada BETWEEN ? AND ? 
  ORDER BY c.id, rh.fecha_trabajada, rh.hora_inicio";

    // --- FIN DE LA MODIFICACIÓN CLAVE ---
   $stmt_horas = $pdo->prepare($sql_horas);
    $stmt_horas->execute([$fecha_inicio, $fecha_fin]);
    $horas_aprobadas = $stmt_horas->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    
    $results = [];

    foreach ($horas_aprobadas as $contrato_id => $registros) {
        $tarifa_hora = (float)$registros[0]['tarifa_por_hora'];
        $total_horas_feriado = 0; $total_horas_laborales = 0; $total_horas_nocturnas_bono = 0; $total_horas_gracia = 0;
        
              // Bucle unificado para calcular todo (horas y transporte)
              foreach ($registros as $reg) {
                // --- NUEVA LÓGICA DE TRANSPORTE SIMPLIFICADA ---
                if ($reg['transporte_aprobado'] && !empty($reg['id_zona_trabajo']) && isset($zonas[$reg['id_zona_trabajo']])) {
                    $costo_lugar = (float)$zonas[$reg['id_zona_trabajo']];
                    if ($reg['transporte_mitad']) {
                        $pago_transporte_total += ($costo_lugar * 0.5); // Paga el 50%
                    } else {
                        $pago_transporte_total += $costo_lugar; // Paga el 100%
                    }
                }
                // --- FIN NUEVA LÓGICA ---
    
                $inicio = new DateTime($reg['hora_inicio']);
                $fin = new DateTime($reg['hora_fin']);
                if ($fin <= $inicio) $fin->modify('+1 day');
                $duracion_horas = round(($fin->getTimestamp() - $inicio->getTimestamp()) / 3600, 2);
    
                if (in_array($reg['fecha_trabajada'], $feriados)) { 
                    $total_horas_feriado += $duracion_horas; 
                } else { 
                    $total_horas_laborales += $duracion_horas; 
                }
    
                $total_horas_nocturnas_bono += calcular_horas_nocturnas_reales($inicio, $fin);
    
                if ($reg['hora_gracia_antes']) $total_horas_gracia++;
                if ($reg['hora_gracia_despues']) $total_horas_gracia++;
            }
    
        
        // Bucle para sumar horas, ahora incluye el cálculo de nocturnas
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

            // --- LÍNEA REACTIVADA ---
            $total_horas_nocturnas_bono += calcular_horas_nocturnas_reales($inicio, $fin);

            if ($reg['hora_gracia_antes']) $total_horas_gracia++;
            if ($reg['hora_gracia_despues']) $total_horas_gracia++;
        }

        // --- Bloque de totalización de pagos ---
        $pago_feriado = $total_horas_feriado * $tarifa_hora * 2.0;
        $horas_normales = min($total_horas_laborales, 44);
        $pago_normal = $horas_normales * $tarifa_hora;
        $horas_extra_35 = max(0, min($total_horas_laborales - 44, 24));
        $pago_extra_35 = $horas_extra_35 * $tarifa_hora * 1.35;
        $horas_extra_100 = max(0, $total_horas_laborales - 68);
        $pago_extra_100 = $horas_extra_100 * $tarifa_hora * 2.0;
        $pago_extra_total = $pago_extra_35 + $pago_extra_100;
        
        // --- LÍNEA CORREGIDA ---
        $pago_nocturno = $total_horas_nocturnas_bono * $tarifa_hora * 0.15;
        
        $pago_gracia = $total_horas_gracia * $tarifa_hora;
        
        $results[$contrato_id] = [
            'nombre_empleado' => $registros[0]['nombres'] . ' ' . $registros[0]['primer_apellido'],
            'pago_normal' => round($pago_normal, 2), 
            'pago_extra' => round($pago_extra_total, 2),
            'pago_feriado' => round($pago_feriado, 2), 
            'pago_nocturno' => round($pago_nocturno, 2),
            'pago_gracia' => round($pago_gracia, 2),
            'pago_transporte' => round($pago_transporte_total, 2),
            'ingreso_bruto' => round($pago_normal + $pago_extra_total + $pago_feriado + $pago_nocturno + $pago_gracia + $pago_transporte_total, 2)
        ];
    }



    if ($mode === 'preview') {
        $_SESSION['preview_results'] = $results;
        $_SESSION['preview_period_id'] = $periodo_id;
        header('Location: generar_novedades.php');
        exit();
    } 
    elseif ($mode === 'final') {
        $contratos_a_procesar = array_keys($results);
        if (empty($contratos_a_procesar)) {
            if ($pdo->inTransaction()) $pdo->commit();
            header('Location: generar_novedades.php?status=info&message=' . urlencode('No se encontraron horas aprobadas para generar novedades.'));
            exit();
        }
        $contratos_placeholders = implode(',', array_fill(0, count($contratos_a_procesar), '?'));
        
        $codigos_automaticos = ['ING-NORMAL', 'ING-EXTRA', 'ING-FERIADO', 'ING-NOCTURNO', 'ING-TRANSP'];
        $codigos_auto_placeholders = implode(',', array_fill(0, count($codigos_automaticos), '?'));
        
        $sql_rescate = "SELECT np.id_contrato, np.id_concepto, np.periodo_aplicacion, np.monto_valor FROM novedadesperiodo np JOIN conceptosnomina cn ON np.id_concepto = cn.id WHERE np.id_contrato IN ($contratos_placeholders) AND np.periodo_aplicacion BETWEEN ? AND ? AND cn.codigo_concepto NOT IN ($codigos_auto_placeholders)";
        $stmt_rescate = $pdo->prepare($sql_rescate);
        $params_rescate = array_merge($contratos_a_procesar, [$fecha_inicio, $fecha_fin], $codigos_automaticos);
        $stmt_rescate->execute($params_rescate);
        $novedades_manuales_rescatadas = $stmt_rescate->fetchAll(PDO::FETCH_ASSOC);

        $sql_delete_total = "DELETE FROM novedadesperiodo WHERE id_contrato IN ($contratos_placeholders) AND periodo_aplicacion BETWEEN ? AND ?";
        $stmt_delete_total = $pdo->prepare($sql_delete_total);
        $stmt_delete_total->execute(array_merge($contratos_a_procesar, [$fecha_inicio, $fecha_fin]));

        $stmt_insert = $pdo->prepare("INSERT INTO novedadesperiodo (id_contrato, id_concepto, periodo_aplicacion, monto_valor, estado_novedad) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($results as $contrato_id => $res) {
            if ($res['pago_normal'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-NORMAL'], $fecha_inicio, $res['pago_normal'], 'Pendiente']);
            if ($res['pago_extra'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-EXTRA'], $fecha_inicio, $res['pago_extra'], 'Pendiente']);
            if ($res['pago_feriado'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-FERIADO'], $fecha_inicio, $res['pago_feriado'], 'Pendiente']);
            if ($res['pago_nocturno'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-NOCTURNO'], $fecha_inicio, $res['pago_nocturno'], 'Pendiente']);
            if ($res['pago_transporte'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-TRANSP'], $fecha_inicio, $res['pago_transporte'], 'Pendiente']);
            if ($res['pago_gracia'] > 0) $stmt_insert->execute([$contrato_id, $conceptos['ING-GRACIA'], $fecha_inicio, $res['pago_gracia'], 'Pendiente']);
        }

        foreach ($novedades_manuales_rescatadas as $novedad_manual) {
            $stmt_insert->execute([
                $novedad_manual['id_contrato'],
                $novedad_manual['id_concepto'],
                $novedad_manual['periodo_aplicacion'],
                $novedad_manual['monto_valor'],
                'Pendiente'
            ]);
        }
        
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
