<?php
// users/index.php
// Página principal para la gestión de usuarios del sistema.

require_once '../auth.php';
require_login();
require_role('Admin');

// Obtener todos los usuarios con la información del empleado asociado
$stmt = $pdo->query("
    SELECT 
        u.id, 
        u.nombre_usuario, 
        u.rol, 
        u.estado, 
        e.nombres, 
        e.primer_apellido 
    FROM usuarios u
    JOIN empleados e ON u.id_empleado = e.id
    ORDER BY u.nombre_usuario ASC
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
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Usuario</th>
                    <th>Empleado Vinculado</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="5" class="text-center">No hay usuarios registrados.</td></tr>
                <?php else: ?>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($user['nombre_usuario']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['nombres'] . ' ' . $user['primer_apellido']); ?></td>
                        <td><?php echo htmlspecialchars($user['rol']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user['estado'] === 'Activo' ? 'success' : 'secondary'; ?>">
                                <?php echo htmlspecialchars($user['estado']); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
