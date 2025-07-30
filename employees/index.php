<?php
// employees/index.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role(['Admin', 'Contabilidad', 'Supervisor']); // Roles permitidos para ver empleados

// La conexión $pdo ya está disponible a través de auth.php

$sql = 'SELECT id, cedula, nombres, primer_apellido, email_personal FROM Empleados ORDER BY nombres ASC';
$stmt = $pdo->query($sql);

require_once '../includes/header.php'; // Muestra el header después de la verificación de rol
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
