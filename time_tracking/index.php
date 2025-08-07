<?php
// time_tracking/index.php - v2.2 (Versión final y corregida)

require_once '../auth.php';
require_login();
require_role('Inspector');

$contrato_inspector_id = $_SESSION['contrato_inspector_id'] ?? null;
if (!$contrato_inspector_id) {
    header('Location: ' . BASE_URL . 'index.php?status=error&message=Su%20usuario%20no%20est%C3%A1%20vinculado%20a%20un%20contrato%20de%20inspector%20activo.');
    exit();
}

// 1. Buscar TODOS los períodos de reporte abiertos para Inspectores
$stmt_periodos = $pdo->query("SELECT * FROM PeriodosDeReporte WHERE tipo_nomina = 'Inspectores' AND estado_periodo = 'Abierto' ORDER BY fecha_inicio_periodo DESC");
$periodos_abiertos = $stmt_periodos->fetchAll();
$num_periodos_abiertos = count($periodos_abiertos);

// 2. Determinar el período seleccionado
$periodo_seleccionado = null;
if (isset($_GET['periodo_id'])) {
    foreach ($periodos_abiertos as $p) {
        if ($p['id'] == $_GET['periodo_id']) {
            $periodo_seleccionado = $p;
            break;
        }
    }
} elseif ($num_periodos_abiertos === 1) {
    // Si solo hay un período abierto, se selecciona automáticamente.
    $periodo_seleccionado = $periodos_abiertos[0];
}

