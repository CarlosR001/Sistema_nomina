<?php
// time_tracking/index.php
require_once '../config/init.php';

// Lógica de seguridad
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

require_once '../includes/header.php';

// --- Lógica para el portal del inspector ---
$id_contrato_inspector = $_SESSION['id_contrato'];

// 1. Buscar el ÚNICO período de reporte abierto para Inspectores
$stmt_periodo = $pdo->query("SELECT * FROM PeriodosDeReporte WHERE tipo_nomina = 'Inspectores' AND estado_periodo = 'Abierto' LIMIT 1");
$periodo_abierto = $stmt_periodo->fetch();

// 2. Obtener datos para los dropdowns del formulario (solo si hay período abierto)
if ($periodo_abierto) {
    $proyectos = $pdo->query("SELECT id, nombre_proyecto FROM Proyectos WHERE estado_proyecto = 'Activo'")->fetchAll();
    $zonas = $pdo->query("SELECT id, nombre_zona_o_muelle FROM ZonasTransporte")->fetchAll();
}

// 3. Obtener los registros de horas recientes de este inspector
$stmt_registros = $pdo->prepare("SELECT r.*, p.nombre_proyecto FROM RegistroHoras r JOIN Proyectos p ON r.id_proyecto = p.id WHERE r.id_contrato = ? ORDER BY r.fecha_trabajada DESC, r.hora_inicio DESC LIMIT 10");
$stmt_registros->execute([$id_contrato_inspector]);
$registros_recientes = $stmt_registros->fetchAll(); 
?>

<h1 class="mb-4">Portal de Registro de Horas</h1>

<?php
if (isset($_GET['status'])) {
    $status = $_GET['status'];
    if ($status === 'success') {
        echo '<div class="alert alert-success" role="alert">¡Horas registradas correctamente!</div>';
    } elseif ($status === 'error') {
        $message = $_GET['message'] ?? '';
        $error_text = 'Ocurrió un error al registrar las horas.';
        if ($message === 'time_conflict') {
            $error_text = '<strong>Error:</strong> El horario que intentas registrar choca con uno ya existente en esa fecha.';
        } elseif ($message === 'period_closed_for_date') {
            $error_text = '<strong>Error:</strong> No hay un período de reporte abierto para la fecha que seleccionaste.';
        } elseif ($message === 'invalid_time_range') {
            $error_text = '<strong>Error:</strong> La hora de fin no puede ser anterior o igual a la hora de inicio.';
        }
        echo '<div class="alert alert-danger" role="alert">' . $error_text . '</div>';
    }
}
?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        Registrar Nuevo Parte de Horas
    </div>
    <div class="card-body">
        <?php if ($periodo_abierto): ?>
            <div class="alert alert-info">
                Período de reporte abierto: Del <strong><?php echo $periodo_abierto['fecha_inicio_periodo']; ?></strong> al <strong><?php echo $periodo_abierto['fecha_fin_periodo']; ?></strong>
            </div>
            <hr>
            <form action="store.php" method="POST">
                <input type="hidden" name="id_contrato" value="<?php echo $id_contrato_inspector; ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="fecha_trabajada" class="form-label">Fecha</label>
                        <input type="date" class="form-control" name="fecha_trabajada" 
                               min="<?php echo $periodo_abierto['fecha_inicio_periodo']; ?>" 
                               max="<?php echo $periodo_abierto['fecha_fin_periodo']; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="hora_inicio" class="form-label">Hora Inicio</label>
                        <input type="time" class="form-control" name="hora_inicio" required>
                    </div>
                    <div class="col-md-3">
                        <label for="hora_fin" class="form-label">Hora Fin</label>
                        <input type="time" class="form-control" name="hora_fin" required>
                    </div>
                     <div class="col-md-3">
                        <label for="id_proyecto" class="form-label">Proyecto</label>
                        <select class="form-select" name="id_proyecto" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($proyectos as $proyecto): ?>
                                <option value="<?php echo $proyecto['id']; ?>"><?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label for="id_zona_trabajo" class="form-label">Zona / Muelle</label>
                        <select class="form-select" name="id_zona_trabajo" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($zonas as $zona): ?>
                                <option value="<?php echo $zona['id']; ?>"><?php echo htmlspecialchars($zona['nombre_zona_o_muelle']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Registrar Horas</button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning" role="alert">
              Actualmente no hay ningún período de reporte abierto. Contacte a su supervisor.
            </div>
        <?php endif; ?>
    </div>
</div>

<h3 class="mt-5">Mis Registros Recientes</h3>
<table class="table table-sm table-striped">
    <thead class="table-light">
        <tr>
            <th>Fecha</th>
            <th>Horario</th>
            <th>Proyecto</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($registros_recientes): ?>
            <?php foreach ($registros_recientes as $registro): ?>
            <tr>
                <td><?php echo htmlspecialchars($registro['fecha_trabajada']); ?></td>
                <td><?php echo htmlspecialchars($registro['hora_inicio']) . " - " . htmlspecialchars($registro['hora_fin']); ?></td>
                <td><?php echo htmlspecialchars($registro['nombre_proyecto']); ?></td>
                <td>
                    <?php 
                    $estado = $registro['estado_registro'];
                    $clase_badge = 'bg-secondary';
                    if ($estado == 'Aprobado') $clase_badge = 'bg-success';
                    if ($estado == 'Pendiente') $clase_badge = 'bg-warning text-dark';
                    if ($estado == 'Rechazado') $clase_badge = 'bg-danger';
                    ?>
                    <span class="badge <?php echo $clase_badge; ?>"><?php echo htmlspecialchars($estado); ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" class="text-center text-muted">No hay registros de horas para este inspector.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php
require_once '../includes/footer.php'; 
?>