<?php
// users/create.php - v2.0 (Corregido y Dinámico)

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar');

// Obtener solo los empleados que aún NO tienen una cuenta de usuario
$stmt_empleados = $pdo->query("
    SELECT e.id, e.nombres, e.primer_apellido
    FROM empleados e
    LEFT JOIN usuarios u ON e.id = u.id_empleado
    WHERE u.id IS NULL AND e.estado_empleado = 'Activo'
    ORDER BY e.nombres ASC
");
$empleados_sin_usuario = $stmt_empleados->fetchAll();

// --- CORRECCIÓN 1: Cargar roles desde la base de datos ---
$stmt_roles = $pdo->query("SELECT id, nombre_rol FROM roles ORDER BY nombre_rol");
$roles_disponibles = $stmt_roles->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Crear Nuevo Usuario</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Usuarios</a></li>
        <li class="breadcrumb-item active">Crear Usuario</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-user-plus me-1"></i>Detalles de la Cuenta</div>
        <div class="card-body">
            <form action="store.php" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="id_empleado" class="form-label">Empleado</label>
                        <select class="form-select" id="id_empleado" name="id_empleado" required>
                            <option value="">Selecciona un empleado...</option>
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
                        <label for="roles" class="form-label">Rol(es) del Usuario</label>
                        <!-- --- CORRECCIÓN 2: `name` es "roles[]" y el `value` es el ID del rol --- -->
                        <select class="form-select" id="roles" name="roles[]" required>
                            <option value="">Seleccionar rol...</option>
                            <?php foreach ($roles_disponibles as $rol): ?>
                                <option value="<?php echo $rol['id']; ?>">
                                    <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Para seleccionar múltiples roles, mantén presionada la tecla Ctrl (o Cmd en Mac).</small>
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

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Guardar Usuario</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
