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

$stmt = $pdo->prepare("SELECT u.*, e.nombres, e.primer_apellido FROM usuarios u JOIN empleados e ON u.id_empleado = e.id WHERE u.id = ?");
$stmt->execute([$id_usuario]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php?status=error&message=Usuario no encontrado.');
    exit();
}

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

                <div class="col-md-6">
                    <label for="rol" class="form-label">Rol del Usuario</label>
                    <select class="form-select" id="rol" name="rol" required>
                        <option value="Inspector" <?php echo ($user['rol'] == 'Inspector') ? 'selected' : ''; ?>>Inspector</option>
                        <option value="Supervisor" <?php echo ($user['rol'] == 'Supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                        <option value="Contabilidad" <?php echo ($user['rol'] == 'Contabilidad') ? 'selected' : ''; ?>>Contabilidad</option>
                        <option value="Admin" <?php echo ($user['rol'] == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
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
