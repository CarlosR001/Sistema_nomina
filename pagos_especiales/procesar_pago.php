<?php
// pagos_especiales/procesar_pago.php - v2.1 (Añade estado inicial a nóminas especiales)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}
$id_nomina_a_recalcular = $_POST['id_nomina_a_recalcular'] ?? null;
$contrato_id_original = null;

if ($id_nomina_a_recalcular) {
    // Si estamos recalculando, debemos encontrar el contrato original de esa nómina.
    $stmt_contrato_original = $pdo->prepare(
        "SELECT DISTINCT id_contrato FROM nominadetalle WHERE id_nomina_procesada = ?"
    );
    $stmt_contrato_original->execute([$id_nomina_a_recalcular]);
    $contrato_id_original = $stmt_contrato_original->fetchColumn();
    
    if (!$contrato_id_original) {
        header('Location: index.php?status=error&message=' . urlencode('Error: No se pudo identificar al empleado de la nómina a recalcular.'));
        exit();
    }
}

try {
    $pdo->beginTransaction();
    if ($id_nomina_a_recalcular) {
        $pdo->prepare("DELETE FROM nominadetalle WHERE id_nomina_procesada = ?")->execute([$id_nomina_a_recalcular]);
        $pdo->prepare("DELETE FROM nominasprocesadas WHERE id = ?")->execute([$id_nomina_a_recalcular]);
    }
    // --- LÓGICA PARA REGALÍA ---
    if (isset($_POST['payment_type']) && $_POST['payment_type'] === 'regalia') {
        if (!isset($_SESSION['regalia_data'], $_SESSION['regalia_year'])) {
            throw new Exception("No se encontraron datos de regalía para procesar.");
        }
        $regalia_data = $_SESSION['regalia_data'];
        $year = $_SESSION['regalia_year'];
        
        $stmt_concepto = $pdo->prepare("SELECT id FROM conceptosnomina WHERE codigo_concepto = 'ING-REGALIA'");
        $stmt_concepto->execute();
        if (!$stmt_concepto->fetchColumn()) {
            $pdo->exec("INSERT INTO conceptosnomina (codigo_concepto, descripcion_publica, tipo_concepto, origen_calculo, afecta_tss, afecta_isr) VALUES ('ING-REGALIA', 'Pago de Regalía Pascual', 'Ingreso', 'Formula', 0, 1)");
        }

        $sql_nomina = "INSERT INTO nominasprocesadas (tipo_nomina_procesada, tipo_calculo_nomina, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES ('Directiva', 'Especial', ?, ?, ?, 'Calculada')";
        $stmt_nomina = $pdo->prepare($sql_nomina);
        $stmt_nomina->execute(["$year-12-01", "$year-12-31", $_SESSION['user_id']]);
        $id_nomina_procesada = $pdo->lastInsertId();

        $sql_detalle = "INSERT INTO nominadetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, 'ING-REGALIA', 'Pago de Regalía Pascual', 'Ingreso', ?)";
        $stmt_detalle = $pdo->prepare($sql_detalle);

        foreach ($regalia_data as $data) {
            if ($data['monto_regalia'] > 0) {
                $stmt_detalle->execute([$id_nomina_procesada, $data['contrato_id'], $data['monto_regalia']]);
            }
        }
        
        unset($_SESSION['regalia_data'], $_SESSION['regalia_year']);
        $pdo->commit();
        header('Location: ../payroll/show.php?id=' . $id_nomina_procesada . '&status=success&message=' . urlencode('Nómina de regalía generada exitosamente.'));
        exit();
    }

       // --- LÓGICA PARA PAGO ESPECIAL MANUAL ---
    $id_empleado = filter_input(INPUT_POST, 'id_empleado', FILTER_VALIDATE_INT);
    $fecha_pago = $_POST['fecha_pago'];
    $conceptos_post = $_POST['conceptos'];

    if (!$id_empleado || !$fecha_pago || !isset($conceptos_post['id']) || !is_array($conceptos_post['id'])) {
        throw new Exception("Datos del formulario inválidos o incompletos.");
    }

    // CORRECCIÓN: Se añade salario_mensual_bruto a la consulta y se usa FETCH_ASSOC.
    $stmt_contrato = $pdo->prepare("SELECT id, tipo_nomina, salario_mensual_bruto FROM contratos WHERE id_empleado = ? AND estado_contrato = 'Vigente'");
    $stmt_contrato->execute([$id_empleado]);
    $contrato = $stmt_contrato->fetch(PDO::FETCH_ASSOC);
    if (!$contrato) throw new Exception("No se encontró un contrato vigente para el empleado.");
    $id_contrato = $contrato['id'];

    $mes_pago = date('m', strtotime($fecha_pago));
    $anio_pago = date('Y', strtotime($fecha_pago));
    $configs_db = $pdo->query("SELECT clave, valor FROM configuracionglobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $escala_isr = $pdo->query("SELECT * FROM escalasisr WHERE anio_fiscal = {$anio_pago} ORDER BY desde_monto_anual ASC")->fetchAll(PDO::FETCH_ASSOC);

    $ingreso_total_tss = 0;
    $ingreso_total_isr = 0;
    $lineas_de_pago = [];
    $se_pago_bono_vacacional = false; // Nueva bandera


    foreach ($conceptos_post['id'] as $index => $id_concepto) {
        $monto_pago = filter_var($conceptos_post['monto'][$index], FILTER_VALIDATE_FLOAT);
        if (!$id_concepto || $monto_pago === false) continue;
        $stmt_concepto = $pdo->prepare("SELECT * FROM conceptosnomina WHERE id = ?");
        $stmt_concepto->execute([$id_concepto]);
        $concepto_info = $stmt_concepto->fetch();
        if (!$concepto_info) continue;
        if ($concepto_info['codigo_concepto'] === 'ING-Bono Vacacional') {
            $se_pago_bono_vacacional = true;
        }
        if ($concepto_info['afecta_tss']) $ingreso_total_tss += $monto_pago;
        if ($concepto_info['afecta_isr']) $ingreso_total_isr += $monto_pago;
        $lineas_de_pago[] = ['info' => $concepto_info, 'monto' => $monto_pago];
    }
    if (empty($lineas_de_pago)) throw new Exception("No se proporcionaron líneas de pago válidas.");

// --- [INICIO] LÓGICA DE ISR v6 (MÉTODO DE TASA MARGINAL ESTÁNDAR) ---
$deduccion_afp = 0; $deduccion_sfs = 0;
if ($ingreso_total_tss > 0) {
    $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287);
    $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304);
    $deduccion_sfs = round($ingreso_total_tss * $porcentaje_sfs, 2);
    $deduccion_afp = round($ingreso_total_tss * $porcentaje_afp, 2);
}

