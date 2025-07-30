<?php
// payroll/index.php

require_once '../auth.php';
require_login();
require_role('Admin'); // O ['Admin', 'Contabilidad'] si se define ese rol

$empleados_a_procesar = [];
$periodo_seleccionado_id = null;
$detalles_por_empleado = [];
$tipo_nomina_seleccionada = $_POST['tipo_nomina'] ?? 'Inspectores';

$stmt_periodos = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE estado_periodo = 'Abierto' AND tipo_nomina = ?");
$stmt_periodos->execute([$tipo_nomina_seleccionada]);
$periodos_abiertos = $stmt_periodos->fetchAll();

// Si se ha enviado el formulario de previsualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['periodo_id'])) {
    $periodo_seleccionado_id = $_POST['periodo_id'];
    
    $stmt_periodo_sel = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE id = ?");
    $stmt_periodo_sel->execute([$periodo_seleccionado_id]);
    $periodo_sel = $stmt_periodo_sel->fetch();
    $fecha_inicio = $periodo_sel['fecha_inicio_periodo'];
    $fecha_fin = $periodo_sel['fecha_fin_periodo'];

    // Obtener todos los contratos relevantes
    $sql_contratos = "SELECT DISTINCT c.id, e.id as empleado_id, e.nombres, e.primer_apellido, c.tarifa_por_hora 
                      FROM Contratos c 
                      JOIN Empleados e ON c.id_empleado = e.id
                      JOIN RegistroHoras rh ON c.id = rh.id_contrato
                      WHERE c.tipo_nomina = 'Inspectores' AND rh.estado_registro = 'Aprobado' AND rh.fecha_trabajada BETWEEN ? AND ?";
    $stmt_contratos = $pdo->prepare($sql_contratos);
    $stmt_contratos->execute([$fecha_inicio, $fecha_fin]);
    $empleados_a_procesar = $stmt_contratos->fetchAll();

    // Preparar consultas para detalles
    $horas_stmt = $pdo->prepare("SELECT rh.*, p.nombre_proyecto FROM RegistroHoras rh JOIN Proyectos p ON rh.id_proyecto = p.id WHERE rh.id_contrato = ? AND rh.fecha_trabajada BETWEEN ? AND ? AND rh.estado_registro = 'Aprobado' ORDER BY rh.fecha_trabajada");
    $novedades_stmt = $pdo->prepare("SELECT np.*, cn.descripcion_publica FROM NovedadesPeriodo np JOIN ConceptosNomina cn ON np.id_concepto = cn.id WHERE np.id_contrato = ? AND np.periodo_aplicacion BETWEEN ? AND ?");

    // Lógica de Pre-Cálculo de Ingreso Bruto
    foreach ($empleados_a_procesar as &$empleado) {
        $id_contrato = $empleado['id'];
        $tarifa_hora = (float)$empleado['tarifa_por_hora'];
        
        $horas_stmt->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
        $registros_horas = $horas_stmt->fetchAll();
        $detalles_por_empleado[$empleado['empleado_id']]['horas'] = $registros_horas;

        $novedades_stmt->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
        $novedades = $novedades_stmt->fetchAll();
        $detalles_por_empleado[$empleado['empleado_id']]['novedades'] = $novedades;
        
        // Simulación de cálculo de ingresos
        $total_horas_laborales = 0; $total_horas_feriado = 0; $total_horas_nocturnas = 0; $pago_transporte = 0;
        // ... (Aquí iría la lógica de cálculo de horas, simplificada o completa)
        // Por simplicidad, calculamos solo el total de horas para la vista principal
        $total_horas_semana = 0;
        foreach ($registros_horas as $reg) {
            $inicio = new DateTime($reg['hora_inicio']);
            $fin = new DateTime($reg['hora_fin']);
            if ($fin < $inicio) $fin->modify('+1 day');
            $total_horas_semana += ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
        }
        
        $empleado['total_horas_calculadas'] = $total_horas_semana;

        // Aquí se podría añadir una simulación más completa del ingreso bruto
        $empleado['ingreso_bruto_estimado'] = ($total_horas_semana * $tarifa_hora) + array_sum(array_column($novedades, 'monto_valor'));
    }
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Revisión y Procesamiento de Nómina</h1>

