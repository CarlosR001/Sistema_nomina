<?php
// reporting_periods/index.php - v1.1
// Añade acciones para cerrar y reabrir períodos.

require_once '../auth.php';
require_login();
require_role('Admin');

$periodos = $pdo->query("SELECT id, fecha_inicio_periodo, fecha_fin_periodo, tipo_nomina, estado_periodo FROM PeriodosDeReporte ORDER BY fecha_inicio_periodo DESC")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Gestión de Períodos de Reporte</h1>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'] ?? 'Operación realizada.')); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Abrir Nuevo Período de Reporte</div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row align-items-end">
                <div class="col-md-4"><label for="fecha_inicio" class="form-label">Fecha de Inicio</label><input type="date" class="form-control" name="fecha_inicio" required></div>
                <div class="col-md-4"><label for="fecha_fin" class="form-label">Fecha de Fin</label><input type="date" class="form-control" name="fecha_fin" required></div>
                <div class="col-md-2"><label for="tipo_nomina" class="form-label">Para Nómina</label><select class="form-select" name="tipo_nomina"><option value="Inspectores">Inspectores</option><option value="Administrativa">Administrativa</option></select></div>
                <div class="col-md-2"><button class="btn btn-success w-100" type="submit">Abrir Período</button></div>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>Período</th>
            <th>Nómina</th>
            <th>Estado</th>
            <th class="text-center">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($periodos as $periodo): ?>
        <tr>
            <td><?php echo htmlspecialchars($periodo['fecha_inicio_periodo']) . " al " . htmlspecialchars($periodo['fecha_fin_periodo']); ?></td>
            <td><?php echo htmlspecialchars($periodo['tipo_nomina']); ?></td>
            <td>
                <span class="badge bg-<?php echo $periodo['estado_periodo'] == 'Abierto' ? 'success' : 'secondary'; ?>">
                    <?php echo htmlspecialchars($periodo['estado_periodo']); ?>
                </span>
            </td>
            <td class="text-center">
                <?php if ($periodo['estado_periodo'] == 'Abierto'): ?>
                    <form action="update_status.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres cerrar este período?');">
                        <input type="hidden" name="periodo_id" value="<?php echo $periodo['id']; ?>">
                        <input type="hidden" name="new_status" value="Cerrado">
                        <button type="submit" class="btn btn-sm btn-danger">Cerrar Período</button>
                    </form>
                <?php else: ?>
                     <form action="update_status.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres reabrir este período?');">
                        <input type="hidden" name="periodo_id" value="<?php echo $periodo['id']; ?>">
                        <input type="hidden" name="new_status" value="Abierto">
                        <button type="submit" class="btn btn-sm btn-info">Reabrir Período</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>
