<?php
// nomina_administrativa/procesar_nomina_admin.php - v2.1 (CORREGIDO)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// Validar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

// --- Lógica Unificada de Entrada ---
$id_nomina_a_recalcular = $_POST['id_nomina_a_recalcular'] ?? null;
$fecha_inicio_form = $_POST['fecha_inicio'] ?? null;
$fecha_fin_form = $_POST['fecha_fin'] ?? null;

$fecha_inicio = null;
$fecha_fin = null;

// Orden Lógico:
// 1. Si es un recálculo, tiene prioridad y define las fechas.
// 2. Si no, es un nuevo cálculo y toma las fechas del formulario.
if ($id_nomina_a_recalcular) {
    // Es un RECALCULO. Obtenemos las fechas desde la nómina existente.
    $stmt_recalc_info = $pdo->prepare("SELECT periodo_inicio, periodo_fin FROM nominasprocesadas WHERE id = ? AND tipo_nomina_procesada = 'Administrativa'");
    $stmt_recalc_info->execute([$id_nomina_a_recalcular]);
    $recalc_data = $stmt_recalc_info->fetch();
    if ($recalc_data) {
        $fecha_inicio = $recalc_data['periodo_inicio'];
        $fecha_fin = $recalc_data['periodo_fin'];
    }
} elseif ($fecha_inicio_form && $fecha_fin_form) {
    // Es un NUEVO procesamiento desde el formulario de selección de mes.
    $fecha_inicio = $fecha_inicio_form;
    $fecha_fin = $fecha_fin_form;
}

// Verificación final de fechas
if (!$fecha_inicio || !$fecha_fin) {
    header('Location: index.php?status=error&message=' . urlencode('Error crítico: Parámetros de período inválidos.'));
    exit();
}

