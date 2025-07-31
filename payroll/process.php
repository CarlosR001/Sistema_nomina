<?php
// payroll/process.php - v6.0 HÍBRIDO Y DEFINITIVO
// Calcula desde `registrohoras` si existe, y siempre suma las `novedadesperiodo`.

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

    // Limpieza de nómina previa...
    $stmt_find_nomina = $pdo->prepare("SELECT id FROM NominasProcesadas WHERE periodo_inicio = ? AND periodo_fin = ? AND tipo_nomina_procesada = ?");
    $stmt_find_nomina->execute([$fecha_inicio, $fecha_fin, $tipo_nomina]);
    if ($existing_nomina = $stmt_find_nomina->fetch()) {
        $pdo->prepare("DELETE FROM NominaDetalle WHERE id_nomina_procesada = ?")->execute([$existing_nomina['id']]);
        $pdo->prepare("DELETE FROM NominasProcesadas WHERE id = ?")->execute([$existing_nomina['id']]);
        $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Pendiente' WHERE periodo_aplicacion BETWEEN ? AND ?")->execute([$fecha_inicio, $fecha_fin]);
    }

    $sql_nomina = "INSERT INTO NominasProcesadas (tipo_nomina_procesada, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES (?, ?, ?, ?, 'Pendiente de Aprobación')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    $stmt_nomina->execute([$tipo_nomina, $fecha_inicio, $fecha_fin, $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();

    // Obtener todos los empleados que tengan HORAS o NOVEDADES en el período
    $sql_contratos = "SELECT DISTINCT c.id, c.id_empleado, c.tarifa_por_hora 
                      FROM Contratos c 
                      LEFT JOIN RegistroHoras rh ON c.id = rh.id_contrato AND rh.fecha_trabajada BETWEEN ? AND ? AND rh.estado_registro = 'Aprobado'
                      LEFT JOIN NovedadesPeriodo np ON c.id = np.id_contrato AND np.periodo_aplicacion BETWEEN ? AND ?
                      WHERE c.tipo_nomina = 'Inspectores' AND (rh.id IS NOT NULL OR np.id IS NOT NULL)";
    $stmt_contratos = $pdo->prepare($sql_contratos);
    $stmt_contratos->execute([$fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin]);
    $contratos = $stmt_contratos->fetchAll();

    foreach ($contratos as $contrato) {
        $id_contrato = $contrato['id'];
        $id_empleado = $contrato['id_empleado'];
        $conceptos = [];

        // PASO A.1: CALCULAR INGRESOS DESDE REGISTRO DE HORAS (SI EXISTEN)
        $horas_stmt = $pdo->prepare("SELECT fecha_trabajada, hora_inicio, hora_fin FROM RegistroHoras WHERE id_contrato = ? AND estado_registro = 'Aprobado' AND fecha_trabajada BETWEEN ? AND ?");
        $horas_stmt->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
        $registros_horas = $horas_stmt->fetchAll();
        
        if (!empty($registros_horas)) {
            $feriados_stmt = $pdo->prepare("SELECT fecha FROM CalendarioLaboralRD WHERE fecha BETWEEN ? AND ?");
            $feriados_stmt->execute([$fecha_inicio, $fecha_fin]);
            $dias_feriados = $feriados_stmt->fetchAll(PDO::FETCH_COLUMN);
            $tarifa_hora = (float)$contrato['tarifa_por_hora'];
            
            $total_horas_laborales = 0; $total_horas_feriado = 0; $total_horas_nocturnas = 0;
            // ... (lógica de cálculo de horas, que no cambia) ...
            foreach ($registros_horas as $registro) { /* ... */ }
            
            $horas_normales = min($total_horas_laborales, 44);
            $horas_extra_35 = max(0, min($total_horas_laborales - 44, 24));
            $horas_extra_100 = max(0, $total_horas_laborales - 68);
            
            $conceptos['ING-HN'] = ['desc' => 'Ingreso Horas Normales', 'monto' => $horas_normales * $tarifa_hora, 'aplica_tss' => true, 'aplica_isr' => true, 'tipo' => 'Ingreso'];
            $conceptos['ING-HE35'] = ['desc' => 'Ingreso Horas Extras 35%', 'monto' => $horas_extra_35 * $tarifa_hora * 1.35, 'aplica_tss' => false, 'aplica_isr' => true, 'tipo' => 'Ingreso'];
            $conceptos['ING-HE100'] = ['desc' => 'Ingreso Horas Extras 100%', 'monto' => $horas_extra_100 * $tarifa_hora * 2.0, 'aplica_tss' => false, 'aplica_isr' => true, 'tipo' => 'Ingreso'];
            $conceptos['ING-HFER'] = ['desc' => 'Ingreso Horas Feriados', 'monto' => $total_horas_feriado * $tarifa_hora * 2.0, 'aplica_tss' => false, 'aplica_isr' => true, 'tipo' => 'Ingreso'];
            $conceptos['ING-HNOT'] = ['desc' => 'Bono Horas Nocturnas', 'monto' => $total_horas_nocturnas * $tarifa_hora * 0.15, 'aplica_tss' => true, 'aplica_isr' => true, 'tipo' => 'Ingreso'];
        }

        // PASO A.2: CARGAR Y SUMAR NOVEDADES FINANCIERAS
        $stmt_novedades = $pdo->prepare("SELECT n.*, c.* FROM NovedadesPeriodo n JOIN ConceptosNomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND n.estado_novedad = 'Pendiente' AND n.periodo_aplicacion BETWEEN ? AND ?");
        $stmt_novedades->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
        foreach ($stmt_novedades->fetchAll() as $novedad) {
            $codigo = $novedad['codigo_concepto'];
            $monto = (float)$novedad['monto_valor'];
            
            if(isset($conceptos[$codigo])) {
                $conceptos[$codigo]['monto'] += $monto;
            } else {
                $conceptos[$codigo] = ['desc' => $novedad['descripcion_publica'], 'monto' => $monto, 'aplica_tss' => (bool)$novedad['afecta_tss'], 'aplica_isr' => (bool)$novedad['afecta_isr'], 'tipo' => $novedad['tipo_concepto']];
            }
            $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Aplicada' WHERE id = ?")->execute([$novedad['id']]);
        }
        
        // ... (resto del script: TSS, ISR, Guardado) ...
        // El resto del script funciona con el array $conceptos, por lo que no necesita cambios.
    }

    $pdo->commit();
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina_procesada . '&status=processed');
    exit();

} catch (Exception $e) {
    if($pdo->inTransaction()) { $pdo->rollBack(); }
    die("Error Crítico al procesar la nómina: " . $e->getMessage());
}
?>
