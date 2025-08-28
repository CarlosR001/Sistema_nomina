<?php
// nomina_administrativa/procesar_nomina_admin.php - v5.1 (Lógica Híbrida de ISR Corregida)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$id_nomina_a_recalcular = $_POST['id_nomina_a_recalcular'] ?? null;
$fecha_inicio_form = $_POST['fecha_inicio'] ?? null;
$fecha_fin_form = $_POST['fecha_fin'] ?? null;

$fecha_inicio = null;
$fecha_fin = null;

if ($id_nomina_a_recalcular) {
    $stmt_recalc_info = $pdo->prepare("SELECT periodo_inicio, periodo_fin FROM nominasprocesadas WHERE id = ? AND tipo_nomina_procesada = 'Administrativa'");
    $stmt_recalc_info->execute([$id_nomina_a_recalcular]);
    $recalc_data = $stmt_recalc_info->fetch();
    if ($recalc_data) {
        $fecha_inicio = $recalc_data['periodo_inicio'];
        $fecha_fin = $recalc_data['periodo_fin'];
    }
} elseif ($fecha_inicio_form && $fecha_fin_form) {
    $fecha_inicio = $fecha_inicio_form;
    $fecha_fin = $fecha_fin_form;
}

if (!$fecha_inicio || !$fecha_fin) {
    header('Location: index.php?status=error&message=' . urlencode('Error: Parámetros de período inválidos.'));
    exit();
}

