<?php
// novedades/ajuste_isr.php
// Interfaz para realizar ajustes manuales de ISR entre períodos.

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// 1. Obtener empleados activos del tipo 'Inspectores'
$stmt_empleados = $pdo->query("
    SELECT e.id, e.cedula, e.nombres, e.primer_apellido
    FROM empleados e
    JOIN contratos c ON e.id = c.id_empleado
    WHERE c.tipo_nomina = 'Inspectores' AND e.estado_empleado = 'Activo'
    ORDER BY e.nombres, e.primer_apellido
");
$empleados = $stmt_empleados->fetchAll();

// 2. Obtener períodos de reporte para los selectores
$periodos = $pdo->query("SELECT id, fecha_inicio_periodo, fecha_fin_periodo FROM periodosdereporte ORDER BY fecha_inicio_periodo DESC")->fetchAll();

require_once '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h5>Crear Nueva Transferencia de ISR</h5>
    </div>
    <div class="card-body">
        <p class="card-text text-muted">
            Esta herramienta permite transferir una porción de la carga del ISR de la última semana del mes a una semana anterior para evitar pagos netos negativos.
            Se creará una <strong>deducción</strong> en el período de origen y un <strong>ingreso</strong> por el mismo monto en el período de destino.
        </p>
        <form action="guardar_ajuste_isr.php" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres crear este ajuste? Se añadirán novedades a ambos períodos.');">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="id_empleado" class="form-label">Empleado</label>
                    <select class="form-select" id="id_empleado" name="id_empleado" required>
                        <option value="">Selecciona un empleado...</option>
                        <?php foreach ($empleados as $empleado): ?>
                            <option value="<?php echo $empleado['id']; ?>">
                                <?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido'] . ' (' . $empleado['cedula'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="monto" class="form-label">Monto a Transferir</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" class="form-control" id="monto" name="monto" placeholder="Ej: 5000.00" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="periodo_origen" class="form-label">Período de Origen (Donde se aplicará la DEDUCCIÓN)</label>
                    <select class="form-select" id="periodo_origen" name="periodo_origen" required>
                        <option value="">Selecciona un período...</option>
                        <?php foreach ($periodos as $periodo): ?>
                            <option value="<?php echo $periodo['id']; ?>">
                                Semana del <?php echo htmlspecialchars($periodo['fecha_inicio_periodo']) . " al " . htmlspecialchars($periodo['fecha_fin_periodo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="periodo_destino" class="form-label">Período de Destino (Donde se aplicará el INGRESO)</label>
                    <select class="form-select" id="periodo_destino" name="periodo_destino" required>
                        <option value="">Selecciona un período...</option>
                        <?php foreach ($periodos as $periodo): ?>
                            <option value="<?php echo $periodo['id']; ?>">
                                Semana del <?php echo htmlspecialchars($periodo['fecha_inicio_periodo']) . " al " . htmlspecialchars($periodo['fecha_fin_periodo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary">Guardar Ajuste</button>
            <a href="<?php echo BASE_URL; ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
