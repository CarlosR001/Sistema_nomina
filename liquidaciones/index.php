<?php
// liquidaciones/index.php - v1.0 (Interfaz de Cálculo de Liquidación)

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

// Obtener solo empleados con contratos vigentes
$empleados = $pdo->query("
    SELECT e.id, e.nombres, e.primer_apellido, e.cedula 
    FROM Empleados e 
    JOIN Contratos c ON e.id = c.id_empleado 
    WHERE c.estado_contrato = 'Vigente' 
    ORDER BY e.nombres, e.primer_apellido
")->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<h1 class="mb-4">Cálculo de Prestaciones Laborales (Liquidación)</h1>

<div class="card">
    <div class="card-header">
        <h5>Paso 1: Ingresar Datos de Salida del Empleado</h5>
    </div>
    <div class="card-body">
        <form action="preview_liquidacion.php" method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="id_empleado" class="form-label">Empleado</label>
                    <select name="id_empleado" id="id_empleado" class="form-select" required>
                        <option value="">Seleccione un empleado...</option>
                        <?php foreach ($empleados as $empleado): ?>
                            <option value="<?php echo $empleado['id']; ?>">
                                <?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido'] . ' (' . $empleado['cedula'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="fecha_salida" class="form-label">Fecha de Salida</label>
                    <input type="date" name="fecha_salida" id="fecha_salida" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-12">
                    <label for="motivo_salida" class="form-label">Motivo de la Salida</label>
                    <select name="motivo_salida" id="motivo_salida" class="form-select" required>
                        <option value="desahucio">Desahucio (Ejercido por el Empleador)</option>
                        <option value="renuncia">Renuncia (Del Empleado)</option>
                        <option value="despido_justificado">Despido Justificado</option>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-calculator"></i> Calcular Liquidación
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
