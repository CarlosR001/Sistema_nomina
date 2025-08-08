<?php
// reporting_periods/index.php - v2.0 (Corregido)
// Restaura la funcionalidad completa y añade el botón de cierre para inspectores.

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// Lógica para el formulario de añadir nuevo período
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_period'])) {
    $fecha_inicio = $_POST['fecha_inicio_periodo'];
    $fecha_fin = $_POST['fecha_fin_periodo'];
    $tipo_nomina = $_POST['tipo_nomina'];

    if (!empty($fecha_inicio) && !empty($fecha_fin) && !empty($tipo_nomina)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO PeriodosDeReporte (fecha_inicio_periodo, fecha_fin_periodo, tipo_nomina, estado_periodo) VALUES (?, ?, ?, 'Abierto')");
            $stmt->execute([$fecha_inicio, $fecha_fin, $tipo_nomina]);
            $add_status = 'success';
            $add_message = 'Período de reporte añadido correctamente.';
        } catch (PDOException $e) {
            $add_status = 'error';
            $add_message = 'Error al añadir el período: ' . $e->getMessage();
        }
    } else {
        $add_status = 'error';
        $add_message = 'Todos los campos son obligatorios.';
    }
}

// Obtener todos los períodos para la tabla
$periodos = $pdo->query("SELECT * FROM PeriodosDeReporte ORDER BY fecha_inicio_periodo DESC")->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<h1 class="mb-4">Gestión de Períodos de Reporte</h1>

<?php if (isset($add_status)): ?>
    <div class="alert alert-<?php echo $add_status === 'success' ? 'success' : 'danger'; ?>"><?php echo $add_message; ?></div>
<?php endif; ?>
<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars(urldecode($_GET['message'])); ?></div>
<?php endif; ?>


<!-- Formulario para Añadir Nuevo Período -->
<div class="card mb-4">
    <div class="card-header">Añadir Nuevo Período de Reporte</div>
    <div class="card-body">
        <form action="" method="POST">
            <input type="hidden" name="add_period" value="1">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="fecha_inicio_periodo" class="form-label">Fecha de Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio_periodo" name="fecha_inicio_periodo" required>
                </div>
                <div class="col-md-4">
                    <label for="fecha_fin_periodo" class="form-label">Fecha de Fin</label>
                    <input type="date" class="form-control" id="fecha_fin_periodo" name="fecha_fin_periodo" required>
                </div>
                <div class="col-md-4">
                    <label for="tipo_nomina" class="form-label">Tipo de Nómina</label>
                    <select class="form-select" id="tipo_nomina" name="tipo_nomina" required>
                        <option value="Inspectores">Inspectores</option>
                        <option value="Administrativos">Administrativos</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Añadir Período</button>
        </form>
    </div>
</div>

<!-- Tabla de Períodos Existentes -->
<h3 class="mt-4">Períodos Existentes</h3>
<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Fecha de Inicio</th>
                <th>Fecha de Fin</th>
                <th>Tipo de Nómina</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($periodos)): ?>
                <tr><td colspan="5" class="text-center text-muted">No hay períodos de reporte creados.</td></tr>
            <?php else: ?>
                <?php foreach ($periodos as $periodo): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($periodo['fecha_inicio_periodo']))); ?></td>
                        <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($periodo['fecha_fin_periodo']))); ?></td>
                        <td><?php echo htmlspecialchars($periodo['tipo_nomina']); ?></td>
                        <td>
                            <?php
                                $estado = htmlspecialchars($periodo['estado_periodo']);
                                $clase_badge = 'bg-secondary';
                                if ($estado == 'Abierto') $clase_badge = 'bg-success';
                                if ($estado == 'Cerrado para Registro') $clase_badge = 'bg-warning text-dark';
                                if ($estado == 'Procesado y Finalizado') $clase_badge = 'bg-dark';
                            ?>
                            <span class="badge <?php echo $clase_badge; ?>"><?php echo $estado; ?></span>
                        </td>
                        <td>
                                <?php if ($periodo['estado_periodo'] === 'Abierto'): ?>
                                    <form action="update_status.php" method="POST" class="d-inline">
                                        <input type="hidden" name="periodo_id" value="<?php echo $periodo['id']; ?>">
                                        <input type="hidden" name="new_status" value="Cerrado para Registro">
                                        <button type="submit" class="btn btn-sm btn-warning">Cerrar para Inspectores</button>
                                    </form>
                                <?php elseif ($periodo['estado_periodo'] === 'Cerrado para Registro'): ?>
                                    <form action="update_status.php" method="POST" class="d-inline">
                                        <input type="hidden" name="periodo_id" value="<?php echo $periodo['id']; ?>">
                                        <input type="hidden" name="new_status" value="Abierto">
                                        <button type="submit" class="btn btn-sm btn-success">Forzar Reapertura</button>
                                    </form>
                                <?php endif; ?>

                                <?php // Botón de Eliminar: solo aparece si el período no está finalizado ?>
                                <?php if ($periodo['estado_periodo'] !== 'Procesado y Finalizado'): ?>
                                    <form action="delete.php" method="POST" class="d-inline ms-1" onsubmit="return confirm('¿Está seguro de que desea eliminar este período? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="periodo_id" value="<?php echo $periodo['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                    </form>
                                    <?php else: ?>
                                        <span class="text-muted small">No hay acciones</span>
                                     <?php endif; ?>
                            </td>

                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>
