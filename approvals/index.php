<?php
// approvals/index.php - v2.0
// Añade pestañas para ver registros Pendientes y Aprobados, y permite revertir estos últimos.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

// Determinar la pestaña activa
$view = $_GET['view'] ?? 'pendientes'; // 'pendientes' por defecto

// Obtener datos para los dropdowns del modal (se usan en ambas vistas)
$proyectos = $pdo->query("SELECT id, nombre_proyecto FROM Proyectos WHERE estado_proyecto = 'Activo'")->fetchAll();
$zonas = $pdo->query("SELECT id, nombre_zona_o_muelle FROM ZonasTransporte")->fetchAll();

// Consulta principal adaptada a la vista
$estado_a_buscar = ($view === 'aprobados') ? 'Aprobado' : 'Pendiente';
$sql = "SELECT r.id, r.fecha_trabajada, r.hora_inicio, r.hora_fin, e.nombres, e.primer_apellido, p.nombre_proyecto, u_aprob.nombre_usuario as aprobador, r.fecha_aprobacion
        FROM RegistroHoras r 
        JOIN Contratos c ON r.id_contrato = c.id 
        JOIN Empleados e ON c.id_empleado = e.id 
        JOIN Proyectos p ON r.id_proyecto = p.id 
        LEFT JOIN Usuarios u_aprob ON r.id_usuario_aprobador = u_aprob.id
        WHERE r.estado_registro = ?
        ORDER BY e.nombres, r.fecha_trabajada";
$stmt = $pdo->prepare($sql);
$stmt->execute([$estado_a_buscar]);
$registros = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Aprobación y Gestión de Horas</h1>

<?php if (isset($_GET['message'])): ?>
    <div class="alert alert-<?php echo ($_GET['status'] === 'success') ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Pestañas de Navegación -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?php echo ($view === 'pendientes') ? 'active' : ''; ?>" href="?view=pendientes">Pendientes de Aprobación</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($view === 'aprobados') ? 'active' : ''; ?>" href="?view=aprobados">Aprobados Recientemente</a>
    </li>
</ul>

<?php if ($view === 'pendientes'): ?>
    <!-- Contenido de la Pestaña PENDIENTES -->
    <form action="update_status.php" method="POST">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th><input class="form-check-input" type="checkbox" id="selectAll"></th>
                    <th>Empleado</th><th>Fecha</th><th>Horario</th><th>Proyecto</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $row): ?>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" name="registros[]" value="<?php echo $row['id']; ?>"></td>
                        <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                        <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                        <td><?php echo htmlspecialchars(date('h:i A', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('h:i A', strtotime($row['hora_fin']))); ?></td>
                        <td><?php echo htmlspecialchars($row['nombre_proyecto']); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning btn-edit" data-bs-toggle="modal" data-bs-target="#editModal"
                                    data-id="<?php echo $row['id']; ?>" data-fecha="<?php echo $row['fecha_trabajada']; ?>" data-inicio="<?php echo $row['hora_inicio']; ?>" data-fin="<?php echo $row['hora_fin']; ?>">
                                Editar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!empty($registros)): ?>
        <div class="d-flex justify-content-end">
            <button type="submit" name="action" value="Aprobado" class="btn btn-success me-2"><i class="bi bi-check-all"></i> Aprobar Seleccionados</button>
            <button type="submit" name="action" value="Rechazado" class="btn btn-danger"><i class="bi bi-x-circle"></i> Rechazar Seleccionados</button>
        </div>
        <?php endif; ?>
    </form>
<?php elseif ($view === 'aprobados'): ?>
    <!-- Contenido de la Pestaña APROBADOS -->
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Empleado</th><th>Fecha</th><th>Horario</th><th>Proyecto</th><th>Aprobado Por</th><th>Fecha Aprobación</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
             <?php foreach ($registros as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                    <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                    <td><?php echo htmlspecialchars(date('h:i A', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('h:i A', strtotime($row['hora_fin']))); ?></td>
                    <td><?php echo htmlspecialchars($row['nombre_proyecto']); ?></td>
                    <td><?php echo htmlspecialchars($row['aprobador']); ?></td>
                    <td><?php echo htmlspecialchars($row['fecha_aprobacion']); ?></td>
                    <td>
                        <form action="update_status.php" method="POST" onsubmit="return confirm('¿Revertir este registro a Pendiente? Podrás editarlo después.');">
                            <input type="hidden" name="registros[]" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="action" value="Pendiente" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise"></i> Revertir
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>


<!-- Modal de Edición (sin cambios) -->
<div class="modal fade" id="editModal" tabindex="-1">
    <!-- ... (código del modal idéntico al anterior) ... -->
</div>

<script>
// ... (código JavaScript idéntico al anterior) ...
</script>

<?php require_once '../includes/footer.php'; ?>