<div class="card mb-4">
    <div class="card-header">Paso 1: Seleccionar Período a Revisar</div>
    <div class="card-body">
        <form action="index.php" method="POST">
            <div class="row align-items-end">
                <div class="col-md-5">
                    <label for="tipo_nomina" class="form-label">Tipo de Nómina</label>
                    <select name="tipo_nomina" id="tipo_nomina" class="form-select" onchange="this.form.submit()">
                        <option value="Inspectores" <?php echo ($tipo_nomina_seleccionada == 'Inspectores') ? 'selected' : ''; ?>>Inspectores</option>
                        <option value="Administrativa" <?php echo ($tipo_nomina_seleccionada == 'Administrativa') ? 'selected' : ''; ?>>Administrativa</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="periodo_id" class="form-label">Períodos Abiertos:</label>
                    <select name="periodo_id" id="periodo_id" class="form-select" required>
                        <option value="">Seleccione un período...</option>
                        <?php foreach($periodos_abiertos as $periodo): ?>
                            <option value="<?php echo htmlspecialchars($periodo['id']); ?>" <?php echo ($periodo['id'] == $periodo_seleccionado_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($periodo['fecha_inicio_periodo'] . ' al ' . $periodo['fecha_fin_periodo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Previsualizar Nómina</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($empleados_a_procesar)): ?>
<div class="card">
    <div class="card-header bg-primary text-white">Paso 2: Revisión Detallada de Pre-Nómina</div>
    <div class="card-body">
        <p>Se procesará la nómina para <strong><?php echo count($empleados_a_procesar); ?> empleado(s)</strong>. Revise los detalles antes de confirmar.</p>
        
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Empleado</th>
                    <th class="text-end">Total Horas</th>
                    <th class="text-end">Ingreso Bruto Estimado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empleados_a_procesar as $empleado): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></td>
                        <td class="text-end"><?php echo number_format($empleado['total_horas_calculadas'], 2); ?></td>
                        <td class="text-end fw-bold">$<?php echo number_format($empleado['ingreso_bruto_estimado'], 2); ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#details-<?php echo $empleado['empleado_id']; ?>">
                                Ver Detalles
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="p-0">
                            <div class="collapse" id="details-<?php echo $empleado['empleado_id']; ?>">
                                <div class="p-3 bg-light border">
                                    <h5>Detalle de Horas Registradas</h5>
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-secondary">
                                            <tr><th>Fecha</th><th>Proyecto</th><th>Hora Inicio</th><th>Hora Fin</th><th class="text-end">Duración</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($detalles_por_empleado[$empleado['empleado_id']]['horas'] as $hora): 
                                                $inicio = new DateTime($hora['hora_inicio']);
                                                $fin = new DateTime($hora['hora_fin']);
                                                if ($fin < $inicio) $fin->modify('+1 day');
                                                $duracion = ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($hora['fecha_trabajada']); ?></td>
                                                <td><?php echo htmlspecialchars($hora['nombre_proyecto']); ?></td>
                                                <td><?php echo htmlspecialchars($inicio->format('h:i A')); ?></td>
                                                <td><?php echo htmlspecialchars($fin->format('h:i A')); ?></td>
                                                <td class="text-end"><?php echo number_format($duracion, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    
                                    <?php if(!empty($detalles_por_empleado[$empleado['empleado_id']]['novedades'])): ?>
                                    <h5 class="mt-3">Novedades Aplicadas</h5>
                                    <ul class="list-group">
                                        <?php foreach($detalles_por_empleado[$empleado['empleado_id']]['novedades'] as $novedad): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($novedad['descripcion_publica']); ?>
                                                <span class="badge bg-success rounded-pill">$<?php echo number_format($novedad['monto_valor'], 2); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form action="process.php" method="POST" class="mt-4 text-end">
            <input type="hidden" name="periodo_id" value="<?php echo htmlspecialchars($periodo_seleccionado_id); ?>">
            <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('¿Está seguro de que desea procesar la nómina? Esta acción es irreversible para este período.');">
                Confirmar y Procesar Nómina
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
