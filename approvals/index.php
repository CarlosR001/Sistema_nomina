<?php
// approvals/index.php

require_once '../auth.php'; // Carga el sistema de autenticación
require_login(); // Asegura que el usuario esté logueado
require_role(['Admin', 'Supervisor']); // Solo Admin y Supervisores

// La conexión $pdo ya está disponible a través de auth.php

// Consulta para obtener todos los registros pendientes, agrupados por empleado
$sql = "SELECT r.id, r.fecha_trabajada, r.hora_inicio, r.hora_fin, e.nombres, e.primer_apellido, p.nombre_proyecto 
        FROM RegistroHoras r 
        JOIN Contratos c ON r.id_contrato = c.id 
        JOIN Empleados e ON c.id_empleado = e.id 
        JOIN Proyectos p ON r.id_proyecto = p.id 
        WHERE r.estado_registro = 'Pendiente' 
        ORDER BY e.nombres, r.fecha_trabajada";
$stmt = $pdo->query($sql);

require_once '../includes/header.php';
?>

<h1 class="mb-4">Aprobación de Horas Registradas</h1>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-success">Acción completada exitosamente.</div>
<?php endif; ?>

<?php if ($stmt->rowCount() > 0): ?>
    <form action="update_status.php" method="POST">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th><input class="form-check-input" type="checkbox" id="selectAll"></th>
                    <th>Empleado</th>
                    <th>Fecha</th>
                    <th>Horario</th>
                    <th>Proyecto</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $stmt->fetch()): ?>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" name="registros[]" value="<?php echo $row['id']; ?>"></td>
                        <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                        <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                        <td><?php echo htmlspecialchars($row['hora_inicio']) . " - " . htmlspecialchars($row['hora_fin']); ?></td>
                        <td><?php echo htmlspecialchars($row['nombre_proyecto']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <div class="d-flex justify-content-end">
            <button type="submit" name="action" value="Aprobado" class="btn btn-success me-2">Aprobar Seleccionados</button>
            <button type="submit" name="action" value="Rechazado" class="btn btn-danger">Rechazar Seleccionados</button>
        </div>
    </form>
    <script>
        document.getElementById('selectAll').addEventListener('click', function(event) {
            let checkboxes = document.querySelectorAll('input[name="registros[]"]');
            for (let checkbox of checkboxes) {
                checkbox.checked = event.target.checked;
            }
        });
    </script>
<?php else: ?>
    <div class="alert alert-info">No hay registros de horas pendientes de aprobación.</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
