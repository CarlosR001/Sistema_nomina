<?php
// approvals/index.php
require_once '../config/init.php';

// --- Verificación de Seguridad y Rol ---
if (!isset($_SESSION['usuario_id'])) {
    // Si no hay sesión, se va al login.
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}
// Solo los Administradores o Supervisores pueden aprobar horas.
// Puedes ajustar los roles según tus necesidades.
if (!in_array($_SESSION['rol'], ['Administrador', 'Supervisor'])) {
    // Si no tiene el rol correcto, muestra un error.
    die('Acceso Denegado. No tienes los permisos necesarios para acceder a esta página.');
}
// --- Fin de la verificación ---

require_once '../includes/header.php';

// Consulta para obtener todos los registros pendientes, agrupados por empleado
$sql = "SELECT
            r.id,
            r.fecha_trabajada,
            r.hora_inicio,
            r.hora_fin,
            e.nombres,
            e.primer_apellido,
            p.nombre_proyecto
        FROM RegistroHoras r
        JOIN Contratos c ON r.id_contrato = c.id
        JOIN Empleados e ON c.id_empleado = e.id
        JOIN Proyectos p ON r.id_proyecto = p.id
        WHERE r.estado_registro = 'Pendiente'
        ORDER BY e.nombres, r.fecha_trabajada";

$stmt = $pdo->query($sql);
?>

<h1 class="mb-4">Aprobación de Horas Registradas</h1>

<?php
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        echo '<div class="alert alert-success">La acción se completó correctamente.</div>';
    } elseif ($_GET['status'] === 'error') {
        echo '<div class="alert alert-danger">Ocurrió un error: ' . htmlspecialchars($_GET['message']) . '</div>';
    }
}
?>

<?php if ($stmt->rowCount() > 0): ?>
<form action="update_status.php" method="POST">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>
                    <input class="form-check-input" type="checkbox" id="selectAll">
                </th>
                <th>Empleado</th>
                <th>Fecha</th>
                <th>Horario</th>
                <th>Proyecto</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $stmt->fetch()): ?>
            <tr>
                <td>
                    <input class="form-check-input" type="checkbox" name="registros[]" value="<?php echo $row['id']; ?>">
                </td>
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
// Script simple para seleccionar/deseleccionar todos los checkboxes
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