<?php
// time_tracking/index.php - v1.1
// Modifica los campos de hora a campos numéricos para un registro más rápido.

require_once '../auth.php';
require_login();
require_role('Inspector');

$contrato_inspector_id = $_SESSION['contrato_inspector_id'] ?? null;
if (!$contrato_inspector_id) {
    header('Location: ' . BASE_URL . 'index.php?status=error&message=Su%20usuario%20no%20est%C3%A1%20vinculado%20a%20un%20contrato%20de%20inspector%20activo.');
    exit();
}

// 1. Buscar el ÚNICO período de reporte abierto para Inspectores
$stmt_periodo = $pdo->query("SELECT * FROM PeriodosDeReporte WHERE tipo_nomina = 'Inspectores' AND estado_periodo = 'Abierto' LIMIT 1");
$periodo_abierto = $stmt_periodo->fetch();

if ($periodo_abierto) {
    $proyectos = $pdo->query("SELECT id, nombre_proyecto FROM Proyectos WHERE estado_proyecto = 'Activo'")->fetchAll();
    $zonas = $pdo->query("SELECT id, nombre_zona_o_muelle FROM ZonasTransporte")->fetchAll();
}

$stmt_registros = $pdo->prepare("SELECT r.*, p.nombre_proyecto FROM RegistroHoras r JOIN Proyectos p ON r.id_proyecto = p.id WHERE r.id_contrato = ? ORDER BY r.fecha_trabajada DESC, r.hora_inicio DESC LIMIT 10");
$stmt_registros->execute([$contrato_inspector_id]);
$registros_recientes = $stmt_registros->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Portal de Registro de Horas</h1>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars(urldecode($_GET['message'] ?? 'Ocurrió un error.')); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">Registrar Nuevo Parte de Horas</div>
    <div class="card-body">
        <?php if ($periodo_abierto): ?>
            <div class="alert alert-info">Período de reporte abierto: Del <strong><?php echo htmlspecialchars($periodo_abierto['fecha_inicio_periodo']); ?></strong> al <strong><?php echo htmlspecialchars($periodo_abierto['fecha_fin_periodo']); ?></strong></div>
            <hr>
            <form action="store.php" method="POST">
                <input type="hidden" name="id_contrato" value="<?php echo htmlspecialchars($contrato_inspector_id); ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="fecha_trabajada" class="form-label">Fecha</label>
                        <input type="date" class="form-control" name="fecha_trabajada" min="<?php echo htmlspecialchars($periodo_abierto['fecha_inicio_periodo']); ?>" max="<?php echo htmlspecialchars($periodo_abierto['fecha_fin_periodo']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="hora_inicio" class="form-label">Hora Inicio (Formato 24h)</label>
                        <input type="number" class="form-control" name="hora_inicio" min="0" max="24" placeholder="Ej: 19" required>
                    </div>
                    <div class="col-md-3">
                        <label for="hora_fin" class="form-label">Hora Fin (Formato 24h)</label>
                        <input type="number" class="form-control" name="hora_fin" min="0" max="24" placeholder="Ej: 24" required>
                    </div>
                    <div class="col-md-3">
                        <label for="id_proyecto" class="form-label">Proyecto</label>
                        <select class="form-select" name="id_proyecto" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($proyectos as $proyecto): ?><option value="<?php echo htmlspecialchars($proyecto['id']); ?>"><?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label for="id_zona_trabajo" class="form-label">Zona / Muelle</label>
                        <select class="form-select" name="id_zona_trabajo" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($zonas as $zona): ?><option value="<?php echo htmlspecialchars($zona['id']); ?>"><?php echo htmlspecialchars($zona['nombre_zona_o_muelle']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Registrar Horas</button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning" role="alert">Actualmente no hay ningún período de reporte abierto. Contacte a su supervisor.</div>
        <?php endif; ?>
    </div>
</div>

<h3 class="mt-5">Mis Registros Recientes</h3>
<table class="table table-sm table-striped">
    <thead class="table-light"><tr><th>Fecha</th><th>Horario</th><th>Proyecto</th><th>Estado</th></tr></thead>
    <tbody>
    <?php if ($registros_recientes): ?>
        <?php foreach ($registros_recientes as $registro): ?>
            <tr>
                <td><?php echo htmlspecialchars($registro['fecha_trabajada']); ?></td>
                <td><?php echo htmlspecialchars(date('H:i', strtotime($registro['hora_inicio']))) . " - " . htmlspecialchars(date('H:i', strtotime($registro['hora_fin']))); ?></td>
                <td><?php echo htmlspecialchars($registro['nombre_proyecto']); ?></td>
                <td>
                    <?php
                    $estado = $registro['estado_registro'];
                    $clase_badge = 'bg-secondary';
                    if ($estado == 'Aprobado') $clase_badge = 'bg-success';
                    if ($estado == 'Pendiente') $clase_badge = 'bg-warning text-dark';
                    if ($estado == 'Rechazado') $clase_badge = 'bg-danger';
                    ?><span class="badge <?php echo $clase_badge; ?>"><?php echo htmlspecialchars($estado); ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="4" class="text-center text-muted">No tienes registros de horas recientes.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>