// 3. Si un período está seleccionado, cargar toda la data necesaria.
$proyectos = [];
$zonas = [];
$registros_del_periodo = [];
if ($periodo_seleccionado) {
    $proyectos = $pdo->query("SELECT id, nombre_proyecto FROM Proyectos WHERE estado_proyecto = 'Activo'")->fetchAll();
    $zonas = $pdo->query("SELECT id, nombre_zona_o_muelle FROM ZonasTransporte")->fetchAll();
    
    // Carga los registros del período seleccionado
    $stmt_registros = $pdo->prepare("
        SELECT 
            r.fecha_trabajada, r.hora_inicio, r.hora_fin, r.estado_registro,
            r.hora_gracia_antes, r.hora_gracia_despues, r.transporte_aprobado,
            p.nombre_proyecto, z.nombre_zona_o_muelle
        FROM RegistroHoras r
        JOIN Proyectos p ON r.id_proyecto = p.id
        JOIN ZonasTransporte z ON r.id_zona_trabajo = z.id
        WHERE r.id_contrato = ? AND r.id_periodo_reporte = ?
        ORDER BY r.fecha_trabajada DESC, r.hora_inicio DESC
    ");
    $stmt_registros->execute([$contrato_inspector_id, $periodo_seleccionado['id']]);
    $registros_del_periodo = $stmt_registros->fetchAll();
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Portal de Registro de Horas</h1>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars(urldecode($_GET['message'] ?? 'Ocurrió un error.')); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">Registrar Nuevo Parte de Horas</div>
    <div class="card-body">

        <?php if ($num_periodos_abiertos > 1 && !$periodo_seleccionado): ?>
            <!-- Caso A: Más de un período abierto, y ninguno seleccionado todavía -->
            <div class="alert alert-info">Hay varios períodos de reporte abiertos. Por favor, seleccione uno para continuar.</div>
            <form method="GET" action="index.php">
                <div class="input-group">
                    <select name="periodo_id" class="form-select form-select-lg">
                        <option value="">-- Seleccione un período --</option>
                        <?php foreach ($periodos_abiertos as $p): ?>
                            <option value="<?php echo htmlspecialchars($p['id']); ?>">
                                Semana del <?php echo htmlspecialchars(date("d/m/Y", strtotime($p['fecha_inicio_periodo']))); ?> al <?php echo htmlspecialchars(date("d/m/Y", strtotime($p['fecha_fin_periodo']))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit">Cargar Período</button>
                </div>
            </form>

        <?php elseif ($periodo_seleccionado): ?>
            <!-- Caso B: Un período está seleccionado -->
            <div class="alert alert-info">
                Período de reporte: <strong>Del <?php echo htmlspecialchars(date("d/m/Y", strtotime($periodo_seleccionado['fecha_inicio_periodo']))); ?> al <?php echo htmlspecialchars(date("d/m/Y", strtotime($periodo_seleccionado['fecha_fin_periodo']))); ?></strong>.
                <?php if ($num_periodos_abiertos > 1): ?>
                    <a href="index.php" class="alert-link ms-3">(Cambiar período)</a>
                <?php endif; ?>
            </div>
            <hr>
            <form action="store.php" method="POST">
                <input type="hidden" name="id_periodo_reporte" value="<?php echo htmlspecialchars($periodo_seleccionado['id']); ?>">
                <div class="row g-3">
                    <div class="col-md-4"><label for="fecha_trabajada" class="form-label">Fecha</label><input type="date" class="form-control" name="fecha_trabajada" min="<?php echo htmlspecialchars($periodo_seleccionado['fecha_inicio_periodo']); ?>" max="<?php echo htmlspecialchars($periodo_seleccionado['fecha_fin_periodo']); ?>" required></div>
                    <div class="col-md-4"><label for="hora_inicio" class="form-label">Hora Inicio (24h)</label><input type="number" class="form-control" name="hora_inicio" min="0" max="24" required></div>
                    <div class="col-md-4"><label for="hora_fin" class="form-label">Hora Fin (24h)</label><input type="number" class="form-control" name="hora_fin" min="0" max="24" required></div>
                    <div class="col-md-6"><label for="id_proyecto" class="form-label">Proyecto</label><select class="form-select" name="id_proyecto" required><option value="">Seleccionar...</option><?php foreach ($proyectos as $proyecto): ?><option value="<?php echo htmlspecialchars($proyecto['id']); ?>"><?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label for="id_zona_trabajo" class="form-label">Zona / Muelle</label><select class="form-select" name="id_zona_trabajo" required><option value="">Seleccionar...</option><?php foreach ($zonas as $zona): ?><option value="<?php echo htmlspecialchars($zona['id']); ?>"><?php echo htmlspecialchars($zona['nombre_zona_o_muelle']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-12 mt-3"><hr><label class="form-label fw-bold">Horas de Gracia (Opcional):</label><div class="form-check"><input class="form-check-input" type="checkbox" name="hora_gracia_antes" value="1" id="hora_gracia_antes"><label class="form-check-label" for="hora_gracia_antes">Solicitar 1 hora de gracia ANTES del turno</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="hora_gracia_despues" value="1" id="hora_gracia_despues"><label class="form-check-label" for="hora_gracia_despues">Solicitar 1 hora de gracia DESPUÉS del turno</label></div></div>
                    <div class="col-12 d-grid mt-4"><button type="submit" class="btn btn-primary btn-lg">Registrar Horas</button></div>
                </div>
            </form>

        <?php else: ?>
            <!-- Caso C: No hay ningún período abierto -->
            <div class="alert alert-warning" role="alert">Actualmente no hay ningún período de reporte abierto. Contacte a su supervisor.</div>
        <?php endif; ?>
    </div>
</div>

<h3 class="mt-5">Horas Reportadas en este Período</h3>
<div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
        <thead class="table-dark">
            <tr><th>Fecha</th><th>Proyecto</th><th>Horario</th><th>Transporte</th><th class="text-center">Estado</th></tr>
        </thead>
        <tbody>
            <?php if (!$periodo_seleccionado || empty($registros_del_periodo)): ?>
                <tr><td colspan="5" class="text-center">Aún no has reportado horas para este período.</td></tr>
            <?php else: ?>
                <?php foreach ($registros_del_periodo as $registro): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($registro['fecha_trabajada'])); ?></td>
                        <td><?php echo htmlspecialchars($registro['nombre_proyecto']); ?></td>
                        <td>
                            <?php echo htmlspecialchars(date('H:i', strtotime($registro['hora_inicio']))) . " - " . htmlspecialchars(date('H:i', strtotime($registro['hora_fin']))); ?>
                            <?php
                                $horas_gracia = ($registro['hora_gracia_antes'] ? 1 : 0) + ($registro['hora_gracia_despues'] ? 1 : 0);
                                if ($horas_gracia > 0):
                            ?>
                                <span class="badge bg-info ms-1" title="Incluye <?php echo $horas_gracia; ?> hora(s) de gracia">+<?php echo $horas_gracia; ?>H</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($registro['nombre_zona_o_muelle']); ?>
                            <?php if ($registro['transporte_aprobado']): ?>
                                <i class="bi bi-check-circle-fill text-success" title="Transporte Aprobado"></i>
                            <?php else: ?>
                                <i class="bi bi-x-circle-fill text-danger" title="Transporte Denegado"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
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
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>
