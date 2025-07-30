<?php
// calendario/index.php

require_once '../auth.php';
require_login();
require_role('Admin');

$feriados = $pdo->query("SELECT * FROM CalendarioLaboralRD ORDER BY fecha DESC")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Gestión de Calendario Laboral (Días Feriados)</h1>

<div class="card mb-4">
    <div class="card-header">Añadir Nuevo Día Feriado</div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label for="fecha" class="form-label">Fecha</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" required>
                </div>
                <div class="col-md-6">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <input type="text" class="form-control" id="descripcion" name="descripcion" placeholder="Ej: Día de la Independencia" required>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-success w-100" type="submit">Guardar Feriado</button>
                </div>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped">
    <thead class="table-dark">
        <tr>
            <th>Fecha</th>
            <th>Descripción</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($feriados as $feriado): ?>
        <tr>
            <td><?php echo htmlspecialchars($feriado['fecha']); ?></td>
            <td><?php echo htmlspecialchars($feriado['descripcion']); ?></td>
            <td>
                <!-- Futuras acciones como editar o eliminar -->
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>
