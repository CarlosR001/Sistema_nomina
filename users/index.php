<?php
// users/index.php
// Página principal para la gestión de usuarios del sistema.

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar');

// Obtener todos los usuarios con la información del empleado asociado
$stmt = $pdo->query("
    SELECT 
        u.id,
        u.nombre_usuario,
        u.estado,
        e.nombres,
        e.primer_apellido,
        GROUP_CONCAT(r.nombre_rol SEPARATOR ', ') AS roles
    FROM usuarios u
    JOIN empleados e ON u.id_empleado = e.id
    LEFT JOIN usuario_rol ur ON u.id = ur.id_usuario
    LEFT JOIN roles r ON ur.id_rol = r.id
    GROUP BY u.id
    ORDER BY u.nombre_usuario
");
$users = $stmt->fetchAll();


require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Gestión de Usuarios</h1>
    <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Añadir Nuevo Usuario</a>
</div>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <!-- AÑADIDO: Formulario para acciones en lote -->
        <form action="batch_update.php" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas aplicar esta acción a los usuarios seleccionados?');">
            
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <!-- AÑADIDO: Checkbox para seleccionar todos -->
                        <th style="width: 1%;"><input type="checkbox" id="selectAll"></th>
                        <th>Usuario</th>
                        <th>Empleado Vinculado</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="text-center">No hay usuarios registrados.</td></tr>
                    <?php else: ?>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <!-- AÑADIDO: Checkbox para cada usuario -->
                            <td><input type="checkbox" name="user_ids[]" class="user-checkbox" value="<?php echo $user['id']; ?>"></td>
                            <td><strong><?php echo htmlspecialchars($user['nombre_usuario']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['nombres'] . ' ' . $user['primer_apellido']); ?></td>
                            <td><?php echo htmlspecialchars($user['roles'] ?? 'Sin rol asignado'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['estado'] === 'Activo' ? 'success' : 'secondary'; ?>">
                                    <?php echo htmlspecialchars($user['estado']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este usuario? Esta acción es irreversible.');">
                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- AÑADIDO: Menú de acciones en lote -->
            <div class="row mt-3">
                <div class="col-md-4">
                    <select name="action" class="form-select" required>
                        <option value="">-- Seleccione una acción en lote --</option>
                        <option value="force_password_change">Forzar cambio de contraseña</option>
                        <option value="activate">Activar</option>
                        <option value="deactivate">Desactivar</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Aplicar a Selección</button>
                </div>
            </div>

        </form> <!-- AÑADIDO: Cierre del formulario -->
    </div>
</div>

<!-- AÑADIDO: Script para el checkbox "Seleccionar Todos" -->
<script>
document.getElementById('selectAll').addEventListener('click', function(event) {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = event.target.checked;
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
