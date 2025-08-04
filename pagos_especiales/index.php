<?php
// pagos_especiales/index.php

require_once '../auth.php';
require_login();
require_role('Admin');

// Cargar empleados activos y conceptos de ingreso manuales
$empleados = $pdo->query("SELECT e.id, e.nombres, e.primer_apellido, e.cedula FROM Empleados e JOIN Contratos c ON e.id = c.id_empleado WHERE c.estado_contrato = 'Vigente' ORDER BY e.nombres")->fetchAll(PDO::FETCH_ASSOC);
$conceptos = $pdo->query("SELECT id, descripcion_publica, codigo_concepto FROM ConceptosNomina WHERE tipo_concepto = 'Ingreso' AND codigo_concepto LIKE 'ING-%' ORDER BY descripcion_publica")->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<h1 class="mb-4">Procesar Pago Especial</h1>

<div class="card">
    <div class="card-header">
        <h5>Crear Nuevo Pago Fuera de Ciclo (Vacaciones, Bonos, etc.)</h5>
    </div>
    <div class="card-body">
        <p class="card-text text-muted">
            Esta herramienta procesa un pago individual, calcula y retiene el ISR correspondiente en el momento, y lo registra para que sea considerado en el cierre de mes.
        </p>
        <form action="procesar_pago.php" method="POST" onsubmit="return confirm('¿Está seguro de que desea procesar este pago especial? Esta acción es irreversible.');">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="id_empleado" class="form-label">Empleado</label>
                    <select class="form-select" id="id_empleado" name="id_empleado" required>
                        <option value="">Seleccione un empleado...</option>
                        <?php foreach ($empleados as $empleado): ?>
                            <option value="<?php echo $empleado['id']; ?>"><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="id_concepto" class="form-label">Concepto del Pago</label>
                    <select class="form-select" id="id_concepto" name="id_concepto" required>
                        <option value="">Seleccione un concepto...</option>
                        <?php foreach ($conceptos as $concepto): ?>
                            <option value="<?php echo $concepto['id']; ?>"><?php echo htmlspecialchars($concepto['descripcion_publica']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="monto_pago" class="form-label">Monto Bruto del Pago</label>
                    <input type="number" step="0.01" class="form-control" id="monto_pago" name="monto_pago" required>
                </div>
                <div class="col-md-6">
                    <label for="fecha_pago" class="form-label">Fecha Efectiva del Pago</label>
                    <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Procesar Pago Especial</button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
