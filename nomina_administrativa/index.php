<?php
// nomina_administrativa/index.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

// Establecer la configuración regional a español para nombres de meses
setlocale(LC_TIME, 'es_ES.UTF-8', 'Spanish_Spain.1252');

require_once '../includes/header.php';
?>

<h1 class="mb-4">Nómina Administrativa y Directiva</h1>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5>Procesar Nómina Quincenal</h5>
    </div>
    <div class="card-body">
        <p class="card-text text-muted">
            Seleccione el período para procesar la nómina del personal con salario fijo. El sistema calculará el salario base y lo combinará con todas las novedades registradas (comisiones, horas extras, etc.) dentro del rango de fechas de la quincena.
        </p>
        <form action="procesar_nomina_admin.php" method="POST" onsubmit="return confirm('¿Está seguro de que desea procesar esta nómina? Esta acción generará los pagos y no se puede deshacer fácilmente.');">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="mes" class="form-label">Mes</label>
                    <select class="form-select" id="mes" name="mes" required>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == date('n')) ? 'selected' : ''; ?>>
                                <?php echo ucfirst(strftime('%B', mktime(0, 0, 0, $i, 1))); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="anio" class="form-label">Año</label>
                    <input type="number" class="form-control" id="anio" name="anio" value="<?php echo date('Y'); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="quincena" class="form-label">Quincena</label>
                    <select class="form-select" id="quincena" name="quincena" required>
                        <option value="1">Primera Quincena</option>
                        <option value="2">Segunda Quincena</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Procesar Nómina</button>
        </form>
    </div>
</div>

<?php require_once '../includes/header.php'; ?>