// 1. Obtener la base imponible NETA de este pago especial.
$base_isr_neta_pago_especial = max(0, $ingreso_total_isr - ($deduccion_afp + $deduccion_sfs));

// 2. Proyectar el ingreso FIJO del empleado para encontrar su tasa marginal de impuestos.
$salario_mensual_bruto = (float)$contrato['salario_mensual_bruto'];

// 3. Calcular deducciones teóricas de TSS sobre su salario mensual FIJO.
$sfs_mensual_teorico = round(min($salario_mensual_bruto, (float)($configs_db['TSS_TOPE_SFS'] ?? 0)) * (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304), 2);
$afp_mensual_teorico = round(min($salario_mensual_bruto, (float)($configs_db['TSS_TOPE_AFP'] ?? 0)) * (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287), 2);

// 4. Calcular la base anual proyectada SOLO de sus ingresos fijos.
$base_isr_anual_proyectada = ($salario_mensual_bruto - ($sfs_mensual_teorico + $afp_mensual_teorico)) * 12;

// 5. Determinar la Tasa Marginal basándose en la proyección anual.
$tasa_marginal = 0;
if (count($escala_isr) === 4) {
    $tramo1 = (float)$escala_isr[0]['hasta_monto_anual']; $tramo2 = (float)$escala_isr[1]['hasta_monto_anual']; $tramo3 = (float)$escala_isr[2]['hasta_monto_anual'];
    if ($base_isr_anual_proyectada > $tramo3) { $tasa_marginal = (float)$escala_isr[3]['tasa_porcentaje'] / 100; } 
    elseif ($base_isr_anual_proyectada > $tramo2) { $tasa_marginal = (float)$escala_isr[2]['tasa_porcentaje'] / 100; } 
    elseif ($base_isr_anual_proyectada > $tramo1) { $tasa_marginal = (float)$escala_isr[1]['tasa_porcentaje'] / 100; }
}

