<?php
// reporting_periods/index.php

require_once '../config/init.php';
require_once '../includes/header.php';

$periodos = $pdo->query("SELECT id, fecha_inicio_periodo, fecha_fin_periodo, tipo_nomina, estado_periodo FROM PeriodosDeReporte ORDER BY fecha_inicio_periodo DESC")->fetchAll();
?>

<h1 class="mb-4">Gestión de Períodos de Reporte</h1>

<div class="card mb-4">
    <div class="card-header">Abrir Nuevo Período de Reporte</div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                </div>
                <div class="col-md-4">
                    <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                </div>
                <div class="col-md-2">
                    <label for="tipo_nomina" class="form-label">Para Nómina</label>
                    <select class="form-select" id="tipo_nomina" name="tipo_nomina">
                        <option value="Inspectores">Inspectores</option>
                        <option value="Administrativa">Administrativa</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-success w-100" type="submit">Abrir Período</button>
                </div>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped">
    <thead class="table-dark">
        <tr>
            <th>Período</th>
            <th>Nómina</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($periodos as $periodo): ?>
        <tr>
            <td><?php echo htmlspecialchars($periodo['fecha_inicio_periodo']) . " al " . htmlspecialchars($periodo['fecha_fin_periodo']); ?></td>
            <td><?php echo htmlspecialchars($periodo['tipo_nomina']); ?></td>
            <td>
                <span class="badge bg-<?php echo $periodo['estado_periodo'] == 'Abierto' ? 'success' : 'secondary'; ?>">
                    <?php echo htmlspecialchars($periodo['estado_periodo']); ?>
                </span>
            </td>
            <td>
                </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>