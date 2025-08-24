<?php
// novedades/saldo_a_favor.php

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// Obtener empleados administrativos y directivos activos para el selector
$stmt_empleados = $pdo->query("
    SELECT e.id, e.nombres, e.primer_apellido, e.cedula
    FROM empleados e
    JOIN contratos c ON e.id = c.id_empleado
    WHERE c.tipo_nomina IN ('Administrativa', 'Directiva') AND c.estado_contrato = 'Vigente'
    ORDER BY e.nombres, e.primer_apellido
");
$empleados = $stmt_empleados->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Registrar Saldo a Favor de ISR</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Novedades</a></li>
        <li class="breadcrumb-item active">Saldo a Favor</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-money-bill-wave me-1"></i>
            Datos del Saldo a Favor
        </div>
        <div class="card-body">
            <form action="guardar_saldo_a_favor.php" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="id_empleado" class="form-label">Empleado</label>
                        <select class="form-select" id="id_empleado" name="id_empleado" required>
                            <option value="">-- Seleccione un Empleado --</option>
                            <?php foreach ($empleados as $empleado): ?>
                                <option value="<?php echo $empleado['id']; ?>">
                                    <?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido'] . ' (' . $empleado['cedula'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="monto" class="form-label">Monto del Saldo a Favor</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="monto" name="monto" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="periodo_aplicacion" class="form-label">Aplicar en la Nómina de</label>
                        <input type="date" class="form-control" id="periodo_aplicacion" name="periodo_aplicacion" required>
                        <div class="form-text">Seleccione cualquier día dentro de la quincena que desea aplicar el saldo.</div>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Guardar Saldo a Favor</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
