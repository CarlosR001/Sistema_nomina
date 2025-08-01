<?php
// payroll/generar_novedades.php
// Interfaz para iniciar el proceso de cálculo de horas y generación de novedades.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

// Obtener los períodos de reporte que están 'Abiertos' para la nómina de 'Inspectores'
$stmt_periodos = $pdo->query("
    SELECT id, fecha_inicio_periodo, fecha_fin_periodo 
    FROM PeriodosDeReporte 
    WHERE tipo_nomina = 'Inspectores' AND estado_periodo = 'Abierto'
    ORDER BY fecha_inicio_periodo DESC
");
$periodos_abiertos = $stmt_periodos->fetchAll();

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
        <h5>Paso 1: Seleccionar Período a Procesar</h5>
    </div>
    <div class="card-body">
        <p class="card-text text-muted">
            Selecciona un período de reporte abierto. El sistema buscará todas las horas con estado <strong>'Aprobado'</strong> dentro de este rango de fechas,
            calculará los montos correspondientes (normales, extras, feriados, etc.) y los insertará como novedades en el sistema, dejándolos listos para el cálculo de la nómina.
        </p>
        <p class="text-danger"><strong>Advertencia:</strong> Este proceso borrará cualquier novedad de ingresos (excepto ajustes manuales) previamente calculada para este período para evitar duplicados.</p>
        
        <?php if (empty($periodos_abiertos)): ?>
            <div class="alert alert-warning">No hay períodos de reporte abiertos para la nómina de Inspectores. Por favor, abre un período en "Entrada de Datos" -> "Períodos de Reporte".</div>
        <?php else: ?>
            <form action="procesar_horas.php" method="POST" onsubmit="return confirm('¿Estás seguro? Este proceso es intensivo y no se puede deshacer.');">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="periodo_id" class="form-label">Período de Reporte</label>
                        <select name="periodo_id" id="periodo_id" class="form-select" required>
                            <option value="">Selecciona un período...</option>
                            <?php foreach ($periodos_abiertos as $periodo): ?>
                                <option value="<?php echo $periodo['id']; ?>">
                                    Semana del <?php echo htmlspecialchars($periodo['fecha_inicio_periodo']) . " al " . htmlspecialchars($periodo['fecha_fin_periodo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-calculator"></i> Calcular y Generar Novedades
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