try {
    $pdo->beginTransaction();

       if ($id_nomina_a_recalcular) {
        // --- INICIO DE LA CORRECCIÓN ---
        // 1. Obtenemos la lista de contratos de la nómina que vamos a recalcular.
        $stmt_contratos = $pdo->prepare("SELECT DISTINCT id_contrato FROM nominadetalle WHERE id_nomina_procesada = ?");
        $stmt_contratos->execute([$id_nomina_a_recalcular]);
        $contratos_a_reiniciar = $stmt_contratos->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($contratos_a_reiniciar)) {
            // 2. Creamos los placeholders (?) para la consulta
            $placeholders = rtrim(str_repeat('?,', count($contratos_a_reiniciar)), ',');
            
            // 3. Reiniciamos las novedades SOLO para esos contratos en ese período.
            $sql_update_novedades = "UPDATE novedadesperiodo SET estado_novedad = 'Pendiente' 
                                     WHERE periodo_aplicacion BETWEEN ? AND ? AND id_contrato IN ($placeholders)";
            
            // Preparamos los parámetros: fecha de inicio, fecha fin, y luego la lista de IDs de contrato
            $params = array_merge([$fecha_inicio, $fecha_fin], $contratos_a_reiniciar);
            $pdo->prepare($sql_update_novedades)->execute($params);
        }
        // --- FIN DE LA CORRECCIÓN ---

        // Ahora borramos la nómina antigua
        $pdo->prepare("DELETE FROM nominadetalle WHERE id_nomina_procesada = ?")->execute([$id_nomina_a_recalcular]);
        $pdo->prepare("DELETE FROM nominasprocesadas WHERE id = ?")->execute([$id_nomina_a_recalcular]);
    }

    
    $quincena = (int)date('d', strtotime($fecha_fin)) <= 15 ? 1 : 2;
    $mes = (int)date('m', strtotime($fecha_fin));
    $anio = (int)date('Y', strtotime($fecha_fin));

    $configs_db = $pdo->query("SELECT clave, valor FROM configuracionglobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $escala_isr = $pdo->query("SELECT * FROM escalasisr WHERE anio_fiscal = {$anio} ORDER BY desde_monto_anual ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_empleados = $pdo->prepare("SELECT c.id as id_contrato, c.salario_mensual_bruto, c.fecha_inicio FROM contratos c WHERE c.tipo_nomina IN ('Administrativa', 'Directiva') AND c.estado_contrato = 'Vigente' AND c.salario_mensual_bruto > 0");
    $stmt_empleados->execute();
    $empleados_a_procesar = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

    if (empty($empleados_a_procesar)) throw new Exception("No se encontraron empleados activos con salario para este tipo de nómina.");

    $sql_nomina = "INSERT INTO nominasprocesadas (tipo_nomina_procesada, tipo_calculo_nomina, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES ('Administrativa', 'Quincenal', ?, ?, ?, 'Calculada')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    $stmt_nomina->execute([$fecha_inicio, $fecha_fin, $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();

    foreach ($empleados_a_procesar as $empleado) {
            $id_contrato = $empleado['id_contrato'];
            $stmt_check_skip = $pdo->prepare("
            SELECT n.id FROM novedadesperiodo n
            JOIN conceptosnomina c ON n.id_concepto = c.id
            WHERE n.id_contrato = ? AND c.codigo_concepto = 'SYS-SKIP-PAYROLL' 
            AND n.periodo_aplicacion BETWEEN ? AND ? AND n.estado_novedad = 'Pendiente'
        ");
              // --- INICIO: CÁLCULO DE SALARIO Y MANEJO DE NOVEDAD DE OMISIÓN ---
        
        // 1. Calcular el salario quincenal base.
        $salario_quincenal_completo = round($empleado['salario_mensual_bruto'] / 2, 2);
        $salario_a_pagar = $salario_quincenal_completo;
        $descripcion_salario = 'Salario Quincenal';
        
        // 2. Verificar si hay que prorratear el salario por nuevo ingreso.
        $fecha_inicio_contrato = new DateTime($empleado['fecha_inicio']);
        $fecha_inicio_periodo = new DateTime($fecha_inicio);
        $fecha_fin_periodo = new DateTime($fecha_fin);
        if ($fecha_inicio_contrato > $fecha_inicio_periodo && $fecha_inicio_contrato <= $fecha_fin_periodo) {
            $dias_laborables_a_pagar = 0;
            $cursor_fecha = clone $fecha_inicio_contrato;
            while ($cursor_fecha <= $fecha_fin_periodo) {
                $dia_semana = (int)$cursor_fecha->format('w');
                if ($dia_semana == 6) $dias_laborables_a_pagar += 0.5;
                elseif ($dia_semana != 0) $dias_laborables_a_pagar += 1;
                $cursor_fecha->modify('+1 day');
            }
            $salario_diario = $empleado['salario_mensual_bruto'] / 23.83; 
            $salario_a_pagar = round($salario_diario * $dias_laborables_a_pagar, 2);
            $descripcion_salario = "Salario Quincenal (Prorrateado {$dias_laborables_a_pagar} días laborables)";
        }

            // --- FIN: CÁLCULO DE SALARIO Y MANEJO DE NOVEDAD DE OMISIÓN ---

        // --- INICIO: RECOLECCIÓN Y CLASIFICACIÓN ---
        $conceptos_del_empleado = [];
        $ingresos_fijos_isr = 0;
        $ingresos_extras_isr = 0;
        $ids_novedades_a_marcar = [];
        $monto_ajuste_isr = 0;
        $monto_saldo_a_favor = 0;

        // Bucle de Novedades optimizado
        $stmt_novedades = $pdo->prepare("SELECT n.id, n.monto_valor, c.codigo_concepto, c.descripcion_publica, c.tipo_concepto, c.afecta_tss, c.afecta_isr FROM novedadesperiodo n JOIN conceptosnomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND n.periodo_aplicacion BETWEEN ? AND ? AND n.estado_novedad = 'Pendiente'");
        $stmt_novedades->execute([$id_contrato, $fecha_inicio, $fecha_fin]);

        $novedad_skip_encontrada = false;

        foreach ($stmt_novedades->fetchAll(PDO::FETCH_ASSOC) as $novedad) {
            $codigo = $novedad['codigo_concepto'];
            $ids_novedades_a_marcar[] = $novedad['id']; // Marcar toda novedad leída para ser aplicada.

            if ($codigo === 'SYS-SKIP-PAYROLL') {
                $novedad_skip_encontrada = true;
                continue; // No procesar esta novedad como un ingreso/deducción normal.
            }

            if ($codigo === 'SYS-SALDO-FAVOR') {
                $monto_saldo_a_favor += $novedad['monto_valor'];
            } elseif ($codigo === 'DED-AJUSTE-ISR' || $codigo === 'ING-AJUSTE-ISR') {
                $monto_ajuste_isr += ($codigo === 'ING-AJUSTE-ISR' ? -$novedad['monto_valor'] : $novedad['monto_valor']);
            } else {
                if ($novedad['tipo_concepto'] === 'Ingreso' && $novedad['afecta_isr']) {
                    $ingresos_extras_isr += $novedad['monto_valor'];
                }
                if (!isset($conceptos_del_empleado[$codigo])) { 
                    $conceptos_del_empleado[$codigo] = ['monto' => 0, 'aplica_tss' => (bool)$novedad['afecta_tss'], 'aplica_isr' => (bool)$novedad['afecta_isr'], 'desc' => $novedad['descripcion_publica'], 'tipo' => $novedad['tipo_concepto']]; 
                }
                $conceptos_del_empleado[$codigo]['monto'] += $novedad['monto_valor'];
            }
        }

        // DECISIÓN FINAL: Después de revisar todas las novedades, si se encontró la de omitir, se anula el salario.
        if ($novedad_skip_encontrada) {
            $salario_a_pagar = 0;
            $descripcion_salario = 'Salario omitido por adelanto de vacaciones/bono';
        }

        // Finalmente, añadir el salario (que podría ser 0) y los ingresos recurrentes al array de conceptos.
        $conceptos_del_empleado['ING-SALARIO'] = ['monto' => $salario_a_pagar, 'aplica_tss' => true, 'aplica_isr' => true, 'desc' => $descripcion_salario, 'tipo' => 'Ingreso'];

       $ingresos_fijos_isr += $salario_a_pagar;
        $stmt_ing_rec = $pdo->prepare("SELECT ir.monto_ingreso, cn.codigo_concepto, cn.descripcion_publica, cn.afecta_tss, cn.afecta_isr FROM ingresosrecurrentes ir JOIN conceptosnomina cn ON ir.id_concepto_ingreso = cn.id WHERE ir.id_contrato = ? AND ir.estado = 'Activa' AND (ir.quincena_aplicacion = 0 OR ir.quincena_aplicacion = ?)");
        $stmt_ing_rec->execute([$id_contrato, $quincena]);
        foreach ($stmt_ing_rec->fetchAll(PDO::FETCH_ASSOC) as $ing_rec) {
            $codigo = $ing_rec['codigo_concepto'];
            if (!isset($conceptos_del_empleado[$codigo])) { $conceptos_del_empleado[$codigo] = ['monto' => 0, 'aplica_tss' => (bool)$ing_rec['afecta_tss'], 'aplica_isr' => (bool)$ing_rec['afecta_isr'], 'desc' => $ing_rec['descripcion_publica'], 'tipo' => 'Ingreso']; }
            $conceptos_del_empleado[$codigo]['monto'] += $ing_rec['monto_ingreso'];
      // Línea añadida para clasificar el ingreso
            if ($ing_rec['afecta_isr']) {
                $ingresos_fijos_isr += $ing_rec['monto_ingreso'];
            }
        }

        $stmt_ded_rec = $pdo->prepare("SELECT dr.monto_deduccion, cn.codigo_concepto, cn.descripcion_publica FROM deduccionesrecurrentes dr JOIN conceptosnomina cn ON dr.id_concepto_deduccion = cn.id WHERE dr.id_contrato = ? AND dr.estado = 'Activa' AND (dr.quincena_aplicacion = 0 OR dr.quincena_aplicacion = ?)");
        $stmt_ded_rec->execute([$id_contrato, $quincena]);
        foreach ($stmt_ded_rec->fetchAll(PDO::FETCH_ASSOC) as $ded_rec) {
            $codigo = $ded_rec['codigo_concepto'];
            if (!isset($conceptos_del_empleado[$codigo])) { $conceptos_del_empleado[$codigo] = ['monto' => 0, 'desc' => $ded_rec['descripcion_publica'], 'tipo' => 'Deducción']; }
            $conceptos_del_empleado[$codigo]['monto'] += $ded_rec['monto_deduccion'];
        }
        
        // Cálculo de TSS (sin cambios)
        $ingreso_total_tss = 0; $ingreso_total_isr = 0;
        foreach ($conceptos_del_empleado as $data) {
            if ($data['tipo'] === 'Ingreso') {
                if (!empty($data['aplica_tss'])) $ingreso_total_tss += $data['monto'];
                if (!empty($data['aplica_isr'])) $ingreso_total_isr += $data['monto'];
            }
        }
        $tope_sfs_mensual = (float)($configs_db['TSS_TOPE_SFS'] ?? 0); $tope_afp_mensual = (float)($configs_db['TSS_TOPE_AFP'] ?? 0);
        $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287); $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304);
        $sfs_deducido_previamente = 0; $afp_deducido_previamente = 0;
        if ($quincena == 2) {
            $stmt_prev_sfs = $pdo->prepare("SELECT SUM(monto_resultado) FROM nominadetalle nd JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'DED-SFS'");
            $stmt_prev_sfs->execute([$id_contrato, $mes, $anio]);
            $sfs_deducido_previamente = (float)$stmt_prev_sfs->fetchColumn();
            $stmt_prev_afp = $pdo->prepare("SELECT SUM(monto_resultado) FROM nominadetalle nd JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'DED-AFP'");
            $stmt_prev_afp->execute([$id_contrato, $mes, $anio]);
            $afp_deducido_previamente = (float)$stmt_prev_afp->fetchColumn();
        }
        $max_sfs_mensual = round($tope_sfs_mensual * $porcentaje_sfs, 2);
        $sfs_restante_a_deducir = max(0, $max_sfs_mensual - $sfs_deducido_previamente);
        $sfs_calculado_actual = round(min($ingreso_total_tss, $tope_sfs_mensual) * $porcentaje_sfs, 2);
        $deduccion_sfs = min($sfs_calculado_actual, $sfs_restante_a_deducir);
        $max_afp_mensual = round($tope_afp_mensual * $porcentaje_afp, 2);
        $afp_restante_a_deducir = max(0, $max_afp_mensual - $afp_deducido_previamente);
        $afp_calculado_actual = round(min($ingreso_total_tss, $tope_afp_mensual) * $porcentaje_afp, 2);
        $deduccion_afp = min($afp_calculado_actual, $afp_restante_a_deducir);
        
       // --- [INICIO] LÓGICA DE ISR v8 (Reconciliación Mensual Total) ---
$base_isr_quincenal_actual_neto = max(0, $ingreso_total_isr - ($deduccion_afp + $deduccion_sfs));
$deduccion_isr = 0;

if ($quincena == 1) {
    // --- LÓGICA PARA LA PRIMERA QUINCENA v3.0 (CON RECONCILIACIÓN ADELANTADA) ---

    // 1. VERIFICAR SI LA Q2 SERÁ OMITIDA
    $fecha_inicio_q2 = date('Y-m-16', strtotime($fecha_inicio));
    $fecha_fin_q2 = date('Y-m-t', strtotime($fecha_inicio));
    $stmt_check_skip_q2 = $pdo->prepare("SELECT COUNT(*) FROM novedadesperiodo n JOIN conceptosnomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND c.codigo_concepto = 'SYS-SKIP-PAYROLL' AND n.periodo_aplicacion BETWEEN ? AND ? AND n.estado_novedad = 'Pendiente'");
    $stmt_check_skip_q2->execute([$id_contrato, $fecha_inicio_q2, $fecha_fin_q2]);
    $skip_en_q2 = $stmt_check_skip_q2->fetchColumn() > 0;

    if ($skip_en_q2) {
        // CASO ESPECIAL: La Q2 se omitirá. SE EJECUTA LA LÓGICA DE CIERRE DE MES AHORA.
        // Esta es una copia adaptada de la lógica de la Q2.

        // a) Sumar TODAS las bases imponibles del mes (pagos especiales + esta Q1).
        $stmt_bases_pasadas = $pdo->prepare("SELECT SUM(nd.monto_resultado) FROM nominadetalle nd JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND nd.codigo_concepto IN ('BASE-ISR-QUINCENAL', 'BASE-ISR-MENSUAL') AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ?");
        $stmt_bases_pasadas->execute([$id_contrato, $mes, $anio]);
        $base_isr_pasada = (float)$stmt_bases_pasadas->fetchColumn();
        
        $base_isr_actual = max(0, $ingreso_total_isr - ($deduccion_afp + $deduccion_sfs));
        $base_isr_total_mes = $base_isr_pasada + $base_isr_actual;
        $ingreso_anual_proyectado = $base_isr_total_mes * 12;

        // b) Calcular el ISR total y correcto para el mes completo.
        $isr_anual_total = 0;
        foreach ($escala_isr as $escala) {
            if ($ingreso_anual_proyectado >= $escala['desde_monto_anual'] && ($ingreso_anual_proyectado <= $escala['hasta_monto_anual'] || $escala['hasta_monto_anual'] === null)) {
                $excedente = $ingreso_anual_proyectado - $escala['desde_monto_anual'];
                $isr_anual_total = ($excedente * ($escala['tasa_porcentaje'] / 100)) + $escala['monto_fijo_adicional'];
                break;
            }
        }
        $isr_total_del_mes = round($isr_anual_total / 12, 2);

        // c) Buscar ISR ya retenido en pagos especiales.
        $stmt_isr_retenido = $pdo->prepare("SELECT SUM(nd.monto_resultado) FROM nominadetalle nd JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND nd.codigo_concepto = 'DED-ISR' AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ?");
        $stmt_isr_retenido->execute([$id_contrato, $mes, $anio]);
        $isr_retenido_previamente = (float)$stmt_isr_retenido->fetchColumn();
        
        // d) La deducción para esta quincena es el total del mes menos lo ya retenido.
        $deduccion_isr = $isr_total_del_mes - $isr_retenido_previamente;

        // e) Traer las deducciones recurrentes de la Q2 a esta Q1.
        $stmt_ded_rec_q2 = $pdo->prepare("SELECT dr.monto_deduccion, cn.codigo_concepto, cn.descripcion_publica FROM deduccionesrecurrentes dr JOIN conceptosnomina cn ON dr.id_concepto_deduccion = cn.id WHERE dr.id_contrato = ? AND dr.estado = 'Activa' AND dr.quincena_aplicacion = 2");
        $stmt_ded_rec_q2->execute([$id_contrato]);
        foreach ($stmt_ded_rec_q2->fetchAll(PDO::FETCH_ASSOC) as $ded_rec_q2) {
            $codigo = $ded_rec_q2['codigo_concepto'];
            if (!isset($conceptos_del_empleado[$codigo])) { 
                $conceptos_del_empleado[$codigo] = ['monto' => 0, 'desc' => $ded_rec_q2['descripcion_publica'], 'tipo' => 'Deducción']; 
            }
            $conceptos_del_empleado[$codigo]['monto'] += $ded_rec_q2['monto_deduccion'];
        }
        
    } else {
        // CASO NORMAL: La Q2 se pagará, así que se usa la lógica estándar de Tasa Marginal.
        $salario_mensual_bruto = $empleado['salario_mensual_bruto'];
        $stmt_rec_mensual_isr = $pdo->prepare("SELECT SUM(ir.monto_ingreso) FROM ingresosrecurrentes ir JOIN conceptosnomina cn ON ir.id_concepto_ingreso = cn.id WHERE ir.id_contrato = ? AND ir.estado = 'Activa' AND cn.afecta_isr = 1");
        $stmt_rec_mensual_isr->execute([$id_contrato]);
        $ingreso_mensual_proyectable = $salario_mensual_bruto + (float)$stmt_rec_mensual_isr->fetchColumn();

        $sfs_mensual_teorico = round(min($ingreso_mensual_proyectable, $tope_sfs_mensual) * $porcentaje_sfs, 2);
        $afp_mensual_teorico = round(min($ingreso_mensual_proyectable, $tope_afp_mensual) * $porcentaje_afp, 2);
        
        $base_isr_anual_proyectada = ($ingreso_mensual_proyectable - ($sfs_mensual_teorico + $afp_mensual_teorico)) * 12;
        $isr_anual_fijo = 0; 
        $tasa_marginal = 0;

        foreach ($escala_isr as $escala) {
            if ($base_isr_anual_proyectada >= $escala['desde_monto_anual'] && ($base_isr_anual_proyectada <= $escala['hasta_monto_anual'] || $escala['hasta_monto_anual'] === null)) {
                $excedente = $base_isr_anual_proyectada - $escala['desde_monto_anual'];
                $fijo = (float)$escala['monto_fijo_adicional'];
                $tasa = (float)$escala['tasa_porcentaje'] / 100;
                $isr_anual_fijo = $fijo + ($excedente * $tasa);
                $tasa_marginal = $tasa;
                break;
            }
        }
        
        $isr_quincenal_fijo = round(max(0, $isr_anual_fijo / 24), 2);
        $isr_extras = round($ingresos_extras_isr * $tasa_marginal, 2);
        $deduccion_isr = $isr_quincenal_fijo + $isr_extras;
    }

} else { // --- CÁLCULO PARA LA SEGUNDA QUINCENA (CIERRE Y RECONCILIACIÓN) ---

    // 1. OBTENER TODOS los ingresos afectos a ISR del MES COMPLETO.
    $stmt_ingresos_mes_completo = $pdo->prepare("
        SELECT SUM(nd.monto_resultado) 
        FROM nominadetalle nd
        JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id
        JOIN conceptosnomina cn ON nd.codigo_concepto = cn.codigo_concepto
        WHERE nd.id_contrato = ? 
          AND cn.afecta_isr = 1 
          AND nd.tipo_concepto = 'Ingreso'
          AND MONTH(np.periodo_fin) = ? 
          AND YEAR(np.periodo_fin) = ?
    ");
    $stmt_ingresos_mes_completo->execute([$id_contrato, $mes, $anio]);
    $ingresos_pasados_del_mes = (float)$stmt_ingresos_mes_completo->fetchColumn();
    $ingresos_totales_reales_del_mes = $ingresos_pasados_del_mes + $ingreso_total_isr;

    // 2. OBTENER TODAS las deducciones de TSS del MES COMPLETO.
    $stmt_tss_mes_completo = $pdo->prepare("
        SELECT SUM(nd.monto_resultado) 
        FROM nominadetalle nd
        JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id
        WHERE nd.id_contrato = ? 
          AND nd.codigo_concepto IN ('DED-AFP', 'DED-SFS')
          AND MONTH(np.periodo_fin) = ? 
          AND YEAR(np.periodo_fin) = ?
    ");
    $stmt_tss_mes_completo->execute([$id_contrato, $mes, $anio]);
    $tss_pasado_del_mes = (float)$stmt_tss_mes_completo->fetchColumn();
    $tss_total_real_del_mes = $tss_pasado_del_mes + $deduccion_afp + $deduccion_sfs;

    // 3. CALCULAR la base imponible REAL del mes completo.
    $base_imponible_total_mes = $ingresos_totales_reales_del_mes - $tss_total_real_del_mes;
    $ingreso_anual_final = $base_imponible_total_mes * 12;
    $isr_anual_total = 0;

    // 4. APLICAR ESCALA DE ISR sobre la proyección anual REAL.
    foreach ($escala_isr as $escala) {
        if ($ingreso_anual_final >= $escala['desde_monto_anual'] && ($ingreso_anual_final <= $escala['hasta_monto_anual'] || $escala['hasta_monto_anual'] === null)) {
            $excedente = $ingreso_anual_final - $escala['desde_monto_anual'];
            $isr_anual_total = ($excedente * ($escala['tasa_porcentaje'] / 100)) + $escala['monto_fijo_adicional'];
            break;
        }
    }
    $isr_total_correcto_del_mes = round($isr_anual_total / 12, 2);

    // 5. OBTENER TODO el ISR ya retenido en el mes (Q1 + Pagos Especiales).
    $stmt_isr_retenido = $pdo->prepare("
        SELECT SUM(nd.monto_resultado) 
        FROM nominadetalle nd JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id
        WHERE nd.id_contrato = ? 
          AND nd.codigo_concepto = 'DED-ISR' 
          AND MONTH(np.periodo_fin) = ? 
          AND YEAR(np.periodo_fin) = ?
    ");
    $stmt_isr_retenido->execute([$id_contrato, $mes, $anio]);
    $isr_retenido_previamente = (float)$stmt_isr_retenido->fetchColumn();

    // 6. La retención para esta quincena es el total correcto menos lo ya retenido.
    $deduccion_isr = $isr_total_correcto_del_mes - $isr_retenido_previamente;
}

// AJUSTES FINALES (SIN CAMBIOS)
// Aplicar el ajuste manual primero
$deduccion_isr += $monto_ajuste_isr;

// Aplicar el Saldo a Favor
if ($monto_saldo_a_favor > 0) {
    $remanente = $monto_saldo_a_favor - $deduccion_isr;
    if ($remanente > 0) {
        $deduccion_isr = 0;
        $conceptos_del_empleado['ING-REEMBOLSO-SF'] = [
            'monto' => $remanente, 
            'aplica_tss' => false, 
            'aplica_isr' => false, 
            'desc' => 'Reembolso Saldo a Favor ISR', 
            'tipo' => 'Ingreso'
        ];
    } else {
        $deduccion_isr -= $monto_saldo_a_favor;
    }
}

// Línea de seguridad final para que el ISR nunca sea negativo.
$deduccion_isr = max(0, $deduccion_isr); 

$base_isr_quincenal_actual = max(0, $ingreso_total_isr - ($deduccion_afp + $deduccion_sfs));
// --- [FIN] LÓGICA DE ISR v8 ---


        // Guardado de Resultados (sin cambios)
        $stmt_detalle = $pdo->prepare("INSERT INTO nominadetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)");
        foreach($conceptos_del_empleado as $codigo => $data) { $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $codigo, $data['desc'], $data['tipo'], abs($data['monto'])]); }
        if ($deduccion_afp > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-AFP', 'Aporte AFP', 'Deducción', $deduccion_afp]);
        if ($deduccion_sfs > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-SFS', 'Aporte SFS', 'Deducción', $deduccion_sfs]);
        if ($deduccion_isr > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-ISR', 'Impuesto ISR', 'Deducción', $deduccion_isr]);
        if ($ingreso_total_tss > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'BASE-TSS-QUINCENAL', 'Base TSS Quincenal', 'Base de Cálculo', $ingreso_total_tss]);
        if ($base_isr_quincenal_actual > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'BASE-ISR-QUINCENAL', 'Base ISR Quincenal', 'Base de Cálculo', $base_isr_quincenal_actual]);
                // Marca como aplicadas solo las novedades que se procesaron en este ciclo.
        if (!empty($ids_novedades_a_marcar)) {
            $placeholders = rtrim(str_repeat('?,', count($ids_novedades_a_marcar)), ',');
            $stmt_marcar_novedades = $pdo->prepare("UPDATE novedadesperiodo SET estado_novedad = 'Aplicada' WHERE id IN ($placeholders)");
            $stmt_marcar_novedades->execute($ids_novedades_a_marcar);
        }

    }

    $pdo->commit();
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina_procesada . '&status=success&message=' . urlencode('Nómina Administrativa recalculada con la lógica de ISR corregida.'));
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: ' . BASE_URL . 'nomina_administrativa/index.php?status=error&message=' . urlencode('Error crítico: ' . $e->getMessage()));
    exit();
}
?>