try {
    $pdo->beginTransaction();

    // --- BLOQUE DE BORRADO (AHORA EN EL LUGAR CORRECTO) ---
    // Si estamos recalculando, borramos la nómina existente ANTES de crear la nueva.
    if ($id_nomina_a_recalcular) {
        // Borra los detalles (hijos) primero.
        $pdo->prepare("DELETE FROM nominadetalle WHERE id_nomina_procesada = ?")->execute([$id_nomina_a_recalcular]);
        // Luego borra la cabecera (padre).
        $pdo->prepare("DELETE FROM nominasprocesadas WHERE id = ?")->execute([$id_nomina_a_recalcular]);
    }
    
    // --- INICIO DEL PROCESAMIENTO ---
    $quincena = (int)date('d', strtotime($fecha_fin)) <= 15 ? 1 : 2;
    $mes = (int)date('m', strtotime($fecha_fin));
    $anio = (int)date('Y', strtotime($fecha_fin));

    $configs_db = $pdo->query("SELECT clave, valor FROM configuracionglobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $escala_isr = $pdo->query("SELECT * FROM escalasisr WHERE anio_fiscal = {$anio} ORDER BY desde_monto_anual ASC")->fetchAll(PDO::FETCH_ASSOC);

    $stmt_empleados = $pdo->prepare("SELECT c.id as id_contrato, c.salario_mensual_bruto FROM contratos c WHERE c.tipo_nomina IN ('Administrativa', 'Directiva') AND c.estado_contrato = 'Vigente' AND c.salario_mensual_bruto > 0");
    $stmt_empleados->execute();
    $empleados_a_procesar = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

    if (empty($empleados_a_procesar)) throw new Exception("No se encontraron empleados activos con salario para este tipo de nómina.");

    // Crear la nueva cabecera de nómina
    $sql_nomina = "INSERT INTO nominasprocesadas (tipo_nomina_procesada, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES ('Administrativa', ?, ?, ?, 'Calculada')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    $stmt_nomina->execute([$fecha_inicio, $fecha_fin, $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();

    foreach ($empleados_a_procesar as $empleado) {
        $id_contrato = $empleado['id_contrato'];
        $conceptos_del_empleado = [];

        // Salario Quincenal
        $salario_quincenal = round($empleado['salario_mensual_bruto'] / 2, 2);
        $conceptos_del_empleado['ING-SALARIO'] = ['monto' => $salario_quincenal, 'aplica_tss' => true, 'aplica_isr' => true, 'desc' => 'Salario Quincenal', 'tipo' => 'Ingreso'];

        // Novedades Manuales
        $stmt_novedades = $pdo->prepare("SELECT n.monto_valor, c.codigo_concepto, c.descripcion_publica, c.afecta_tss, c.afecta_isr FROM novedadesperiodo n JOIN conceptosnomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND n.periodo_aplicacion BETWEEN ? AND ? AND n.estado_novedad = 'Pendiente'");
        $stmt_novedades->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
        foreach ($stmt_novedades->fetchAll(PDO::FETCH_ASSOC) as $novedad) {
            $codigo = $novedad['codigo_concepto'];
            if (!isset($conceptos_del_empleado[$codigo])) {
                $conceptos_del_empleado[$codigo] = ['monto' => 0, 'aplica_tss' => (bool)$novedad['afecta_tss'], 'aplica_isr' => (bool)$novedad['afecta_isr'], 'desc' => $novedad['descripcion_publica'], 'tipo' => 'Ingreso'];
            }
            $conceptos_del_empleado[$codigo]['monto'] += $novedad['monto_valor'];
        }
        
        // Deducciones Recurrentes
        $stmt_ded_rec = $pdo->prepare("SELECT dr.monto_deduccion, cn.codigo_concepto, cn.descripcion_publica FROM deduccionesrecurrentes dr JOIN conceptosnomina cn ON dr.id_concepto_deduccion = cn.id WHERE dr.id_contrato = ? AND dr.estado = 'Activa' AND (dr.quincena_aplicacion = 0 OR dr.quincena_aplicacion = ?)");
        $stmt_ded_rec->execute([$id_contrato, $quincena]);
        foreach ($stmt_ded_rec->fetchAll(PDO::FETCH_ASSOC) as $ded_rec) {
            $conceptos_del_empleado[$ded_rec['codigo_concepto']] = ['monto' => $ded_rec['monto_deduccion'], 'desc' => $ded_rec['descripcion_publica'], 'tipo' => 'Deducción'];
        }

        // Calcular Impuestos
        $ingreso_total_tss = 0;
        $ingreso_total_isr = 0;
        foreach ($conceptos_del_empleado as $data) {
            if (($data['tipo'] ?? 'Ingreso') === 'Ingreso') {
                if (!empty($data['aplica_tss'])) $ingreso_total_tss += $data['monto'];
                if (!empty($data['aplica_isr'])) $ingreso_total_isr += $data['monto'];
            }
        }

        // Cálculo TSS
        $tope_salarial_tss = (float)($configs_db['TSS_TOPE_SALARIAL'] ?? 265840.00);
        $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287);
        $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304);
        $salario_cotizable_final = min($ingreso_total_tss, $tope_salarial_tss / 2); // Tope quincenal
        $deduccion_afp = round($salario_cotizable_final * $porcentaje_afp, 2);
        $deduccion_sfs = round($salario_cotizable_final * $porcentaje_sfs, 2);

        // Cálculo ISR
        $base_isr_quincenal = $ingreso_total_isr - ($deduccion_afp + $deduccion_sfs);
        $ingreso_anual_proyectado = ($base_isr_quincenal * 2) * 12;
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
        $deduccion_isr = round(max(0, $isr_anual / 12) / 2, 2);

        // Guardar detalles
        $stmt_detalle = $pdo->prepare("INSERT INTO nominadetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach($conceptos_del_empleado as $codigo => $data) {
             $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $codigo, $data['desc'], $data['tipo'], $data['monto']]);
        }
        if ($deduccion_afp > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-AFP', 'Aporte AFP', 'Deducción', $deduccion_afp]);
        if ($deduccion_sfs > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-SFS', 'Aporte SFS', 'Deducción', $deduccion_sfs]);
        if ($deduccion_isr > 0) $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, 'DED-ISR', 'Impuesto ISR', 'Deducción', $deduccion_isr]);

        // Marcar novedades como aplicadas
        $stmt_marcar_novedades = $pdo->prepare("UPDATE novedadesperiodo SET estado_novedad = 'Aplicada' WHERE id_contrato = ? AND periodo_aplicacion BETWEEN ? AND ? AND estado_novedad = 'Pendiente'");
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
