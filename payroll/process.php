<?php
// payroll/process.php - v5.1 FINAL con ISR Acumulativo en Última Semana

require_once '../auth.php';
require_login();
require_role('Admin');

// --- Helper Function ---
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

$periodo_id = $_POST['periodo_id'];
$stmt_periodo = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE id = ?");
$stmt_periodo->execute([$periodo_id]);
$periodo = $stmt_periodo->fetch();

if (!$periodo) {
    header('Location: ' . BASE_URL . 'payroll/index.php?status=error&message=Per%C3%ADodo%20no%20encontrado.');
    exit();
}

$tipo_nomina = $periodo['tipo_nomina'];
$fecha_inicio = $periodo['fecha_inicio_periodo'];
$fecha_fin = $periodo['fecha_fin_periodo'];
$es_ultima_semana = esUltimaSemanaDelMes($fecha_fin);
$mes_actual = date('m', strtotime($fecha_fin));
$anio_actual = date('Y', strtotime($fecha_fin));

try {
    $pdo->beginTransaction();

    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $tope_salarial_tss = (float)($configs_db['TSS_TOPE_SALARIAL'] ?? 265840.00);
    $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287);
    $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304);
    $escala_isr = $pdo->query("SELECT * FROM escalasisr WHERE anio_fiscal = {$anio_actual} ORDER BY desde_monto_anual ASC")->fetchAll();

    $stmt_find_nomina = $pdo->prepare("SELECT id FROM NominasProcesadas WHERE periodo_inicio = ? AND periodo_fin = ?");
    $stmt_find_nomina->execute([$fecha_inicio, $fecha_fin]);
    if ($existing_nomina = $stmt_find_nomina->fetch()) {
        $pdo->prepare("DELETE FROM NominaDetalle WHERE id_nomina_procesada = ?")->execute([$existing_nomina['id']]);
        $pdo->prepare("DELETE FROM NominasProcesadas WHERE id = ?")->execute([$existing_nomina['id']]);
        $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Pendiente' WHERE periodo_aplicacion BETWEEN ? AND ?")->execute([$fecha_inicio, $fecha_fin]);
    }

    $sql_nomina = "INSERT INTO NominasProcesadas (tipo_nomina_procesada, periodo_inicio, periodo_fin, id_usuario_ejecutor, id_empleado, estado_nomina) VALUES (?, ?, ?, ?, ?, 'Pendiente de Aprobación')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    
    $sql_contratos = "SELECT DISTINCT c.id, c.id_empleado FROM Contratos c JOIN NovedadesPeriodo np ON c.id = np.id_contrato WHERE np.periodo_aplicacion BETWEEN ? AND ?";
    $stmt_contratos = $pdo->prepare($sql_contratos);
    $stmt_contratos->execute([$fecha_inicio, $fecha_fin]);
    $contratos = $stmt_contratos->fetchAll();

    foreach ($contratos as $contrato) {
        $id_contrato = $contrato['id'];
        $id_empleado = $contrato['id_empleado'];
        $conceptos = [];

        if (empty($id_nomina_procesada)) {
             $stmt_nomina->execute([$tipo_nomina, $fecha_inicio, $fecha_fin, $_SESSION['user_id'], $id_empleado]);
             $id_nomina_procesada = $pdo->lastInsertId();
        }

        $stmt_novedades = $pdo->prepare("SELECT n.*, c.* FROM NovedadesPeriodo n JOIN ConceptosNomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND n.estado_novedad = 'Pendiente' AND n.periodo_aplicacion = ?");
        $stmt_novedades->execute([$id_contrato, $fecha_inicio]);
        foreach ($stmt_novedades->fetchAll() as $novedad) {
            $conceptos[$novedad['codigo_concepto']] = ['desc' => $novedad['descripcion_publica'], 'monto' => (float)$novedad['monto_valor'], 'aplica_tss' => (bool)$novedad['afecta_tss'], 'aplica_isr' => (bool)$novedad['afecta_isr'], 'tipo' => $novedad['tipo_concepto']];
            $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Aplicada' WHERE id = ?")->execute([$novedad['id']]);
        }

        $salario_cotizable_tss = 0;
        foreach ($conceptos as $data) { if ($data['tipo'] === 'Ingreso' && $data['aplica_tss']) { $salario_cotizable_tss += $data['monto']; } }
        $proyeccion_mensual_tss = $salario_cotizable_tss * (52/12);
        $salario_cotizable_final = min($proyeccion_mensual_tss, $tope_salarial_tss);
        $deduccion_afp = ($salario_cotizable_final * $porcentaje_afp) / (52/12);
        $deduccion_sfs = ($salario_cotizable_final * $porcentaje_sfs) / (52/12);
        $conceptos['DED-AFP'] = ['desc' => 'Aporte AFP (2.87%)', 'monto' => $deduccion_afp, 'tipo' => 'Deducción'];
        $conceptos['DED-SFS'] = ['desc' => 'Aporte SFS (3.04%)', 'monto' => $deduccion_sfs, 'tipo' => 'Deducción'];

        $base_para_isr_semanal = 0;
        foreach ($conceptos as $data) { if ($data['tipo'] === 'Ingreso' && $data['aplica_isr']) { $base_para_isr_semanal += $data['monto']; } }
        $base_para_isr_semanal -= ($deduccion_afp + $deduccion_sfs);
        $conceptos['BASE-ISR-SEMANAL'] = ['desc' => 'Base ISR Semanal', 'monto' => $base_para_isr_semanal, 'tipo' => 'Base de Cálculo'];

        $deduccion_isr = 0;
        if ($es_ultima_semana) {
            $sql_prev_base = "SELECT SUM(nd.monto_resultado) FROM NominaDetalle nd JOIN NominasProcesadas np ON nd.id_nomina_procesada = np.id JOIN Contratos c ON nd.id_contrato = c.id WHERE c.id_empleado = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'BASE-ISR-SEMANAL'";
            $stmt_prev_base = $pdo->prepare($sql_prev_base);
            $stmt_prev_base->execute([$id_empleado, $mes_actual, $anio_actual]);
            $base_isr_acumulada_previa = (float)$stmt_prev_base->fetchColumn();
            
            $base_isr_mensual_total = $base_para_isr_semanal + $base_isr_acumulada_previa;
            $conceptos['BASE-ISR-MENSUAL'] = ['desc' => 'Base ISR Mensual Acumulada', 'monto' => $base_isr_mensual_total, 'tipo' => 'Base de Cálculo'];
            
            $ingreso_anual_proyectado = $base_isr_mensual_total * 12;
            $isr_anual = 0;
            foreach ($escala_isr as $tramo) {
                if ($ingreso_anual_proyectado >= (float)$tramo['desde_monto_anual'] && ($tramo['hasta_monto_anual'] === null || $ingreso_anual_proyectado <= (float)$tramo['hasta_monto_anual'])) {
                    $excedente = $ingreso_anual_proyectado - (float)$tramo['desde_monto_anual'];
                    $isr_anual = ($excedente * (float)$tramo['tasa_porcentaje']) + (float)$tramo['monto_fijo_adicional'];
                    break;
                }
            }
            $deduccion_isr = max(0, $isr_anual / 12);
        }
        $conceptos['DED-ISR'] = ['desc' => 'Impuesto Sobre la Renta (ISR)', 'monto' => $deduccion_isr, 'tipo' => 'Deducción'];
        
        $sql_detalle = "INSERT INTO NominaDetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detalle = $pdo->prepare($sql_detalle);
        foreach ($conceptos as $codigo => $data) {
            if ($data['monto'] > 0.001) {
                $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $codigo, $data['desc'], $data['tipo'], $data['monto']]);
            }
        }
    }

    $stmt_cerrar = $pdo->prepare("UPDATE PeriodosDeReporte SET estado_periodo = 'Cerrado' WHERE id = ?");
    $stmt_cerrar->execute([$periodo_id]);

    $pdo->commit();
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina_procesada . '&status=processed');
    exit();

} catch (Exception $e) {
    if($pdo->inTransaction()) { $pdo->rollBack(); }
    die("Error Crítico al procesar la nómina: " . $e->getMessage());
}
?>
