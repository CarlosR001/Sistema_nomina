<?php
// nomina_administrativa/procesar_nomina_admin.php - v4.0 (TSS Acumulativo Mensual)

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
        $pdo->prepare("DELETE FROM nominadetalle WHERE id_nomina_procesada = ?")->execute([$id_nomina_a_recalcular]);
        $pdo->prepare("DELETE FROM nominasprocesadas WHERE id = ?")->execute([$id_nomina_a_recalcular]);
    }
    
    $pdo->prepare("UPDATE novedadesperiodo SET estado_novedad = 'Pendiente' WHERE periodo_aplicacion BETWEEN ? AND ?")
        ->execute([$fecha_inicio, $fecha_fin]);
    
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
        $conceptos_del_empleado = [];
               // 1. Recopilación de Conceptos y Cálculo de Salario (con prorrateo por días laborables sin feriados)
        $salario_quincenal_completo = round($empleado['salario_mensual_bruto'] / 2, 2);
        $salario_a_pagar = $salario_quincenal_completo;
        $descripcion_salario = 'Salario Quincenal';

        $fecha_inicio_contrato = new DateTime($empleado['fecha_inicio']);
        $fecha_inicio_periodo = new DateTime($fecha_inicio);
        $fecha_fin_periodo = new DateTime($fecha_fin);

        // Verificar si el empleado es nuevo EN ESTE PERÍODO
        if ($fecha_inicio_contrato > $fecha_inicio_periodo && $fecha_inicio_contrato <= $fecha_fin_periodo) {
            
            $dias_laborables_a_pagar = 0;
            $cursor_fecha = clone $fecha_inicio_contrato;

            while ($cursor_fecha <= $fecha_fin_periodo) {
                $dia_semana = (int)$cursor_fecha->format('w'); // 0=Domingo, 6=Sábado

                if ($dia_semana == 0) { // Domingo
                    // No se cuenta.
                } elseif ($dia_semana == 6) { // Sábado
                    $dias_laborables_a_pagar += 0.5;
                } else { // Lunes a Viernes
                    $dias_laborables_a_pagar += 1;
                }
                $cursor_fecha->modify('+1 day');
            }
            
            // Usar el factor 23.83 para el salario diario según la regla de negocio
            $salario_diario = $empleado['salario_mensual_bruto'] / 23.83; 
            $salario_a_pagar = round($salario_diario * $dias_laborables_a_pagar, 2);
            $descripcion_salario = "Salario Quincenal (Prorrateado {$dias_laborables_a_pagar} días laborables)";
        }
        
        $conceptos_del_empleado['ING-SALARIO'] = ['monto' => $salario_a_pagar, 'aplica_tss' => true, 'aplica_isr' => true, 'desc' => $descripcion_salario, 'tipo' => 'Ingreso'];


        
        $stmt_ing_rec = $pdo->prepare("SELECT ir.monto_ingreso, cn.codigo_concepto, cn.descripcion_publica, cn.afecta_tss, cn.afecta_isr FROM ingresosrecurrentes ir JOIN conceptosnomina cn ON ir.id_concepto_ingreso = cn.id WHERE ir.id_contrato = ? AND ir.estado = 'Activa' AND (ir.quincena_aplicacion = 0 OR ir.quincena_aplicacion = ?)");
        $stmt_ing_rec->execute([$id_contrato, $quincena]);
        
        
        foreach ($stmt_ing_rec->fetchAll(PDO::FETCH_ASSOC) as $ing_rec) {
            $codigo = $ing_rec['codigo_concepto'];
            if (!isset($conceptos_del_empleado[$codigo])) { $conceptos_del_empleado[$codigo] = ['monto' => 0, 'aplica_tss' => (bool)$ing_rec['afecta_tss'], 'aplica_isr' => (bool)$ing_rec['afecta_isr'], 'desc' => $ing_rec['descripcion_publica'], 'tipo' => 'Ingreso']; }
            $conceptos_del_empleado[$codigo]['monto'] += $ing_rec['monto_ingreso'];
        }

        $stmt_novedades = $pdo->prepare("SELECT n.monto_valor, c.codigo_concepto, c.descripcion_publica, c.tipo_concepto, c.afecta_tss, c.afecta_isr FROM novedadesperiodo n JOIN conceptosnomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND n.periodo_aplicacion BETWEEN ? AND ? AND n.estado_novedad = 'Pendiente'");
        $stmt_novedades->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
        foreach ($stmt_novedades->fetchAll(PDO::FETCH_ASSOC) as $novedad) {
            $codigo = $novedad['codigo_concepto'];
            if (!isset($conceptos_del_empleado[$codigo])) { $conceptos_del_empleado[$codigo] = ['monto' => 0, 'aplica_tss' => (bool)$novedad['afecta_tss'], 'aplica_isr' => (bool)$novedad['afecta_isr'], 'desc' => $novedad['descripcion_publica'], 'tipo' => $novedad['tipo_concepto']]; }
            $conceptos_del_empleado[$codigo]['monto'] += $novedad['monto_valor'];
        }
        
        $stmt_ded_rec = $pdo->prepare("SELECT dr.monto_deduccion, cn.codigo_concepto, cn.descripcion_publica FROM deduccionesrecurrentes dr JOIN conceptosnomina cn ON dr.id_concepto_deduccion = cn.id WHERE dr.id_contrato = ? AND dr.estado = 'Activa' AND (dr.quincena_aplicacion = 0 OR dr.quincena_aplicacion = ?)");
        $stmt_ded_rec->execute([$id_contrato, $quincena]);
        foreach ($stmt_ded_rec->fetchAll(PDO::FETCH_ASSOC) as $ded_rec) {
            $codigo = $ded_rec['codigo_concepto'];
            if (!isset($conceptos_del_empleado[$codigo])) { $conceptos_del_empleado[$codigo] = ['monto' => 0, 'desc' => $ded_rec['descripcion_publica'], 'tipo' => 'Deducción']; }
            $conceptos_del_empleado[$codigo]['monto'] += $ded_rec['monto_deduccion'];
        }

        // --- CÁLCULOS TSS CON LÓGICA ACUMULATIVA MENSUAL ---
        $ingreso_total_tss = 0; $ingreso_total_isr = 0;
        foreach ($conceptos_del_empleado as $data) {
            if ($data['tipo'] === 'Ingreso') {
                if (!empty($data['aplica_tss'])) $ingreso_total_tss += $data['monto'];
                if (!empty($data['aplica_isr'])) $ingreso_total_isr += $data['monto'];
            }
        }
        
        $tope_sfs_mensual = (float)($configs_db['TSS_TOPE_SFS'] ?? 0);
        $tope_afp_mensual = (float)($configs_db['TSS_TOPE_AFP'] ?? 0);
        $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287);
        $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304);

        // Obtener lo ya deducido en la primera quincena (si aplica)
        $sfs_deducido_previamente = 0;
        $afp_deducido_previamente = 0;
        if ($quincena == 2) {
            $stmt_prev_sfs = $pdo->prepare("SELECT SUM(monto_resultado) FROM nominadetalle nd JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'DED-SFS'");
            $stmt_prev_sfs->execute([$id_contrato, $mes, $anio]);
            $sfs_deducido_previamente = (float)$stmt_prev_sfs->fetchColumn();

            $stmt_prev_afp = $pdo->prepare("SELECT SUM(monto_resultado) FROM nominadetalle nd JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'DED-AFP'");
            $stmt_prev_afp->execute([$id_contrato, $mes, $anio]);
            $afp_deducido_previamente = (float)$stmt_prev_afp->fetchColumn();
        }

        // Calcular deducción SFS para esta nómina
        $max_sfs_mensual = round($tope_sfs_mensual * $porcentaje_sfs, 2);
        $sfs_restante_a_deducir = max(0, $max_sfs_mensual - $sfs_deducido_previamente);
        $sfs_calculado_actual = round($ingreso_total_tss * $porcentaje_sfs, 2);
        $deduccion_sfs = min($sfs_calculado_actual, $sfs_restante_a_deducir);

        // Calcular deducción AFP para esta nómina
        $max_afp_mensual = round($tope_afp_mensual * $porcentaje_afp, 2);
        $afp_restante_a_deducir = max(0, $max_afp_mensual - $afp_deducido_previamente);
        $afp_calculado_actual = round($ingreso_total_tss * $porcentaje_afp, 2);
        $deduccion_afp = min($afp_calculado_actual, $afp_restante_a_deducir);
        
              // --- [INICIO] LÓGICA DE ISR CON DIVISIÓN PARA SALARIOS FIJOS ---

        // Primero, determinar si el empleado tuvo ingresos variables en esta quincena.
        $tiene_ingresos_variables = false;
        foreach ($conceptos_del_empleado as $codigo => $data) {
            if ($data['tipo'] === 'Ingreso' && $codigo !== 'ING-SALARIO' && $data['monto'] > 0) {
                $tiene_ingresos_variables = true;
                break;
            }
        }

        $base_isr_quincenal_actual = $ingreso_total_isr - ($deduccion_afp + $deduccion_sfs);

        // Escenario 1: Primera quincena y el empleado NO tiene ingresos variables.
        if ($quincena == 1 && !$tiene_ingresos_variables) {
            // Calcular el ISR mensual total basado solo en el salario fijo y dividirlo entre 2.
            $salario_mensual = $empleado['salario_mensual_bruto'];
            
            // Calcular deducciones TSS mensuales teóricas para obtener la base ISR mensual
            $sfs_mensual_teorico = round(min($salario_mensual, $tope_sfs_mensual) * $porcentaje_sfs, 2);
            $afp_mensual_teorico = round(min($salario_mensual, $tope_afp_mensual) * $porcentaje_afp, 2);
            
            $base_isr_mensual = $salario_mensual - ($sfs_mensual_teorico + $afp_mensual_teorico);
            $ingreso_anual_proyectado = $base_isr_mensual * 12;
            $isr_anual = 0;

            if (count($escala_isr) === 4) {
                $tramo1 = (float)$escala_isr[0]['hasta_monto_anual']; $tramo2 = (float)$escala_isr[1]['hasta_monto_anual']; $tramo3 = (float)$escala_isr[2]['hasta_monto_anual'];
                if ($ingreso_anual_proyectado > $tramo3) { $excedente = $ingreso_anual_proyectado - $tramo3; $tasa = (float)$escala_isr[3]['tasa_porcentaje'] / 100; $fijo = (float)$escala_isr[3]['monto_fijo_adicional']; $isr_anual = $fijo + ($excedente * $tasa); } 
                elseif ($ingreso_anual_proyectado > $tramo2) { $excedente = $ingreso_anual_proyectado - $tramo2; $tasa = (float)$escala_isr[2]['tasa_porcentaje'] / 100; $fijo = (float)$escala_isr[2]['monto_fijo_adicional']; $isr_anual = $fijo + ($excedente * $tasa); } 
                elseif ($ingreso_anual_proyectado > $tramo1) { $excedente = $ingreso_anual_proyectado - $tramo1; $tasa = (float)$escala_isr[1]['tasa_porcentaje'] / 100; $fijo = (float)$escala_isr[1]['monto_fijo_adicional']; $isr_anual = $fijo + ($excedente * $tasa); }
            }
            
            $isr_mensual_total = $isr_anual / 12;
            $deduccion_isr = round(max(0, $isr_mensual_total / 2), 2);

        } else {
            // Escenario 2: Es la segunda quincena O el empleado SÍ tiene ingresos variables.
            // Se utiliza la lógica acumulativa estándar, que es la más precisa en estos casos.
            $sql_bases_previas = "SELECT SUM(nd.monto_resultado) FROM nominadetalle nd JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'BASE-ISR-QUINCENAL'";
            $stmt_bases_previas = $pdo->prepare($sql_bases_previas);
            $stmt_bases_previas->execute([$id_contrato, $mes, $anio]);
            $base_isr_acumulada_previa = (float)$stmt_bases_previas->fetchColumn();

                       // [NUEVO] Sumar también el ISR retenido en pagos especiales del mismo mes
            $sql_isr_especial = "SELECT SUM(nd.monto_resultado) FROM nominadetalle nd JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'DED-ISR' AND np.tipo_calculo_nomina = 'Especial'";
            $stmt_isr_especial = $pdo->prepare($sql_isr_especial);
            $stmt_isr_especial->execute([$id_contrato, $mes, $anio]);
            $isr_retenido_especial = (float)$stmt_isr_especial->fetchColumn();
            $isr_retenido_previo += $isr_retenido_especial;

            
            $base_isr_mensual_total = $base_isr_quincenal_actual + $base_isr_acumulada_previa;
            $ingreso_anual_proyectado = $base_isr_mensual_total * 12;
            $isr_anual = 0;

            if (count($escala_isr) === 4) {
                $tramo1 = (float)$escala_isr[0]['hasta_monto_anual']; $tramo2 = (float)$escala_isr[1]['hasta_monto_anual']; $tramo3 = (float)$escala_isr[2]['hasta_monto_anual'];
                if ($ingreso_anual_proyectado > $tramo3) { $excedente = $ingreso_anual_proyectado - $tramo3; $tasa = (float)$escala_isr[3]['tasa_porcentaje'] / 100; $fijo = (float)$escala_isr[3]['monto_fijo_adicional']; $isr_anual = $fijo + ($excedente * $tasa); } 
                elseif ($ingreso_anual_proyectado > $tramo2) { $excedente = $ingreso_anual_proyectado - $tramo2; $tasa = (float)$escala_isr[2]['tasa_porcentaje'] / 100; $fijo = (float)$escala_isr[2]['monto_fijo_adicional']; $isr_anual = $fijo + ($excedente * $tasa); } 
                elseif ($ingreso_anual_proyectado > $tramo1) { $excedente = $ingreso_anual_proyectado - $tramo1; $tasa = (float)$escala_isr[1]['tasa_porcentaje'] / 100; $fijo = (float)$escala_isr[1]['monto_fijo_adicional']; $isr_anual = $fijo + ($excedente * $tasa); }
            }
            
            $isr_mensual_total_a_pagar = $isr_anual / 12;
            $isr_a_retener_en_esta_nomina = $isr_mensual_total_a_pagar - $isr_retenido_previo;
            
            $deduccion_isr = round(max(0, $isr_a_retener_en_esta_nomina), 2);
        }
        // --- [FIN] LÓGICA DE ISR ---


        // --- GUARDADO ---
        $stmt_detalle = $pdo->prepare("INSERT INTO nominadetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)");
        foreach($conceptos_del_empleado as $codigo => $data) { $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $codigo, $data['desc'], $data['tipo'], abs($data['monto'])]); }
        if ($deduccion_afp > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-AFP', 'Aporte AFP', 'Deducción', $deduccion_afp]);
        if ($deduccion_sfs > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-SFS', 'Aporte SFS', 'Deducción', $deduccion_sfs]);
        if ($deduccion_isr > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-ISR', 'Impuesto ISR', 'Deducción', $deduccion_isr]);
        
        if ($ingreso_total_tss > 0) {
            $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'BASE-TSS-QUINCENAL', 'Base TSS Quincenal', 'Base de Cálculo', $ingreso_total_tss]);
        }
        if ($base_isr_quincenal_actual > 0) {
            $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'BASE-ISR-QUINCENAL', 'Base ISR Quincenal', 'Base de Cálculo', $base_isr_quincenal_actual]);
        }

        $stmt_marcar_novedades = $pdo->prepare("UPDATE novedadesperiodo SET estado_novedad = 'Aplicada' WHERE id_contrato = ? AND periodo_aplicacion BETWEEN ? AND ?");
        $stmt_marcar_novedades->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
    }

    $pdo->commit();
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina_procesada . '&status=success&message=' . urlencode('Nómina Administrativa recalculada correctamente.'));
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: ' . BASE_URL . 'nomina_administrativa/index.php?status=error&message=' . urlencode('Error crítico: ' . $e->getMessage()));
    exit();
}
?>