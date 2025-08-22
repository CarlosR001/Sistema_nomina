<?php
// novedades/ajuste_isr.php - v2.0 (Soporte para Múltiples Tipos de Nómina)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// 1. Obtener TODOS los empleados activos con su tipo de nómina
$stmt_empleados = $pdo->query("
    SELECT e.id, e.cedula, e.nombres, e.primer_apellido, c.tipo_nomina
    FROM empleados e
    JOIN contratos c ON e.id = c.id_empleado
    WHERE e.estado_empleado = 'Activo' AND c.estado_contrato = 'Vigente'
    ORDER BY e.nombres, e.primer_apellido
");
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

// 2. Obtener períodos de reporte (se mantiene igual, es genérico)
$periodos = $pdo->query("SELECT id, fecha_inicio_periodo, fecha_fin_periodo FROM periodosdereporte ORDER BY fecha_inicio_periodo DESC")->fetchAll();

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h5>Crear Ajuste Manual de ISR</h5>
        </div>
        <div class="card-body">
            <p class="card-text text-muted">
                Esta herramienta permite transferir una carga del ISR de un período a otro para un empleado.
                Se creará una <strong>deducción</strong> (DED-AJUSTE-ISR) en el período de origen y un <strong>ingreso</strong> (ING-AJUSTE-ISR) por el mismo monto en el período de destino.
            </p>
            <form action="guardar_ajuste_isr.php" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres crear este ajuste? Se añadirán novedades a ambos períodos.');">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="id_empleado" class="form-label">Empleado</label>
                        <select class="form-select" id="id_empleado" name="id_empleado" required>
                            <option value="">Seleccione un empleado...</option>
                            <?php foreach ($empleados as $empleado): ?>
                                <option value="<?php echo htmlspecialchars($empleado['id']); ?>" data-nomina="<?php echo htmlspecialchars($empleado['tipo_nomina']); ?>">
                                    <?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido'] . ' (' . $empleado['cedula'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="monto_ajuste" class="form-label">Monto a Ajustar</label>
                        <input type="number" step="0.01" class="form-control" id="monto_ajuste" name="monto_ajuste" required>
                    </div>

                    <!-- Períodos para Inspectores -->
                    <div class="col-md-6" id="periodos_inspectores">
                        <label for="periodo_origen_insp" class="form-label">Período de Origen (Donde se deduce)</label>
                        <select class="form-select" id="periodo_origen_insp" name="periodo_origen_insp">
                            <option value="">Seleccione un período...</option>
                            <?php foreach ($periodos as $periodo): ?>
                                <option value="<?php echo htmlspecialchars($periodo['id']); ?>"><?php echo htmlspecialchars($periodo['fecha_inicio_periodo'] . ' al ' . $periodo['fecha_fin_periodo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="col-md-6" id="periodos_destino_inspectores">
                        <label for="periodo_destino_insp" class="form-label">Período de Destino (Donde se acredita)</label>
                        <select class="form-select" id="periodo_destino_insp" name="periodo_destino_insp">
                            <option value="">Seleccione un período...</option>
                            <?php foreach ($periodos as $periodo): ?>
                                <option value="<?php echo htmlspecialchars($periodo['id']); ?>"><?php echo htmlspecialchars($periodo['fecha_inicio_periodo'] . ' al ' . $periodo['fecha_fin_periodo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Períodos para Administrativos (Quincenas) -->
                    <div class="col-md-6" id="periodos_administrativos" style="display: none;">
                         <label for="mes_admin" class="form-label">Mes y Año del Ajuste</label>
                         <input type="month" class="form-control" id="mes_admin" name="mes_admin">
                    </div>

                </div>
                <button type="submit" class="btn btn-primary mt-4">Guardar Ajuste</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const empleadoSelect = document.getElementById('id_empleado');
    const periodosInspectoresOrigen = document.getElementById('periodos_inspectores');
    const periodosInspectoresDestino = document.getElementById('periodos_destino_inspectores');
    const periodosAdministrativos = document.getElementById('periodos_administrativos');
    
    empleadoSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const tipoNomina = selectedOption.dataset.nomina;

        if (tipoNomina === 'Inspectores') {
            periodosInspectoresOrigen.style.display = 'block';
            periodosInspectoresDestino.style.display = 'block';
            document.getElementById('periodo_origen_insp').required = true;
            document.getElementById('periodo_destino_insp').required = true;

            periodosAdministrativos.style.display = 'none';
            document.getElementById('mes_admin').required = false;

        } else if (tipoNomina === 'Administrativa' || tipoNomina === 'Directiva') {
            periodosInspectoresOrigen.style.display = 'none';
            periodosInspectoresDestino.style.display = 'none';
            document.getElementById('periodo_origen_insp').required = false;
            document.getElementById('periodo_destino_insp').required = false;

            periodosAdministrativos.style.display = 'block';
            document.getElementById('mes_admin').required = true;
        } else {
            periodosInspectoresOrigen.style.display = 'none';
             periodosInspectoresDestino.style.display = 'none';
            periodosAdministrativos.style.display = 'none';
            document.getElementById('periodo_origen_insp').required = false;
            document.getElementById('periodo_destino_insp').required = false;
            document.getElementById('mes_admin').required = false;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
