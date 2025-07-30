<?php
// zones/index.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Admin'); // Solo Admin pueden gestionar zonas de transporte

// La conexión $pdo ya está disponible a través de auth.php

$zonas = $pdo->query("SELECT id, nombre_zona_o_muelle, monto_transporte_completo FROM ZonasTransporte ORDER BY nombre_zona_o_muelle")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Gestión de Zonas de Transporte</h1>

<?php
// Manejo de mensajes de estado (éxito o error)
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        echo '<div class="alert alert-success">Operación realizada exitosamente.</div>';
    } elseif (isset($_GET['message'])) {
        echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($_GET['message']) . '</div>';
    }
}
?>

<div class="card mb-4">
    <div class="card-header">Añadir Nueva Zona / Muelle</div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label for="nombre_zona" class="form-label">Nombre de la Zona o Muelle</label>
                    <input type="text" class="form-control" id="nombre_zona" name="nombre_zona" required>
                </div>
                <div class="col-md-4">
                    <label for="monto" class="form-label">Monto Transporte Completo</label>
                    <input type="number" step="0.01" class="form-control" id="monto" name="monto" required>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-success w-100" type="submit">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped">
    <thead class="table-dark">
        <tr>
            <th>Nombre</th>
            <th>Monto Transporte</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($zonas as $zona): ?>
        <tr>
            <td><?php echo htmlspecialchars($zona['id']); ?></td>
            <td><?php echo htmlspecialchars($zona['nombre_zona_o_muelle']); ?></td>
            <td>$<?php echo number_format($zona['monto_transporte_completo'], 2); ?></td>
            <td>
                <a href="edit.php?id=<?php echo htmlspecialchars($zona['id']); ?>" class="btn btn-sm btn-warning">Editar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>