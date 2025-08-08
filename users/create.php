<?php
// users/create.php
// Formulario para crear un nuevo usuario.

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar');

// Obtener solo los empleados que aún NO tienen una cuenta de usuario
$stmt = $pdo->query("
    SELECT e.id, e.nombres, e.primer_apellido
    FROM empleados e
    LEFT JOIN usuarios u ON e.id = u.id_empleado
    WHERE u.id IS NULL AND e.estado_empleado = 'Activo'
    ORDER BY e.nombres ASC
");
$empleados_sin_usuario = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Crear Nuevo Usuario</h1>

<div class="card">
    <div class="card-header">
        Detalles de la cuenta de usuario
    </div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row g-3">
                <div class="col-md-12">
                    <label for="id_empleado" class="form-label">Empleado</label>
                    <select class="form-select" id="id_empleado" name="id_empleado" required>
                        <option value="">Selecciona un empleado sin usuario...</option>
                        <?php foreach ($empleados_sin_usuario as $empleado): ?>
                            <option value="<?php echo $empleado['id']; ?>">
                                <?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
                    <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" required>
                </div>

                <div class="col-md-6">
                    <label for="rol" class="form-label">Rol del Usuario</label>
                    <select class="form-select" id="rol" name="rol" required>
                        <option value="Inspector">Inspector</option>
                        <option value="Supervisor">Supervisor</option>
                        <option value="Contabilidad">Contabilidad</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="contrasena" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="contrasena" name="contrasena" required>
                </div>

                <div class="col-md-6">
                    <label for="confirmar_contrasena" class="form-label">Confirmar Contraseña</label>
                    <input type="password" class="form-control" id="confirmar_contrasena" name="confirmar_contrasena" required>
                </div>
            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary">Guardar Usuario</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
