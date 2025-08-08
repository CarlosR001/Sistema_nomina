<?php
// approvals/index.php - v2.7 (Mejora Final del Badge de Horas de Gracia)
// 1. El badge ahora suma y muestra el número correcto de horas de gracia (1 o 2).
require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

// --- Lógica de Filtros ---
$view = $_GET['view'] ?? 'pendientes';
$estado_a_buscar = ($view === 'aprobados') ? 'Aprobado' : 'Pendiente';

// Recoger valores del formulario de filtro
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$filtro_empleado_id = $_GET['empleado_id'] ?? '';
$filtro_orden_id = $_GET['orden_id'] ?? '';

// Cargar datos para los dropdowns de los filtros y el modal
$empleados_con_registros = $pdo->query("SELECT DISTINCT e.id, e.nombres, e.primer_apellido FROM empleados e JOIN contratos c ON e.id = c.id_empleado JOIN RegistroHoras r ON c.id = r.id_contrato ORDER BY e.nombres")->fetchAll();
$ordenes_con_registros = $pdo->query("SELECT DISTINCT o.id, o.codigo_orden FROM ordenes o JOIN RegistroHoras r ON o.id = r.id_orden ORDER BY o.codigo_orden")->fetchAll();
$ordenes_para_modal = $pdo->query("SELECT id, codigo_orden FROM ordenes WHERE estado_orden = 'En Proceso' ORDER BY codigo_orden")->fetchAll();

// --- Construcción de la Consulta Dinámica ---
$sql = "SELECT 
            r.id, r.fecha_trabajada, r.hora_inicio, r.hora_fin, r.transporte_aprobado,
            r.hora_gracia_antes, r.hora_gracia_despues,
            e.id as empleado_id, e.nombres, e.primer_apellido, 
            o.codigo_orden, o.id as orden_id, 
            z.nombre_zona_o_muelle, z.monto_transporte_completo,
            u_aprob.nombre_usuario as aprobador, r.fecha_aprobacion
        FROM RegistroHoras r 
        JOIN Contratos c ON r.id_contrato = c.id 
        JOIN Empleados e ON c.id_empleado = e.id 
        LEFT JOIN ordenes o ON r.id_orden = o.id 
        LEFT JOIN zonastransporte z ON o.id_lugar = z.id
        LEFT JOIN usuarios u_aprob ON r.id_usuario_aprobador = u_aprob.id
        WHERE r.estado_registro = ?";
$params = [$estado_a_buscar];

if (!empty($filtro_fecha_desde)) {
    $sql .= " AND r.fecha_trabajada >= ?";
    $params[] = $filtro_fecha_desde;
}
if (!empty($filtro_fecha_hasta)) {
    $sql .= " AND r.fecha_trabajada <= ?";
    $params[] = $filtro_fecha_hasta;
}
if (!empty($filtro_empleado_id)) {
    $sql .= " AND e.id = ?";
    $params[] = $filtro_empleado_id;
}
if (!empty($filtro_orden_id)) {
    $sql .= " AND o.id = ?";
    $params[] = $filtro_orden_id;
}

$sql .= " ORDER BY r.fecha_trabajada DESC, e.nombres, r.hora_inicio";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();


require_once '../includes/header.php';
?>

