<?php
// departments/index.php
require_once '../auth.php'; // Carga el sistema de autenticación
require_login(); // Asegura que el usuario esté logueado
require_role('Admin'); // Solo Admin pueden acceder a esta sección

// La conexión $pdo ya está disponible a través de auth.php

require_once '../includes/header.php';

// Obtener todos los departamentos para la tabla
$stmt = $pdo->query("SELECT id, nombre_departamento, estado FROM Departamentos ORDER BY nombre_departamento");
$departamentos = $stmt->fetchAll();
?>

<h1 class="mb-4">Gestión de Departamentos</h1>

<?php
// Manejo de mensajes de estado (éxito o error)
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        echo '<div class="alert alert-success">Departamento guardado correctamente.</div>';
    } elseif ($_GET['status'] === 'error') {
        $message = 'Ocurrió un error.';
        if (isset($_GET['message'])) {
            if ($_GET['message'] === 'duplicate') {
                $message = 'Error: Ya existe un departamento con ese nombre.';
            } else {
                // Por seguridad, no mostramos el mensaje de la BBDD directamente al usuario.
                $message = 'Error al procesar la solicitud.';
            }
        }
        echo '<div class="alert alert-danger">' . htmlspecialchars($message) . '</div>';
    }
}
?>

<div class="card mb-4">
    <div class="card-header">
        Añadir Nuevo Departamento
    </div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="input-group">
                <input type="text" class="form-control" name="nombre_departamento" placeholder="Nombre del departamento" required>
                <button class="btn btn-success" type="submit">Guardar</button>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nombre del Departamento</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($departamentos as $departamento): ?>
        <tr>
            <td><?php echo htmlspecialchars($departamento['id']); ?></td>
            <td><?php echo htmlspecialchars($departamento['nombre_departamento']); ?></td>
            <td>
                <span class="badge bg-<?php echo $departamento['estado'] === 'Activo' ? 'success' : 'secondary'; ?>">
                    <?php echo htmlspecialchars($departamento['estado']); ?>
                </span>
            </td>
            <td>
                <a href="edit.php?id=<?php echo htmlspecialchars($departamento['id']); ?>" class="btn btn-sm btn-warning">Editar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
require_once '../includes/footer.php';
?>