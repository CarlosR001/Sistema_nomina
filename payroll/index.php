<?php
// payroll/index.php
require_once '../config/init.php';

// --- Verificación de Seguridad y Rol ---
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}
// Solo los Administradores pueden procesar la nómina.
if ($_SESSION['rol'] !== 'Administrador') {
    die('Acceso Denegado. No tienes los permisos necesarios para acceder a esta página.');
}
// --- Fin de la verificación ---

require_once '../includes/header.php';

$empleados_a_procesar = [];
$periodo_seleccionado_id = null;
$tipo_nomina_seleccionada = $_POST['tipo_nomina'] ?? 'Inspectores';

// Llenar el dropdown de períodos basado en el tipo de nómina seleccionado
$stmt_periodos = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE estado_periodo = 'Abierto' AND tipo_nomina = ?");
$stmt_periodos->execute([$tipo_nomina_seleccionada]);
$periodos_abiertos = $stmt_periodos->fetchAll();

// Si se ha enviado el formulario de previsualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['periodo_id'])) {
    $periodo_seleccionado_id = $_POST['periodo_id'];

    // Lógica de previsualización según el tipo de nómina
    if ($tipo_nomina_seleccionada === 'Inspectores') {
        // Muestra empleados que tienen horas aprobadas en el período seleccionado.
        $sql_preview = "SELECT DISTINCT e.id, e.nombres, e.primer_apellido,
                        (SELECT SUM(TIME_TO_SEC(TIMEDIFF(rh.hora_fin, rh.hora_inicio)) / 3600)
                         FROM RegistroHoras rh
                         JOIN PeriodosDeReporte pr_inner ON pr_inner.id = ?
                         WHERE rh.id_contrato = c.id AND rh.estado_registro = 'Aprobado' AND rh.fecha_trabajada BETWEEN pr_inner.fecha_inicio_periodo AND pr_inner.fecha_fin_periodo) as total_horas
                        FROM Contratos c
                        JOIN Empleados e ON c.id_empleado = e.id
                        WHERE c.id IN (
                            SELECT rh.id_contrato FROM RegistroHoras rh JOIN PeriodosDeReporte pr ON pr.id = ? WHERE rh.estado_registro = 'Aprobado' AND rh.fecha_trabajada BETWEEN pr.fecha_inicio_periodo AND pr.fecha_fin_periodo
                        )";

        $stmt_preview = $pdo->prepare($sql_preview);
        $stmt_preview->execute([$periodo_seleccionado_id, $periodo_seleccionado_id]);
        $empleados_a_procesar = $stmt_preview->fetchAll();

    } elseif ($tipo_nomina_seleccionada === 'Administrativa') {
        // Muestra todos los empleados con contrato administrativo vigente.
        $sql_preview = "SELECT c.id, e.nombres, e.primer_apellido, c.salario_mensual_bruto
                        FROM Contratos c
                        JOIN Empleados e ON c.id_empleado = e.id
                        WHERE c.tipo_nomina = 'Administrativa' AND c.estado_contrato = 'Vigente'";
        $empleados_a_procesar = $pdo->query($sql_preview)->fetchAll();
    }
}
?>

<h1 class="mb-4">Procesar Nómina</h1>

<div class="card mb-4">
    <div class="card-header">Paso 1: Seleccionar Nómina y Período</div>
    <div class="card-body">
        <form action="index.php" method="POST">
            <div class="row align-items-end">
                <div class="col-md-5">
                    <label for="tipo_nomina" class="form-label">Tipo de Nómina</label>
                    <select name="tipo_nomina" id="tipo_nomina" class="form-select" onchange="this.form.submit()">
                        <option value="Inspectores" <?php echo ($tipo_nomina_seleccionada == 'Inspectores') ? 'selected' : ''; ?>>Inspectores (Semanal)</option>
                        <option value="Administrativa" <?php echo ($tipo_nomina_seleccionada == 'Administrativa') ? 'selected' : ''; ?>>Administrativa (Quincenal)</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="periodo_id" class="form-label">Períodos Abiertos:</label>
                    <select name="periodo_id" id="periodo_id" class="form-select" required>
                        <option value="">Seleccione un período...</option>
                        <?php foreach($periodos_abiertos as $periodo): ?>
                            <option value="<?php echo $periodo['id']; ?>" <?php echo ($periodo['id'] == $periodo_seleccionado_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($periodo['fecha_inicio_periodo'] . ' al ' . $periodo['fecha_fin_periodo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-info w-100">Previsualizar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($empleados_a_procesar)): ?>
<div class="card">
    <div class="card-header bg-info text-dark">Paso 2: Previsualización de Nómina</div>
    <div class="card-body">
        <p>Se procesará la nómina para <strong><?php echo count($empleados_a_procesar); ?> empleado(s)</strong> en el período seleccionado.</p>

        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Empleado</th>
                    <th class="text-end"><?php echo ($tipo_nomina_seleccionada == 'Inspectores') ? 'Total Horas Aprobadas' : 'Salario Mensual Bruto'; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empleados_a_procesar as $empleado): ?>
                <tr>
                    <td><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></td>
                    <td class="text-end">
                        <?php
                        if ($tipo_nomina_seleccionada == 'Inspectores') {
                            echo number_format($empleado['total_horas'] ?? 0, 2);
                        } else {
                            echo '$' . number_format($empleado['salario_mensual_bruto'] ?? 0, 2);
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form action="process.php" method="POST" class="mt-4 text-end">
            <input type="hidden" name="periodo_id" value="<?php echo htmlspecialchars($periodo_seleccionado_id); ?>">
            <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('¿Está seguro de que desea procesar la nómina? Esta acción cerrará el período de reporte y no se puede deshacer.');">
                Confirmar y Procesar Nómina
            </button>
        </form>
    </div>
</div>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <div class="alert alert-warning">No se encontraron empleados o data para procesar en el período seleccionado. Verifique que las horas hayan sido aprobadas.</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>