<h1 class="mb-4">Aprobación y Gestión de Horas</h1>
<!-- Formulario de Filtros -->
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-filter me-1"></i>Filtrar Registros</div>
    <div class="card-body">
        <form method="GET">
            <input type="hidden" name="view" value="<?php echo $view; ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label for="fecha_desde" class="form-label">Desde Fecha</label><input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>"></div>
                <div class="col-md-3"><label for="fecha_hasta" class="form-label">Hasta Fecha</label><input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>"></div>
                <div class="col-md-2"><label for="empleado_id" class="form-label">Empleado</label><select class="form-select" id="empleado_id" name="empleado_id"><option value="">Todos</option><?php foreach ($empleados_con_registros as $empleado): ?><option value="<?php echo $empleado['id']; ?>" <?php echo ($filtro_empleado_id == $empleado['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label for="orden_id" class="form-label">Orden</label><select class="form-select" id="orden_id" name="orden_id"><option value="">Todas</option><?php foreach ($ordenes_con_registros as $orden): ?><option value="<?php echo $orden['id']; ?>" <?php echo ($filtro_orden_id == $orden['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($orden['codigo_orden']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2 text-end"><button type="submit" class="btn btn-primary">Filtrar</button><a href="index.php?view=<?php echo $view; ?>" class="btn btn-secondary ms-2">Limpiar</a></div>
            </div>
        </form>
    </div>
</div>


<?php if (isset($_GET['message'])): ?>
    <div class="alert alert-<?php echo ($_GET['status'] === 'success') ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?php echo ($view === 'pendientes') ? 'active' : ''; ?>" href="?view=pendientes">Pendientes de Aprobación</a></li>
    <li class="nav-item"><a class="nav-link <?php echo ($view === 'aprobados') ? 'active' : ''; ?>" href="?view=aprobados">Aprobados Recientemente</a></li>
</ul>

<?php if ($view === 'pendientes'): ?>
    <form action="update_status.php" method="POST">
        <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
    <thead class="table-dark">
        <tr>
            <th><input class="form-check-input" type="checkbox" id="selectAll"></th>
            <th>Empleado</th><th>Fecha</th><th>Horario</th><th>Orden</th><th>Transporte</th><th class="text-center">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($registros as $row): ?>
            <tr>
                <td><input class="form-check-input" type="checkbox" name="registros[<?php echo $row['id']; ?>][id]" value="<?php echo $row['id']; ?>"></td>
                <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                <td>
                    <?php echo htmlspecialchars(date('H:i', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('H:i', strtotime($row['hora_fin']))); ?>
                    <?php $horas_gracia = ($row['hora_gracia_antes'] ? 1 : 0) + ($row['hora_gracia_despues'] ? 1 : 0); if ($horas_gracia > 0): ?>
                        <span class="badge bg-info ms-1" title="Incluye <?php echo $horas_gracia; ?> hora(s) de gracia">+<?php echo $horas_gracia; ?>H</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($row['codigo_orden'] ?? 'N/A (Registro Antiguo)'); ?></td>
                <td>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="registros[<?php echo $row['id']; ?>][transporte]" value="1" <?php echo ($row['transporte_aprobado'] ? 'checked' : ''); ?>>
                        <label class="form-check-label"><?php echo htmlspecialchars($row['nombre_zona_o_muelle'] ?? 'N/A'); ?> <span class="text-muted">($<?php echo number_format($row['monto_transporte_completo'] ?? 0, 2); ?>)</span></label>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-warning btn-edit" data-bs-toggle="modal" data-bs-target="#editModal"
                            data-id="<?php echo $row['id']; ?>"
                            data-fecha="<?php echo $row['fecha_trabajada']; ?>"
                            data-inicio="<?php echo (int)date('H', strtotime($row['hora_inicio'])); ?>"
                            data-fin="<?php echo (date('H:i', strtotime($row['hora_fin'])) == '23:59') ? 24 : (int)date('H', strtotime($row['hora_fin'])); ?>"
                            data-orden-id="<?php echo $row['orden_id']; ?>"
                            data-transporte-aprobado="<?php echo $row['transporte_aprobado']; ?>"
                            data-gracia-antes="<?php echo $row['hora_gracia_antes']; ?>"
                            data-gracia-despues="<?php echo $row['hora_gracia_despues']; ?>">
                        Editar
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

        </div>
        <?php if (!empty($registros)): ?>
        <div class="d-flex justify-content-end mt-3">
            <button type="submit" name="action" value="Aprobado" class="btn btn-success me-2"><i class="bi bi-check-all"></i> Aprobar</button>
            <button type="submit" name="action" value="Rechazado" class="btn btn-danger"><i class="bi bi-x-circle"></i> Rechazar</button>
        </div>
        <?php endif; ?>
    </form>
    <?php elseif ($view === 'aprobados'): ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark"><tr><th>Empleado</th><th>Fecha</th><th>Horario</th><th>Orden</th><th>Aprobado Por</th><th>Fecha Aprobación</th><th class="text-center">Acciones</th></tr></thead>
            <tbody>
                <?php if (empty($registros)): ?>
                    <tr><td colspan="7" class="text-center">No se encontraron registros con los filtros aplicados.</td></tr>
                <?php else: ?>
                    <?php foreach ($registros as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                            <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                            <td><?php echo htmlspecialchars(date('H:i', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('H:i', strtotime($row['hora_fin']))); ?></td>
                            <td><?php echo htmlspecialchars($row['codigo_orden'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['aprobador']); ?></td>
                            <td><?php echo htmlspecialchars($row['fecha_aprobacion']); ?></td>
                            <td class="text-center">
                                <form action="update_status.php" method="POST" onsubmit="return confirm('¿Revertir este registro a Pendiente?');">
                                    <input type="hidden" name="registros[<?php echo $row['id']; ?>][id]" value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="action" value="Pendiente" class="btn btn-sm btn-outline-secondary" title="Revertir a Pendiente">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal de Edición (Actualizado para Órdenes) -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="editModalTitle">Editar Registro</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="update_record.php" method="POST" id="editForm">
        <div class="modal-body">
            <input type="hidden" id="edit-registro-id" name="registro_id">
            <div class="row g-3">
                <div class="col-md-6"><label for="edit-fecha" class="form-label">Fecha Trabajada</label><input type="date" class="form-control" id="edit-fecha" name="fecha_trabajada" required></div>
                <div class="col-md-6"><label for="edit-orden" class="form-label">Reasignar Orden</label>
                    <select class="form-select" id="edit-orden" name="id_orden" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($ordenes_para_modal as $orden): ?>
                            <option value="<?php echo $orden['id']; ?>"><?php echo htmlspecialchars($orden['codigo_orden']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label for="edit-inicio" class="form-label">Hora Inicio (0-24)</label><input type="number" class="form-control" id="edit-inicio" name="hora_inicio" min="0" max="24" required></div>
                <div class="col-md-6"><label for="edit-fin" class="form-label">Hora Fin (0-24)</label><input type="number" class="form-control" id="edit-fin" name="hora_fin" min="0" max="24" required></div>
                <div class="col-12"><hr><label class="form-label fw-bold">Gestión de Pagos Adicionales:</label>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="transporte_aprobado" id="edit-transporte" value="1"><label class="form-check-label" for="edit-transporte">Aprobar pago de transporte</label></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="hora_gracia_antes" id="edit-gracia-antes" value="1"><label class="form-check-label" for="edit-gracia-antes">Aprobar hora de gracia ANTES del turno</label></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="hora_gracia_despues" id="edit-gracia-despues" value="1"><label class="form-check-label" for="edit-gracia-despues">Aprobar hora de gracia DESPUÉS del turno</label></div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var recordId = button.getAttribute('data-id');
            var fecha = button.getAttribute('data-fecha');
            var inicio = button.getAttribute('data-inicio');
            var fin = button.getAttribute('data-fin');
            var ordenId = button.getAttribute('data-orden-id'); // Nuevo
            var transporteAprobado = button.getAttribute('data-transporte-aprobado');
            var graciaAntes = button.getAttribute('data-gracia-antes');
            var graciaDespues = button.getAttribute('data-gracia-despues');

            document.getElementById('edit-registro-id').value = recordId;
            document.getElementById('edit-fecha').value = fecha;
            document.getElementById('edit-inicio').value = inicio;
            document.getElementById('edit-fin').value = fin;
            document.getElementById('edit-orden').value = ordenId; // Nuevo
            document.getElementById('edit-transporte').checked = (transporteAprobado === '1');
            document.getElementById('edit-gracia-antes').checked = (graciaAntes === '1');
            document.getElementById('edit-gracia-despues').checked = (graciaDespues === '1');
        });
    }

    var selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('click', function(event) {
            var checkboxes = document.querySelectorAll('input[name^="registros["][type="checkbox"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = event.target.checked;
            });
        });
    }
});
</script>



<?php require_once '../includes/footer.php'; ?>
