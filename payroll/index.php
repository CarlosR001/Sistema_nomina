<?php
// payroll/index.php - v3.1 Corregido

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

$empleados_a_procesar = [];
$periodo_seleccionado_id = null;
$detalles_por_empleado = [];
$error_message = null;
$tipo_nomina_seleccionada = $_POST['tipo_nomina'] ?? 'Inspectores';

try {
    $stmt_periodos = $pdo->prepare("SELECT * FROM periodosdereporte WHERE estado_periodo = 'Abierto' AND tipo_nomina = ?");
    $stmt_periodos->execute([$tipo_nomina_seleccionada]);
    $periodos_abiertos = $stmt_periodos->fetchAll();
} catch (Exception $e) {
    $error_message = "Error al cargar los períodos: " . $e->getMessage();
    $periodos_abiertos = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['periodo_id'])) {
    $periodo_seleccionado_id = $_POST['periodo_id'];
    
    try {
        $stmt_periodo_sel = $pdo->prepare("SELECT * FROM periodosdereporte WHERE id = ?");
        $stmt_periodo_sel->execute([$periodo_seleccionado_id]);
        $periodo_sel = $stmt_periodo_sel->fetch();

        if (!$periodo_sel) {
            throw new Exception("El período seleccionado (ID: {$periodo_seleccionado_id}) no es válido o no fue encontrado.");
        }

        $fecha_inicio = $periodo_sel['fecha_inicio_periodo'];
        $fecha_fin = $periodo_sel['fecha_fin_periodo'];

        $sql_empleados = "SELECT e.id as empleado_id, e.nombres, e.primer_apellido
                          FROM empleados e
                          WHERE e.id IN (
                              SELECT DISTINCT c.id_empleado
                              FROM contratos c
                              JOIN novedadesperiodo np ON c.id = np.id_contrato
                              WHERE np.periodo_aplicacion BETWEEN ? AND ?
                          )
                          ORDER BY e.nombres, e.primer_apellido";
        $stmt_empleados = $pdo->prepare($sql_empleados);
        $stmt_empleados->execute([$fecha_inicio, $fecha_fin]);
        $empleados_a_procesar = $stmt_empleados->fetchAll();

        if (empty($empleados_a_procesar)) {
            $error_message = "No se encontraron empleados con datos de nómina para el período seleccionado.";
        }
        
        // CORRECCIÓN: Se añadió `cn.tipo_concepto` a la consulta
        $novedades_stmt = $pdo->prepare("SELECT np.*, cn.descripcion_publica, cn.tipo_concepto FROM novedadesperiodo np JOIN conceptosnomina cn ON np.id_concepto = cn.id JOIN contratos c ON np.id_contrato = c.id WHERE c.id_empleado = ? AND np.periodo_aplicacion BETWEEN ? AND ?");

        foreach ($empleados_a_procesar as &$empleado) {
            $empleado_id = $empleado['empleado_id'];
            
            $novedades_stmt->execute([$empleado_id, $fecha_inicio, $fecha_fin]);
            $novedades = $novedades_stmt->fetchAll();
            $detalles_por_empleado[$empleado_id]['novedades'] = $novedades;
            
            $ingreso_bruto_estimado = 0;
            foreach ($novedades as $novedad) {
                if ($novedad['tipo_concepto'] === 'Ingreso') { // Esta es la línea 70
                    $ingreso_bruto_estimado += $novedad['monto_valor'];
                }
            }
            $empleado['ingreso_bruto_estimado'] = $ingreso_bruto_estimado;
        }
        unset($empleado);

    } catch (Exception $e) {
        $error_message = "Error al previsualizar la nómina: " . $e->getMessage();
    }
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Revisión y Procesamiento de Nómina</h1>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Paso 1: Seleccionar Período a Revisar</div>
    <div class="card-body">
        <form action="index.php" method="POST">
            <div class="row align-items-end">
                <div class="col-md-5">
                    <label for="tipo_nomina" class="form-label">Tipo de Nómina</label>
                    <select name="tipo_nomina" id="tipo_nomina" class="form-select" onchange="this.form.submit()">
                        <option value="Inspectores" <?php echo ($tipo_nomina_seleccionada == 'Inspectores') ? 'selected' : ''; ?>>Inspectores</option>
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
                    <th class="text-end">Ingreso Bruto Estimado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empleados_a_procesar as $empleado): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></td>
                        <td class="text-end fw-bold">$<?php echo number_format($empleado['ingreso_bruto_estimado'], 2); ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#details-<?php echo $empleado['empleado_id']; ?>">
                                Ver Detalles
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" class="p-0">
                            <div class="collapse" id="details-<?php echo $empleado['empleado_id']; ?>">
                                <div class="p-3 bg-light border">
                                    <h5 class="mt-3">Novedades Aplicadas</h5>
                                    <ul class="list-group">
                                        <?php foreach($detalles_por_empleado[$empleado['empleado_id']]['novedades'] as $novedad): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($novedad['descripcion_publica']); ?>
                                                <span class="badge bg-<?php echo $novedad['tipo_concepto'] === 'Ingreso' ? 'success' : 'danger'; ?> rounded-pill">$<?php echo number_format($novedad['monto_valor'], 2); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
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
