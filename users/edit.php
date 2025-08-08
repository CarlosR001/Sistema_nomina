<?php
// users/edit.php
// Formulario para editar un usuario existente.

require_once '../auth.php';
require_login();
require_role('Admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?status=error&message=ID de usuario no válido.');
    exit();
}
$id_usuario = $_GET['id'];

// Cargar datos del usuario y empleado vinculado (tu consulta original)
$stmt = $pdo->prepare("SELECT u.*, e.nombres, e.primer_apellido FROM usuarios u JOIN empleados e ON u.id_empleado = e.id WHERE u.id = ?");
$stmt->execute([$id_usuario]);
$user = $stmt->fetch();
 
if (!$user) {
    header('Location: index.php?status=error&message=Usuario no encontrado.');
    exit();
}

// Cargar todos los roles disponibles del nuevo sistema
$roles_disponibles = $pdo->query("SELECT id, nombre_rol FROM roles ORDER BY nombre_rol")->fetchAll();

// Cargar los IDs de los roles que este usuario ya tiene asignados
$stmt_user_roles = $pdo->prepare("SELECT id_rol FROM usuario_rol WHERE id_usuario = ?");
$stmt_user_roles->execute([$id_usuario]);
$roles_actuales_ids = $stmt_user_roles->fetchAll(PDO::FETCH_COLUMN);


require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Usuario</h1>

<div class="card">
    <div class="card-header">
        Modificar cuenta de <?php echo htmlspecialchars($user['nombre_usuario']); ?>
    </div>
    <div class="card-body">
        <form action="update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
            
            <p><strong>Empleado Vinculado:</strong> <?php echo htmlspecialchars($user['nombres'] . ' ' . $user['primer_apellido']); ?></p>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
                    <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" value="<?php echo htmlspecialchars($user['nombre_usuario']); ?>" required>
                </div>

                 <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Roles Asignados</label>
                        <div class="border rounded p-3">
                            <?php foreach ($roles_disponibles as $rol): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="rol_<?php echo $rol['id']; ?>" name="roles[]" value="<?php echo $rol['id']; ?>"
                                        <?php echo in_array($rol['id'], $roles_actuales_ids) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="rol_<?php echo $rol['id']; ?>">
                                        <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>


                <div class="col-md-6">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado" required>
                        <option value="Activo" <?php echo ($user['estado'] == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                        <option value="Inactivo" <?php echo ($user['estado'] == 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>

                <div class="col-md-12">
                    <hr>
                    <label class="form-label">Cambiar Contraseña (opcional)</label>
                </div>

                <div class="col-md-6">
                    <input type="password" class="form-control" name="contrasena" placeholder="Nueva contraseña">
                </div>
                <div class="col-md-6">
                    <input type="password" class="form-control" name="confirmar_contrasena" placeholder="Confirmar nueva contraseña">
                </div>
            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
