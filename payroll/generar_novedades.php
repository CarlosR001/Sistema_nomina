<?php
// payroll/generar_novedades.php - v2.1
// Muestra una advertencia si se detectan horas sin aprobar durante la previsualización.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

// Recuperar resultados de la sesión, si existen.
$preview_results = $_SESSION['preview_results'] ?? null;
$preview_period_id = $_SESSION['preview_period_id'] ?? null;
$pending_hours_check = $_SESSION['pending_hours_check'] ?? null;

unset($_SESSION['preview_results'], $_SESSION['preview_period_id'], $_SESSION['pending_hours_check']);

$periodos_abiertos = $pdo->query("SELECT id, fecha_inicio_periodo, fecha_fin_periodo FROM PeriodosDeReporte WHERE tipo_nomina = 'Inspectores' AND estado_periodo = 'Abierto' ORDER BY fecha_inicio_periodo DESC")->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Generar Novedades desde Horas</h1>
</div>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5>Paso 1: Seleccionar Período y Previsualizar</h5>
    </div>
    <div class="card-body">
        <?php if (empty($periodos_abiertos)): ?>
            <div class="alert alert-warning">No hay períodos de reporte abiertos para la nómina de Inspectores.</div>
        <?php else: ?>
            <form action="procesar_horas.php" method="POST">
                <input type="hidden" name="mode" value="preview">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="periodo_id" class="form-label">Período de Reporte</label>
                        <select name="periodo_id" id="periodo_id" class="form-select" required>
                            <option value="">Selecciona un período...</option>
                            <?php foreach ($periodos_abiertos as $periodo): ?>
                                <option value="<?php echo $periodo['id']; ?>" <?php if ($periodo['id'] == $preview_period_id) echo 'selected'; ?>>
                                    Semana del <?php echo htmlspecialchars($periodo['fecha_inicio_periodo']) . " al " . htmlspecialchars($periodo['fecha_fin_periodo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-info w-100"><i class="bi bi-eye"></i> Previsualizar Cálculo</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($preview_results)): ?>
    <?php if (isset($pending_hours_check) && $pending_hours_check > 0): ?>
    <div class="alert alert-warning mt-4">
        <strong><i class="bi bi-exclamation-triangle-fill"></i> Atención:</strong> Se han detectado <strong><?php echo $pending_hours_check; ?></strong> registro(s) de horas en estado 'Pendiente' o 'Rechazado' para este período.
        Estos registros no serán incluidos en el cálculo. Se recomienda <a href="../approvals/index.php" class="alert-link">ir a Aprobaciones</a> para revisarlos antes de continuar.
    </div>
    <?php endif; ?>

    <div class="card mt-4">
        <div class="card-header bg-success text-white">
            <h5>Paso 2: Previsualización de Resultados</h5>
        </div>
        <div class="card-body">
            <!-- (Tabla de previsualización sin cambios) -->
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>Empleado</th><th class="text-end">Pago Normal</th><th class="text-end">Pago Extra</th><th class="text-end">Pago Feriado</th><th class="text-end">Bono Nocturno</th><th class="text-end">Transporte</th><th class="text-end"><strong>Ingreso Bruto</strong></th></tr></thead>
                <tbody>
                <?php if (empty($preview_results)): ?>
                    <tr><td colspan="7" class="text-center">No hay horas aprobadas para calcular en este período.</td></tr>
                <?php else: ?>
                    <?php foreach ($preview_results as $res): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($res['nombre_empleado']); ?></td>
                            <td class="text-end"><?php echo number_format($res['pago_normal'] ?? 0, 2); ?></td>
                            <td class="text-end"><?php echo number_format($res['pago_extra'] ?? 0, 2); ?></td>
                            <td class="text-end"><?php echo number_format($res['pago_feriado'] ?? 0, 2); ?></td>
                            <td class="text-end"><?php echo number_format($res['pago_nocturno'] ?? 0, 2); ?></td>
                            <td class="text-end"><?php echo number_format($res['pago_transporte'] ?? 0, 2); ?></td>
                            <td class="text-end"><strong><?php echo number_format($res['ingreso_bruto'] ?? 0, 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <hr>
            <p class="text-danger"><strong>Advertencia:</strong> Al confirmar, se borrarán y reemplazarán las novedades de ingreso previamente calculadas para este período.</p>
            <form action="procesar_horas.php" method="POST">
                <input type="hidden" name="periodo_id" value="<?php echo htmlspecialchars($preview_period_id); ?>">
                <input type="hidden" name="mode" value="final">
                <button type="submit" class="btn btn-success" <?php if (empty($preview_results)) echo 'disabled'; ?>>
                    <i class="bi bi-check-circle"></i> Confirmar y Guardar Novedades
                </button>
                <a href="generar_novedades.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
