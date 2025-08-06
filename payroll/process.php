<?php
// payroll/process.php - v8.4.2 (RESTAURACIÓN FINAL)
// Restaurado desde tu copia de seguridad funcional (v8.4) con la única modificación necesaria.

require_once '../auth.php';
require_login();
require_role('Admin');

function esUltimaSemanaDelMes($fecha_fin_periodo) {
    $fecha = new DateTime($fecha_fin_periodo);
    $dias_en_mes = (int)$fecha->format('t');
    $dia_fin_semana = (int)$fecha->format('d');
    return ($dias_en_mes - $dia_fin_semana) < 7;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo_id'])) {
    header('Location: ' . BASE_URL . 'payroll/index.php?status=error&message=Solicitud%20inv%C3%A1lida.');
    exit();
}

try {
    $periodo_id = $_POST['periodo_id'];
    $stmt_periodo = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE id = ?");
    $stmt_periodo->execute([$periodo_id]);
    $periodo = $stmt_periodo->fetch();

    if (!$periodo) { throw new Exception("Período no encontrado."); }

    $tipo_nomina = $periodo['tipo_nomina'];
    $fecha_inicio = $periodo['fecha_inicio_periodo'];
    $fecha_fin = $periodo['fecha_fin_periodo'];
    $es_ultima_semana = esUltimaSemanaDelMes($fecha_fin);
    $mes_actual = date('m', strtotime($fecha_fin));
    $anio_actual = date('Y', strtotime($fecha_fin));

    $pdo->beginTransaction();

    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $tope_salarial_tss = (float)($configs_db['TSS_TOPE_SALARIAL'] ?? 265840.00);
    $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287);
    $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304);
    $escala_isr = $pdo->query("SELECT * FROM escalasisr WHERE anio_fiscal = {$anio_actual} ORDER BY desde_monto_anual ASC")->fetchAll(PDO::FETCH_ASSOC);

      // --- INICIO BLOQUE 1 ---
    // Busca si ya existe una nómina para este período.
    $stmt_find_nomina = $pdo->prepare("SELECT id FROM NominasProcesadas WHERE periodo_inicio = ? AND periodo_fin = ?");
    if ($stmt_find_nomina->execute([$fecha_inicio, $fecha_fin]) && $existing_nomina = $stmt_find_nomina->fetch()) {
        // Si existe, la borra por completo para empezar de cero.
        $pdo->prepare("DELETE FROM NominaDetalle WHERE id_nomina_procesada = ?")->execute([$existing_nomina['id']]);
        $pdo->prepare("DELETE FROM NominasProcesadas WHERE id = ?")->execute([$existing_nomina['id']]);
    }

    // Crucial para Recálculo: Asegura que TODAS las novedades del período (manuales y automáticas)
    // vuelvan a 'Pendiente' para ser procesadas de nuevo.
    $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Pendiente' WHERE periodo_aplicacion BETWEEN ? AND ?")->execute([$fecha_inicio, $fecha_fin]);
    // --- FIN BLOQUE 1 ---


    $sql_nomina = "INSERT INTO NominasProcesadas (tipo_nomina_procesada, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES (?, ?, ?, ?, 'Pendiente de Aprobación')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    $stmt_nomina->execute([$tipo_nomina, $fecha_inicio, $fecha_fin, $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();

    // --- INICIO BLOQUE 2 ---
    // CORRECCIÓN CLAVE: Obtiene los contratos que tienen novedades DENTRO DEL RANGO de fechas del período.
    $sql_contratos = "SELECT DISTINCT c.id, c.id_empleado FROM Contratos c JOIN NovedadesPeriodo np ON c.id = np.id_contrato WHERE c.tipo_nomina = ? AND np.periodo_aplicacion BETWEEN ? AND ? AND c.estado_contrato = 'Vigente'";
    $stmt_contratos = $pdo->prepare($sql_contratos);
    $stmt_contratos->execute([$tipo_nomina, $fecha_inicio, $fecha_fin]); // Se usan fecha_inicio y fecha_fin
    $contratos = $stmt_contratos->fetchAll();

    foreach ($contratos as $contrato) {
        $id_contrato = $contrato['id'];
        $conceptos = [];

        // CORRECCIÓN CLAVE: Recopila TODAS las novedades 'Pendientes' dentro del rango de fechas del período.
        $stmt_novedades = $pdo->prepare("SELECT n.id as novedad_id, n.monto_valor, c.* FROM NovedadesPeriodo n JOIN ConceptosNomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND n.estado_novedad = 'Pendiente' AND n.periodo_aplicacion BETWEEN ? AND ?");
        $stmt_novedades->execute([$id_contrato, $fecha_inicio, $fecha_fin]); // Se usan fecha_inicio y fecha_fin
        
        foreach ($stmt_novedades->fetchAll(PDO::FETCH_ASSOC) as $novedad) {
            $codigo = $novedad['codigo_concepto'];
            if (!isset($conceptos[$codigo])) {
                $conceptos[$codigo] = ['desc' => $novedad['descripcion_publica'], 'monto' => 0, 'aplica_tss' => (bool)$novedad['afecta_tss'], 'aplica_isr' => (bool)$novedad['afecta_isr'], 'tipo' => $novedad['tipo_concepto']];
            }
            $conceptos[$codigo]['monto'] += floatval($novedad['monto_valor']);
            // Marca la novedad como 'Aplicada' para no volver a procesarla.
            $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Aplicada' WHERE id = ?")->execute([$novedad['novedad_id']]);
        }

        // --- El resto del bucle (cálculos de TSS, ISR, etc.) permanece igual ---
        $salario_cotizable_tss = 0;
        foreach ($conceptos as $data) { if ($data['tipo'] === 'Ingreso' && $data['aplica_tss']) { $salario_cotizable_tss += $data['monto']; } }
        
        $tope_salarial_semanal = round($tope_salarial_tss / 4.333333, 2);
        $salario_cotizable_final_semanal = min($salario_cotizable_tss, $tope_salarial_semanal);
        
        $deduccion_afp = round($salario_cotizable_final_semanal * $porcentaje_afp, 2);
        $deduccion_sfs = round($salario_cotizable_final_semanal * $porcentaje_sfs, 2);
        if($deduccion_afp > 0) $conceptos['DED-AFP'] = ['desc' => 'Aporte AFP (2.87%)', 'monto' => $deduccion_afp, 'tipo' => 'Deducción'];
        if($deduccion_sfs > 0) $conceptos['DED-SFS'] = ['desc' => 'Aporte SFS (3.04%)', 'monto' => $deduccion_sfs, 'tipo' => 'Deducción'];

        $base_para_isr_semanal = 0;
        foreach ($conceptos as $data) { if (isset($data['aplica_isr']) && $data['aplica_isr'] && $data['tipo'] === 'Ingreso') { $base_para_isr_semanal += $data['monto']; } }
        $base_para_isr_semanal -= ($deduccion_afp + $deduccion_sfs);
        
        $deduccion_isr = 0;
        if ($es_ultima_semana) {
            $sql_prev_base = "SELECT SUM(nd.monto_resultado) FROM NominaDetalle nd JOIN NominasProcesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'BASE-ISR-SEMANAL'";
            $stmt_prev_base = $pdo->prepare($sql_prev_base);
            $stmt_prev_base->execute([$id_contrato, $mes_actual, $anio_actual]);
            $base_isr_acumulada_previa = (float)$stmt_prev_base->fetchColumn();
            $base_isr_mensual_total = $base_para_isr_semanal + $base_isr_acumulada_previa;
            
            $ingreso_anual_proyectado = $base_isr_mensual_total * 12;
            $isr_anual = 0;
            if (count($escala_isr) === 4) {
                $tramo1_hasta = (float)$escala_isr[0]['hasta_monto_anual']; $tramo2_hasta = (float)$escala_isr[1]['hasta_monto_anual']; $tramo3_hasta = (float)$escala_isr[2]['hasta_monto_anual'];
                if ($ingreso_anual_proyectado > $tramo3_hasta) {
                    $excedente = $ingreso_anual_proyectado - $tramo3_hasta; $tasa = (float)$escala_isr[3]['tasa_porcentaje'] / 100; $monto_fijo = (float)$escala_isr[3]['monto_fijo_adicional']; $isr_anual = $monto_fijo + ($excedente * $tasa);
                } elseif ($ingreso_anual_proyectado > $tramo2_hasta) {
                    $excedente = $ingreso_anual_proyectado - $tramo2_hasta; $tasa = (float)$escala_isr[2]['tasa_porcentaje'] / 100; $monto_fijo = (float)$escala_isr[2]['monto_fijo_adicional']; $isr_anual = $monto_fijo + ($excedente * $tasa);
                } elseif ($ingreso_anual_proyectado > $tramo1_hasta) {
                    $excedente = $ingreso_anual_proyectado - $tramo1_hasta; $tasa = (float)$escala_isr[1]['tasa_porcentaje'] / 100; $monto_fijo = (float)$escala_isr[1]['monto_fijo_adicional']; $isr_anual = $monto_fijo + ($excedente * $tasa);
                }
            }
             $deduccion_isr_mensual_total = round(max(0, $isr_anual / 12), 2);

    // --- INICIO DE LA MEJORA: AJUSTE POR ISR PRE-PAGADO ---
    // Buscar si ya se retuvo ISR en pagos especiales este mes.
    $sql_prev_isr = "SELECT SUM(nd.monto_resultado) FROM NominaDetalle nd JOIN NominasProcesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'DED-ISR' AND np.tipo_calculo_nomina = 'Especial'";
    $stmt_prev_isr = $pdo->prepare($sql_prev_isr);
    $stmt_prev_isr->execute([$id_contrato, $mes_actual, $anio_actual]);
    $isr_ya_retenido = (float)$stmt_prev_isr->fetchColumn();

    // La deducción final es el total del mes menos lo que ya se pagó.
    $deduccion_isr = max(0, $deduccion_isr_mensual_total - $isr_ya_retenido);
    // --- FIN DE LA MEJORA ---
}

$conceptos['BASE-ISR-SEMANAL'] = ['desc' => 'Base ISR Semanal', 'monto' => $base_para_isr_semanal, 'tipo' => 'Base de Cálculo'];

        if($es_ultima_semana) { $conceptos['BASE-ISR-MENSUAL'] = ['desc' => 'Base ISR Mensual Acumulada', 'monto' => $base_isr_mensual_total, 'tipo' => 'Base de Cálculo']; }
        if($deduccion_isr > 0) { $conceptos['DED-ISR'] = ['desc' => 'Impuesto Sobre la Renta (ISR)', 'monto' => $deduccion_isr, 'tipo' => 'Deducción']; }
        
        $sql_detalle = "INSERT INTO NominaDetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detalle = $pdo->prepare($sql_detalle);
        foreach ($conceptos as $codigo => $data) {
            if (isset($data['monto']) && abs($data['monto']) > 0.001) {
                $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $codigo, $data['desc'], $data['tipo'], $data['monto']]);
            }
        }
    }
    // --- FIN BLOQUE 2 ---

// FIN DEL BLOQUE A PEGAR


    $stmt_cerrar = $pdo->prepare("UPDATE PeriodosDeReporte SET estado_periodo = 'Procesado y Finalizado' WHERE id = ?");
    $stmt_cerrar->execute([$periodo_id]);

    $pdo->commit();
    header('Location: show.php?id=' . $id_nomina_procesada . '&status=success&message=' . urlencode('Nómina de Inspectores recalculada correctamente.'));
    exit();

} catch (Exception $e) {
    if($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Error al procesar la nómina: " . $e->getMessage());
    header('Location: ' . BASE_URL . 'payroll/index.php?status=error&message=' . urlencode('Error crítico al procesar la nómina: ' . $e->getMessage()));
    exit();
}
?>
