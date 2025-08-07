<?php
// time_tracking/index.php - v2.0
// Añade soporte para múltiples períodos abiertos a través de un selector.

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

// 3. Si un período está seleccionado, cargar los proyectos y zonas.
if ($periodo_seleccionado) {
    $proyectos = $pdo->query("SELECT id, nombre_proyecto FROM Proyectos WHERE estado_proyecto = 'Activo'")->fetchAll();
    $zonas = $pdo->query("SELECT id, nombre_zona_o_muelle FROM ZonasTransporte")->fetchAll();
}

// 4. Cargar los registros recientes del inspector (esto no cambia)
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
            <!-- Caso B: Un período está seleccionado (ya sea porque solo había uno, o porque el usuario lo eligió) -->
            <div class="alert alert-info">
                Período de reporte: <strong>Del <?php echo htmlspecialchars(date("d/m/Y", strtotime($periodo_seleccionado['fecha_inicio_periodo']))); ?> al <?php echo htmlspecialchars(date("d/m/Y", strtotime($periodo_seleccionado['fecha_fin_periodo']))); ?></strong>.
                <?php if ($num_periodos_abiertos > 1): ?>
                    <a href="index.php" class="alert-link ms-3">(Cambiar período)</a>
                <?php endif; ?>
            </div>
            <hr>
            <form action="store.php" method="POST">
                <input type="hidden" name="id_contrato" value="<?php echo htmlspecialchars($contrato_inspector_id); ?>">
                <input type="hidden" name="id_periodo_reporte" value="<?php echo htmlspecialchars($periodo_seleccionado['id']); ?>">
                <div class="row g-3">
                          <div class="col-md-4">
                              <label for="fecha_trabajada" class="form-label">Fecha</label>
                              <input type="date" class="form-control" name="fecha_trabajada" min="<?php echo htmlspecialchars($periodo_seleccionado['fecha_inicio_periodo']); ?>" max="<?php echo htmlspecialchars($periodo_seleccionado['fecha_fin_periodo']); ?>" required>
                          </div>
                          <div class="col-md-4">
                              <label for="hora_inicio" class="form-label">Hora Inicio (Formato 24h)</label>
                              <input type="number" class="form-control" name="hora_inicio" min="0" max="24" step="0.01" placeholder="Ej: 7 o 19.5" required>
                          </div>
                          <div class="col-md-4">
                              <label for="hora_fin" class="form-label">Hora Fin (Formato 24h)</label>
                              <input type="number" class="form-control" name="hora_fin" min="0" max="24" step="0.01" placeholder="Ej: 15.5 o 24" required>
                          </div>
                          <div class="col-md-6">
                              <label for="id_proyecto" class="form-label">Proyecto</label>
                              <select class="form-select" name="id_proyecto" required>
                                  <option value="">Seleccionar...</option>
                                  <?php foreach ($proyectos as $proyecto): ?><option value="<?php echo htmlspecialchars($proyecto['id']); ?>"><?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?></option><?php endforeach; ?>
                              </select>
                          </div>
                          <div class="col-md-6">
                              <label for="id_zona_trabajo" class="form-label">Zona / Muelle</label>
                              <select class="form-select" name="id_zona_trabajo" required>
                                  <option value="">Seleccionar...</option>
                                  <?php foreach ($zonas as $zona): ?><option value="<?php echo htmlspecialchars($zona['id']); ?>"><?php echo htmlspecialchars($zona['nombre_zona_o_muelle']); ?></option><?php endforeach; ?>
                              </select>
                          </div>

                          <!-- INICIO: Bloque de Horas de Gracia -->
                          <div class="col-12 mt-3">
                                <hr>
                                <label class="form-label fw-bold">Horas de Gracia (Opcional):</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="hora_gracia_antes" value="1" id="hora_gracia_antes">
                                    <label class="form-check-label" for="hora_gracia_antes">
                                        Incluir 1 hora de gracia ANTES del turno
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="hora_gracia_despues" value="1" id="hora_gracia_despues">
                                    <label class="form-check-label" for="hora_gracia_despues">
                                        Incluir 1 hora de gracia DESPUÉS del turno
                                    </label>
                                </div>
                          </div>
                          <!-- FIN: Bloque de Horas de Gracia -->

                          <div class="col-12 d-grid mt-4">
                              <button type="submit" class="btn btn-primary btn-lg">Registrar Horas</button>
                          </div>
                      </div>
            </form>

        <?php else: ?>
            <!-- Caso C: No hay ningún período abierto -->
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
