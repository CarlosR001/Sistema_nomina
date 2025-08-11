<?php
// pagos_especiales/procesar_pago.php - v2.0 (Multi-línea)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

try {
    $pdo->beginTransaction();
    // --- INICIO: BLOQUE PARA PROCESAR REGALÍA ---
    // Verificamos si el pago que se está procesando es 'regalia'.
    if (isset($_POST['payment_type']) && $_POST['payment_type'] === 'regalia') {
        
        // 1. Rescatar los datos de la sesión preparados por la previsualización.
        if (!isset($_SESSION['regalia_data'], $_SESSION['regalia_year'])) {
            throw new Exception("No se encontraron datos de regalía para procesar. Por favor, genere una previsualización primero.");
        }
        $regalia_data = $_SESSION['regalia_data'];
        $year = $_SESSION['regalia_year'];
        
        // 2. Asegurar que el concepto de nómina para la regalía exista.
        $stmt_concepto = $pdo->prepare("SELECT id FROM conceptosnomina WHERE codigo_concepto = 'ING-REGALIA'");
        $stmt_concepto->execute();
        if (!$stmt_concepto->fetchColumn()) {
            $pdo->exec("INSERT INTO conceptosnomina (codigo_concepto, descripcion_publica, tipo_concepto, origen_calculo, afecta_tss, afecta_isr) VALUES ('ING-REGALIA', 'Pago de Regalía Pascual', 'Ingreso', 'Formula', 0, 1)");
        }

        // 3. Crear la cabecera de la nómina de tipo 'Especial'.
        $sql_nomina = "INSERT INTO nominasprocesadas (tipo_nomina_procesada, tipo_calculo_nomina, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES ('Directiva', 'Especial', ?, ?, ?, 'Calculada')";
        $stmt_nomina = $pdo->prepare($sql_nomina);
        $stmt_nomina->execute(["$year-12-01", "$year-12-31", $_SESSION['user_id']]);
        $id_nomina_procesada = $pdo->lastInsertId();

        // 4. Insertar una línea de detalle por cada empleado en la nómina.
        $sql_detalle = "INSERT INTO nominadetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, 'ING-REGALIA', 'Pago de Regalía Pascual', 'Ingreso', ?)";
        $stmt_detalle = $pdo->prepare($sql_detalle);

        foreach ($regalia_data as $data) {
            if ($data['monto_regalia'] > 0) {
                $stmt_detalle->execute([$id_nomina_procesada, $data['contrato_id'], $data['monto_regalia']]);
            }
        }
        
        // 5. Limpiar la sesión y finalizar.
        unset($_SESSION['regalia_data'], $_SESSION['regalia_year']);
        $pdo->commit();
        header('Location: ../payroll/review.php?status=success&message=' . urlencode('Nómina de regalía generada exitosamente.'));
        exit(); // Detiene el script aquí para no ejecutar la lógica de pago manual.
    }
    // --- FIN: BLOQUE PARA PROCESAR REGALÍA ---

    // 1. Recoger datos generales
    $id_empleado = filter_input(INPUT_POST, 'id_empleado', FILTER_VALIDATE_INT);
    $fecha_pago = $_POST['fecha_pago'];
    $conceptos_post = $_POST['conceptos'];

    if (!$id_empleado || !$fecha_pago || !isset($conceptos_post['id']) || !is_array($conceptos_post['id'])) {
        throw new Exception("Datos del formulario inválidos o incompletos.");
    }

    // 2. Obtener contrato activo
    $stmt_contrato = $pdo->prepare("SELECT id, tipo_nomina FROM contratos WHERE id_empleado = ? AND estado_contrato = 'Vigente'");
    $stmt_contrato->execute([$id_empleado]);
    $contrato = $stmt_contrato->fetch();
    if (!$contrato) throw new Exception("No se encontró un contrato vigente para el empleado.");
    $id_contrato = $contrato['id'];

    // 3. Preparar datos para el cálculo (mes, año, escalas)
    $mes_pago = date('m', strtotime($fecha_pago));
    $anio_pago = date('Y', strtotime($fecha_pago));
    $configs_db = $pdo->query("SELECT clave, valor FROM configuracionglobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $escala_isr = $pdo->query("SELECT * FROM escalasisr WHERE anio_fiscal = {$anio_pago} ORDER BY desde_monto_anual ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 4. Procesar y sumar los conceptos de la entrada
    $ingreso_total_tss = 0;
    $ingreso_total_isr = 0;
    $lineas_de_pago = [];

    foreach ($conceptos_post['id'] as $index => $id_concepto) {
        $monto_pago = filter_var($conceptos_post['monto'][$index], FILTER_VALIDATE_FLOAT);
        if (!$id_concepto || $monto_pago === false) continue;

        $stmt_concepto = $pdo->prepare("SELECT * FROM conceptosnomina WHERE id = ?");
        $stmt_concepto->execute([$id_concepto]);
        $concepto_info = $stmt_concepto->fetch();
        if (!$concepto_info) continue;

        if ($concepto_info['afecta_tss']) $ingreso_total_tss += $monto_pago;
        if ($concepto_info['afecta_isr']) $ingreso_total_isr += $monto_pago;

        $lineas_de_pago[] = [
            'info' => $concepto_info,
            'monto' => $monto_pago
        ];
    }
    if (empty($lineas_de_pago)) throw new Exception("No se proporcionaron líneas de pago válidas.");

    // 5. Calcular Base para ISR: Acumular ingresos del mes + los actuales
    $stmt_bases_previas = $pdo->prepare("SELECT SUM(nd.monto_resultado) FROM nominadetalle nd JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'BASE-ISR-SEMANAL'");
    $stmt_bases_previas->execute([$id_contrato, $mes_pago, $anio_pago]);
    $base_isr_acumulada_semanal = (float)$stmt_bases_previas->fetchColumn();
    $base_isr_mensual_proyectada = $base_isr_acumulada_semanal + $ingreso_total_isr;

    // 6. Calcular Deducciones
    $deduccion_afp = 0;
    $deduccion_sfs = 0;
    if ($ingreso_total_tss > 0) {
        $tope_salarial_tss = (float)($configs_db['TSS_TOPE_SALARIAL'] ?? 265840.00);
        $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287);
        $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304);
        
        $salario_cotizable_final = min($ingreso_total_tss, $tope_salarial_tss);
        $deduccion_afp = round($salario_cotizable_final * $porcentaje_afp, 2);
        $deduccion_sfs = round($salario_cotizable_final * $porcentaje_sfs, 2);
    }
    
    $base_isr_neta = $base_isr_mensual_proyectada - ($deduccion_afp + $deduccion_sfs);
    $ingreso_anual_proyectado = $base_isr_neta * 12;
    $isr_anual = 0;
    // (Lógica de cálculo de escala de ISR)
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
    $isr_mensual_total_proyectado = round(max(0, $isr_anual / 12), 2);
    
    // 7. Ajustar ISR por lo que ya se haya retenido en otros pagos especiales
    $stmt_isr_previo = $pdo->prepare("SELECT SUM(nd.monto_resultado) FROM nominadetalle nd JOIN nominasprocesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'DED-ISR' AND np.tipo_calculo_nomina = 'Especial'");
    $stmt_isr_previo->execute([$id_contrato, $mes_pago, $anio_pago]);
    $isr_ya_retenido_especial = (float)$stmt_isr_previo->fetchColumn();
    $deduccion_isr = max(0, $isr_mensual_total_proyectado - $isr_ya_retenido_especial);

    // 8. Guardar la Nómina Especial y sus detalles
    $sql_nomina = "INSERT INTO nominasprocesadas (tipo_nomina_procesada, tipo_calculo_nomina, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES (?, 'Especial', ?, ?, ?, 'Procesado y Finalizado')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    $stmt_nomina->execute([$contrato['tipo_nomina'], $fecha_pago, $fecha_pago, $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();

    $stmt_detalle = $pdo->prepare("INSERT INTO nominadetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($lineas_de_pago as $linea) {
        $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $linea['info']['codigo_concepto'], $linea['info']['descripcion_publica'], 'Ingreso', $linea['monto']]);
    }
    
    if ($deduccion_afp > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-AFP', 'Aporte AFP (2.87%)', 'Deducción', $deduccion_afp]);
    if ($deduccion_sfs > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-SFS', 'Aporte SFS (3.04%)', 'Deducción', $deduccion_sfs]);
    if ($deduccion_isr > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-ISR', 'Impuesto Sobre la Renta (ISR)', 'Deducción', $deduccion_isr]);
    $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'BASE-ISR-MENSUAL', 'Base ISR Mensual Acumulada', 'Base de Cálculo', $base_isr_mensual_proyectada]);

    $pdo->commit();
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina_procesada . '&status=processed_special');
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: index.php?status=error&message=' . urlencode('Error crítico al procesar el pago: ' . $e->getMessage()));
    exit();
}
