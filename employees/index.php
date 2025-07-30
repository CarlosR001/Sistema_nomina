<?php
// employees/index.php
require_once '../config/init.php'; // Carga la sesión, DB y auth
require_once '../includes/header.php'; // Muestra el header y protege la página

// Opcional: Restringir acceso por rol si es necesario
// require_role('Admin'); 

$sql = 'SELECT id, cedula, nombres, primer_apellido, email_personal FROM Empleados ORDER BY nombres ASC';
$stmt = $pdo->query($sql);
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
                    <a href="../contracts/index.php?employee_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Ver Contratos</a>
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>