// 6. La deducción de ISR para ESTE PAGO es la base neta del pago por su tasa marginal.
$deduccion_isr = round($base_isr_neta_pago_especial * $tasa_marginal, 2);
// --- [FIN] LÓGICA DE ISR v6 ---

    // [CORRECCIÓN] Se añade el 'estado_nomina' al crear la cabecera.
    $sql_nomina = "INSERT INTO nominasprocesadas (tipo_nomina_procesada, tipo_calculo_nomina, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES (?, 'Especial', ?, ?, ?, 'Calculada')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    $stmt_nomina->execute([$contrato['tipo_nomina'], $fecha_pago, $fecha_pago, $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();

    $stmt_detalle = $pdo->prepare("INSERT INTO nominadetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($lineas_de_pago as $linea) { $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $linea['info']['codigo_concepto'], $linea['info']['descripcion_publica'], 'Ingreso', $linea['monto']]); }
    if ($deduccion_afp > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-AFP', 'Aporte AFP (2.87%)', 'Deducción', $deduccion_afp]);
    if ($deduccion_sfs > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-SFS', 'Aporte SFS (3.04%)', 'Deducción', $deduccion_sfs]);
    if ($deduccion_isr > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-ISR', 'Impuesto Sobre la Renta (ISR)', 'Deducción', $deduccion_isr]);
// Línea 153 CORREGIDA
$stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'BASE-ISR-MENSUAL', 'Base ISR Mensual Acumulada', 'Base de Cálculo', $ingreso_total_isr]);

    if ($se_pago_bono_vacacional) {
        // 1. Obtener el ID del concepto del sistema
        $stmt_skip_id = $pdo->prepare("SELECT id FROM conceptosnomina WHERE codigo_concepto = 'SYS-SKIP-PAYROLL'");
        $stmt_skip_id->execute();
        $skip_concept_id = $stmt_skip_id->fetchColumn();

        if ($skip_concept_id) {
            // 2. Determinar la fecha de la próxima quincena
            $fecha_pago_obj = new DateTime($fecha_pago);
            $dia_pago = (int)$fecha_pago_obj->format('d');
            
            if ($dia_pago <= 15) {
                // Si se pagó en la primera quincena, la próxima es a fin de mes.
                $fecha_proxima_quincena = $fecha_pago_obj->format('Y-m-t');
            } else {
                // Si se pagó en la segunda, la próxima es el 15 del mes siguiente.
                $fecha_pago_obj->modify('first day of next month');
                $fecha_proxima_quincena = $fecha_pago_obj->format('Y-m-15');
            }

            // 3. Insertar la novedad para el futuro
            $stmt_insert_skip = $pdo->prepare(
                "INSERT INTO novedadesperiodo (id_contrato, id_concepto, periodo_aplicacion, monto_valor, descripcion_adicional, estado_novedad) 
                 VALUES (?, ?, ?, 1, 'Adelanto por Bono Vacacional procesado en nómina #" . $id_nomina_procesada . "', 'Pendiente')"
            );
            $stmt_insert_skip->execute([$id_contrato, $skip_concept_id, $fecha_proxima_quincena]);
        }
    }
    $pdo->commit();
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina_procesada . '&status=processed_special');
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: index.php?status=error&message=' . urlencode('Error crítico al procesar el pago: ' . $e->getMessage()));
    exit();
}

