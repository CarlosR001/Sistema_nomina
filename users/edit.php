<?php
// users/edit.php
// Formulario para editar un usuario existente, con gestión de permisos explícitos.

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?status=error&message=ID de usuario no válido.');
    exit();
}
$id_usuario = $_GET['id'];

// Cargar datos del usuario y empleado vinculado
$stmt = $pdo->prepare("SELECT u.*, e.nombres, e.primer_apellido FROM usuarios u JOIN empleados e ON u.id_empleado = e.id WHERE u.id = ?");
$stmt->execute([$id_usuario]);
$user = $stmt->fetch();
 
if (!$user) {
    header('Location: index.php?status=error&message=Usuario no encontrado.');
    exit();
}

// Cargar todos los roles disponibles
$roles_disponibles = $pdo->query("SELECT id, nombre_rol FROM roles ORDER BY nombre_rol")->fetchAll();

// Cargar todos los permisos disponibles, agrupados por recurso para mejor visualización
$permisos_disponibles = $pdo->query("
    SELECT 
        id, 
        clave_permiso, 
        descripcion,
        SUBSTRING_INDEX(clave_permiso, '.', 1) as recurso
    FROM permisos 
    WHERE clave_permiso != '*'
    ORDER BY recurso, clave_permiso
")->fetchAll();

$permisos_agrupados = [];
foreach ($permisos_disponibles as $permiso) {
    $permisos_agrupados[$permiso['recurso']][] = $permiso;
}


// Cargar los IDs de los roles que este usuario ya tiene asignados
$stmt_user_roles = $pdo->prepare("SELECT id_rol FROM usuario_rol WHERE id_usuario = ?");
$stmt_user_roles->execute([$id_usuario]);
$roles_actuales_ids = $stmt_user_roles->fetchAll(PDO::FETCH_COLUMN);

// Cargar los permisos explícitos (anulaciones) de este usuario
$stmt_explicit_perms = $pdo->prepare("SELECT id_permiso, tiene_permiso FROM permisos_usuario WHERE id_usuario = ?");
$stmt_explicit_perms->execute([$id_usuario]);
$permisos_explicitos = $stmt_explicit_perms->fetchAll(PDO::FETCH_KEY_PAIR);


require_once '../includes/header.php';
?>

<style>
    .permisos-table th:nth-child(2),
    .permisos-table th:nth-child(3),
    .permisos-table th:nth-child(4) {
        text-align: center;
        width: 120px;
    }
    .permisos-table td {
        vertical-align: middle;
    }
    .bg-permitir-light { background-color: rgba(25, 135, 84, 0.1); }
    .bg-denegar-light { background-color: rgba(220, 53, 69, 0.1); }
    .recurso-header {
        background-color: #f8f9fa;
        font-weight: bold;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
</style>

<h1 class="mb-4">Editar Usuario</h1>

<div class="card shadow-sm">
    <div class="card-header">
        Modificar cuenta de <strong><?php echo htmlspecialchars($user['nombre_usuario']); ?></strong>
    </div>
    <div class="card-body">
        <form action="update.php" method="POST" id="editUserForm">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
            
            <p><strong>Empleado Vinculado:</strong> <?php echo htmlspecialchars($user['nombres'] . ' ' . $user['primer_apellido']); ?></p>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
                    <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" value="<?php echo htmlspecialchars($user['nombre_usuario']); ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado" required>
                        <option value="Activo" <?php echo ($user['estado'] == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                        <option value="Inactivo" <?php echo ($user['estado'] == 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>

                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Roles Asignados</label>
                    <div class="border rounded p-3 bg-light">
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

                <div class="col-12">
                     <hr>
                    <label class="form-label fw-bold">Permisos Específicos (Anulaciones)</label>
                    <p class="text-muted small">Permite conceder o revocar permisos individuales, ignorando lo que dictan los roles. El valor por defecto es heredar la configuración del rol.</p>
                     <div class="table-responsive border rounded">
                        <table class="table table-striped table-hover mb-0 permisos-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Permiso</th>
                                    <th>Heredar de Rol</th>
                                    <th>Permitir</th>
                                    <th>Denegar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($permisos_agrupados as $recurso => $permisos): ?>
                                    <tr class="recurso-header">
                                        <td colspan="4" class="text-uppercase small"><?php echo htmlspecialchars($recurso); ?></td>
                                    </tr>
                                    <?php foreach ($permisos as $permiso): 
                                        $permiso_id = $permiso['id'];
                                        // Determina el estado actual del permiso para este usuario específico
                                        $valor_actual = array_key_exists($permiso_id, $permisos_explicitos) ? (int)$permisos_explicitos[$permiso_id] : -1;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($permiso['clave_permiso']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($permiso['descripcion']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <input class="form-check-input" type="radio" name="permisos[<?php echo $permiso_id; ?>]" value="-1" <?php echo $valor_actual === -1 ? 'checked' : ''; ?>>
                                        </td>
                                        <td class="text-center bg-permitir-light">
                                            <input class="form-check-input" type="radio" name="permisos[<?php echo $permiso_id; ?>]" value="1" <?php echo $valor_actual === 1 ? 'checked' : ''; ?>>
                                        </td>
                                        <td class="text-center bg-denegar-light">
                                            <input class="form-check-input" type="radio" name="permisos[<?php echo $permiso_id; ?>]" value="0" <?php echo $valor_actual === 0 ? 'checked' : ''; ?>>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-md-12 mt-4">
                    <hr>
                    <label class="form-label fw-bold">Cambiar Contraseña (opcional)</label>
                </div>

                <div class="col-md-6">
                    <input type="password" class="form-control" id="contrasena" name="contrasena" placeholder="Nueva contraseña">
                </div>
                <div class="col-md-6">
                    <input type="password" class="form-control" id="confirmar_contrasena" name="confirmar_contrasena" placeholder="Confirmar nueva contraseña">
                </div>
            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<script>
document.getElementById('editUserForm').addEventListener('submit', function(event) {
    const password = document.getElementById('contrasena').value;
    const confirmPassword = document.getElementById('confirmar_contrasena').value;

    if (password !== '' && password !== confirmPassword) {
        alert('Las contraseñas no coinciden.');
        event.preventDefault(); // Evita que el formulario se envíe
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
