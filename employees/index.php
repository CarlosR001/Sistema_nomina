<?php
// employees/index.php (CORREGIDO)
require_once '../config/init.php';

// Lógica de seguridad: verificar sesión y rol (ej. 'Administrador' o 'RRHH')
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'Administrador') {
    // Si no es el rol correcto, puedes redirigir o mostrar un error.
    // Por simplicidad, aquí solo detenemos la ejecución.
    die('Acceso denegado. No tienes permiso para ver esta página.');
}

require_once '../includes/header.php';

// Consulta para obtener todos los empleados
$stmt = $pdo->query("SELECT id, cedula, nombres, primer_apellido, email_personal, estado_empleado FROM Empleados ORDER BY nombres, primer_apellido");
$empleados = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Gestión de Empleados</h1>
    <a href="create.php" class="btn btn-success">Añadir Nuevo Empleado</a>
</div>

<?php
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    echo '<div class="alert alert-success">Empleado guardado correctamente.</div>';
}
?>

<table class="table table-hover table-striped">
    <thead class="table-dark">
        <tr>
            <th>Nombre Completo</th>
            <th>Cédula</th>
            <th>Email</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($empleados): ?>
            <?php foreach ($empleados as $empleado): ?>
            <tr>
                <td><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></td>
                <td><?php echo htmlspecialchars($empleado['cedula']); ?></td>
                <td><?php echo htmlspecialchars($empleado['email_personal']); ?></td>
                <td>
                    <span class="badge bg-<?php echo $empleado['estado_empleado'] == 'Activo' ? 'success' : 'secondary'; ?>">
                        <?php echo htmlspecialchars($empleado['estado_empleado']); ?>
                    </span>
                </td>
                <td>
                    <a href="../contracts/index.php?employee_id=<?php echo $empleado['id']; ?>" class="btn btn-sm btn-info">Ver Contratos</a>
                    <a href="edit.php?id=<?php echo $empleado['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="text-center">No hay empleados registrados.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>