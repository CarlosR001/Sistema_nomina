<?php
// novedades/horas_extras_admin.php

require_once '../auth.php';
require_login();
require_role('Admin');

// Cargar empleados con contrato administrativo/directivo
$empleados = $pdo->query("
    SELECT c.id as id_contrato, e.nombres, e.primer_apellido 
    FROM Contratos c 
    JOIN Empleados e ON c.id_empleado = e.id 
    WHERE c.tipo_nomina IN ('Administrativa', 'Directiva') AND c.estado_contrato = 'Vigente'
    ORDER BY e.nombres
")->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<h1 class="mb-4">Registro de Horas Extras (Personal Fijo)</h1>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Añadir Horas Extras</div>
    <div class="card-body">
        <form action="store_horas_extras_admin.php" method="POST">
            <div class="row g-3">
                <div class="col-md-5">
                    <label for="id_contrato" class="form-label">Empleado</label>
                    <select class="form-select" id="id_contrato" name="id_contrato" required>
                        <option value="">Seleccione un empleado...</option>
                        <?php foreach ($empleados as $empleado): ?>
                            <option value="<?php echo $empleado['id_contrato']; ?>">
                                <?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="fecha_novedad" class="form-label">Fecha en que se Trabajaron</label>
                    <input type="date" class="form-control" id="fecha_novedad" name="fecha_novedad" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="cantidad_horas" class="form-label">Cantidad de Horas</label>
                    <input type="number" step="0.01" class="form-control" id="cantidad_horas" name="cantidad_horas" placeholder="Ej: 4.5" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Calcular y Guardar Horas Extras</button>
        </form>
    </div>
</div>

<?php require_once '../includes/header.php'; ?>
