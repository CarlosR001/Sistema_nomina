<?php
// positions/index.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Administrador'); // Solo Administradores pueden acceder a esta sección

// La conexión $pdo ya está disponible a través de auth.php

// Obtener todas las posiciones
$stmt_pos = $pdo->query("SELECT p.id, p.nombre_posicion, d.nombre_departamento 
                         FROM Posiciones p 
                         JOIN Departamentos d ON p.id_departamento = d.id 
                         ORDER BY p.nombre_posicion");
$posiciones = $stmt_pos->fetchAll();

// Obtener todos los departamentos para el dropdown
$stmt_dep = $pdo->query("SELECT id, nombre_departamento FROM Departamentos WHERE estado = 'Activo' ORDER BY nombre_departamento");
$departamentos = $stmt_dep->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Gestión de Posiciones</h1>

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
    <div class="card-header">
        Añadir Nueva Posición
    </div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control" name="nombre_posicion" placeholder="Nombre de la posición" required>
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="id_departamento" required>
                        <option value="">Asignar a departamento...</option>
                        <?php foreach ($departamentos as $departamento): ?>
                            <option value="<?php echo htmlspecialchars($departamento['id']); ?>"><?php echo htmlspecialchars($departamento['nombre_departamento']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-success w-100" type="submit">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nombre de la Posición</th>
            <th>Departamento</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($posiciones as $posicion): ?>
        <tr>
            <td><?php echo htmlspecialchars($posicion['id']); ?></td>
            <td><?php echo htmlspecialchars($posicion['nombre_posicion']); ?></td>
            <td><?php echo htmlspecialchars($posicion['nombre_departamento']); ?></td>
            <td>
                <a href="edit.php?id=<?php echo htmlspecialchars($posicion['id']); ?>" class="btn btn-sm btn-warning">Editar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
require_once '../includes/footer.php';
?>