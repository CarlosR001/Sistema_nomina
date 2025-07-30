<?php
// projects/index.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Admin'); // Solo Admin pueden gestionar proyectos

// La conexión $pdo ya está disponible a través de auth.php

$proyectos = $pdo->query("SELECT id, nombre_proyecto, codigo_proyecto, estado_proyecto FROM Proyectos ORDER BY nombre_proyecto")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Gestión de Proyectos</h1>

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
    <div class="card-header">Añadir Nuevo Proyecto</div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row">
                <div class="col-md-5">
                    <input type="text" class="form-control" name="nombre_proyecto" placeholder="Nombre del proyecto" required>
                </div>
                <div class="col-md-5">
                    <input type="text" class="form-control" name="codigo_proyecto" placeholder="Código del proyecto (opcional)">
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
            <th>Nombre del Proyecto</th>
            <th>Código</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($proyectos as $proyecto): ?>
        <tr>
            <td><?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?></td>
            <td><?php echo htmlspecialchars($proyecto['codigo_proyecto']); ?></td>
            <td>
                <span class="badge bg-<?php echo ($proyecto['estado_proyecto'] === 'Activo') ? 'success' : 'secondary'; ?>">
                    <?php echo htmlspecialchars($proyecto['estado_proyecto']); ?>
                </span>
            </td>
            <td>
                <a href="edit.php?id=<?php echo htmlspecialchars($proyecto['id']); ?>" class="btn btn-sm btn-warning">Editar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>
