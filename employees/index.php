<?php
// employees/index.php
// v1.1 - Corrige el nombre de la tabla de 'Empleados' a 'empleados'.

require_once '../auth.php';
require_login();
require_permission('empleados.gestionar');

// CORRECCIÓN: El nombre de la tabla es 'empleados', no 'Empleados'.
$sql = 'SELECT id, cedula, nombres, primer_apellido, email_personal FROM empleados ORDER BY nombres ASC';
$stmt = $pdo->query($sql);

require_once '../includes/header.php';
?>

<h1 class="mb-4">Gestión de Empleados</h1>
<a href="create.php" class="btn btn-primary mb-3">Añadir Nuevo Empleado</a>

<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>Cédula</th>
            <th>Nombres</th>
            <th>Apellido</th>
            <th>Email</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $stmt->fetch()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['cedula'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['nombres'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['primer_apellido'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['email_personal'] ?? ''); ?></td>
                <td class="text-center">
                          <a href="<?php echo BASE_URL; ?>employees/edit.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-warning btn-sm" title="Editar Empleado"><i class="bi bi-pencil-square"></i></a>
                          <a href="<?php echo BASE_URL; ?>contracts/index.php?employee_id=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-info btn-sm" title="Ver Contratos"><i class="bi bi-file-text"></i></a>
                          <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('ADVERTENCIA:\n\n¿Estás seguro de que deseas eliminar este empleado?\n\nEsta acción es irreversible y solo debe realizarse si el empleado fue creado por error y no tiene contratos ni usuarios asociados.');">
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
                              <button type="submit" class="btn btn-danger btn-sm" title="Eliminar Empleado">
                                  <i class="bi bi-trash"></i>
                              </button>
                          </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>
