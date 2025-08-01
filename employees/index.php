<?php
// employees/index.php
// v1.1 - Corrige el nombre de la tabla de 'Empleados' a 'empleados'.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad', 'Supervisor']);

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
                <td><?php echo htmlspecialchars($row['cedula']); ?></td>
                <td><?php echo htmlspecialchars($row['nombres']); ?></td>
                <td><?php echo htmlspecialchars($row['primer_apellido']); ?></td>
                <td><?php echo htmlspecialchars($row['email_personal']); ?></td>
                <td>
                    <a href="<?php echo BASE_URL; ?>contracts/index.php?employee_id=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-sm btn-info">Ver Contratos</a>
                    <a href="<?php echo BASE_URL; ?>employees/edit.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-sm btn-warning">Editar</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>
