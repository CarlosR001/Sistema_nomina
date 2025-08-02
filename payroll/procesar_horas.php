<?php
// payroll/procesar_horas.php - v3.0 (Lógica de Borrado Corregida)
// El script ahora solo borra los conceptos de ingreso derivados de horas,
// preservando las novedades manuales como incentivos.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

// ... (El resto del script, incluida la lógica de modo preview y cálculo de horas, no necesita cambios)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo_id'], $_POST['mode'])) {
    header('Location: generar_novedades.php?status=error&message=' . urlencode('Solicitud no válida.'));
    exit();
}
$periodo_id = $_POST['periodo_id'];
$mode = $_POST['mode'];

// --- LA CORRECCIÓN ESTÁ EN EL MODO 'FINAL' ---

try {
    if ($mode === 'final') {
        $pdo->beginTransaction();
    }

    $stmt_periodo = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE id = ?");
    $stmt_periodo->execute([$periodo_id]);
    $periodo = $stmt_periodo->fetch();
    if (!$periodo) {
        throw new Exception("Período no válido.");
    }
    $fecha_inicio = $periodo['fecha_inicio_periodo'];
    $fecha_fin = $periodo['fecha_fin_periodo'];

    // ... (toda la lógica de cálculo y previsualización se mantiene igual)

    // --- COMIENZO DEL PROCESO REAL ---
    $feriados_stmt = $pdo->prepare("SELECT fecha FROM CalendarioLaboralRD WHERE fecha BETWEEN ? AND ?");
    $feriados_stmt->execute([$fecha_inicio, $fecha_fin]);
    $feriados = $feriados_stmt->fetchAll(PDO::FETCH_COLUMN);
    $conceptos = $pdo->query("SELECT codigo_concepto, id FROM ConceptosNomina")->fetchAll(PDO::FETCH_KEY_PAIR);
    $zonas = $pdo->query("SELECT id, monto_transporte_completo FROM ZonasTransporte")->fetchAll(PDO::FETCH_KEY_PAIR);
    $sql_horas = "SELECT c.id as contrato_id, c.tarifa_por_hora, e.nombres, e.primer_apellido, rh.fecha_trabajada, rh.hora_inicio, rh.hora_fin, rh.id_zona_trabajo, rh.transporte_aprobado FROM Contratos c JOIN Empleados e ON c.id_empleado = e.id JOIN RegistroHoras rh ON c.id = rh.id_contrato WHERE c.tipo_nomina = 'Inspectores' AND rh.estado_registro = 'Aprobado' AND rh.fecha_trabajada BETWEEN ? AND ? ORDER BY c.id, rh.fecha_trabajada, rh.hora_inicio";
    $stmt_horas = $pdo->prepare($sql_horas);
    $stmt_horas->execute([$fecha_inicio, $fecha_fin]);
    $horas_aprobadas = $stmt_horas->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    
    $results = []; // Array para guardar los resultados del cálculo

    foreach ($horas_aprobadas as $contrato_id => $registros) {
        // ... (TODA la lógica de cálculo de horas, nocturnas, transporte, etc. va aquí como antes)
        // ... (Este bloque no necesita cambios y se mantiene idéntico a la v2.8)
    }
    
    // --- LÓGICA DE PREVISUALIZACIÓN Y GUARDADO ---
    if ($mode === 'preview') {
        // ... (código de preview sin cambios)
    } 
    elseif ($mode === 'final') {
        // --- CORRECCIÓN CLAVE: Borrado Selectivo ---
        // Se definen explícitamente solo los códigos de concepto que este proceso genera.
        $codigos_a_borrar_placeholders = implode(',', array_fill(0, 5, '?'));
        $codigos_a_borrar_valores = ['ING-NORMAL', 'ING-EXTRA', 'ING-FERIADO', 'ING-NOCTURNO', 'ING-TRANSP'];
        
        $sql_delete = "DELETE n FROM NovedadesPeriodo n 
                       JOIN ConceptosNomina c ON n.id_concepto = c.id
                       WHERE n.periodo_aplicacion = ? 
                       AND c.codigo_concepto IN ($codigos_a_borrar_placeholders)";
        
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute(array_merge([$fecha_inicio], $codigos_a_borrar_valores));
        // --- FIN DE LA CORRECCIÓN ---
        
        // La lógica de inserción se mantiene igual
        $stmt_insert = $pdo->prepare("INSERT INTO NovedadesPeriodo (id_contrato, id_concepto, periodo_aplicacion, monto_valor, estado_novedad) VALUES (?, ?, ?, ?, 'Pendiente')");
        foreach ($results as $contrato_id => $res) {
            // ... (código de inserción sin cambios)
        }
        
        $pdo->commit();
        header('Location: generar_novedades.php?status=success&message=' . urlencode('Proceso completado. Las novedades de horas han sido generadas.'));
        exit();
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    header('Location: generar_novedades.php?status=error&message=' . urlencode('Error crítico: ' . $e->getMessage()));
    exit();
}
?>
