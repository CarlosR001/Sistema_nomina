<?php
// reports/inspector_hours.php - v2.0 (Selección por Período)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar'); 

// Obtener la lista de inspectores (sin cambios)
$stmt_inspectores = $pdo->query("
    SELECT e.id, e.nombres, e.primer_apellido 
    FROM empleados e
    JOIN contratos c ON e.id = c.id_empleado
    WHERE c.tipo_nomina = 'Inspectores' AND c.estado_contrato = 'Vigente'
    ORDER BY e.nombres, e.primer_apellido
");
$inspectores = $stmt_inspectores->fetchAll();

// NUEVO: Obtener la lista de períodos cerrados o procesados para seleccionar
$stmt_periodos = $pdo->query("
    SELECT id, fecha_inicio_periodo, fecha_fin_periodo 
    FROM periodosdereporte 
    WHERE estado_periodo IN ('Cerrado para Registro', 'Procesado y Finalizado') 
    AND tipo_nomina = 'Inspectores'
    ORDER BY fecha_inicio_periodo DESC
");
$periodos = $stmt_periodos->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Reporte de Horas por Inspector</h1>

<div class="card">
    <div class="card-header">
        <h5>Seleccione los Parámetros del Reporte</h5>
    </div>
    <div class="card-body">
        <form action="generate_inspector_report.php" method="POST" target="_blank">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="id_empleado" class="form-label">Inspector</label>
                    <select class="form-select" id="id_empleado" name="id_empleado">
                        <option value="all">-- Todos los Inspectores --</option>
                        <?php foreach ($inspectores as $inspector): ?>
                            <option value="<?php echo $inspector['id']; ?>">
                                <?php echo htmlspecialchars($inspector['nombres'] . ' ' . $inspector['primer_apellido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label for="periodo_id" class="form-label">Período de Nómina</label>
                    <select class="form-select" id="periodo_id" name="periodo_id" required>
                        <option value="">Seleccione un período...</option>
                        <?php foreach ($periodos as $periodo): ?>
                            <option value="<?php echo $periodo['id']; ?>">
                                Semana del <?php echo htmlspecialchars($periodo['fecha_inicio_periodo']) . " al " . htmlspecialchars($periodo['fecha_fin_periodo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Generar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
