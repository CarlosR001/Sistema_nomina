<?php
// approvals/index.php - v2.7 (Mejora Final del Badge de Horas de Gracia)
// 1. El badge ahora suma y muestra el número correcto de horas de gracia (1 o 2).

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

// Determinar la pestaña activa
$view = $_GET['view'] ?? 'pendientes'; 

// Cargar las órdenes "En Proceso" para el dropdown del modal de edición
$ordenes_para_modal = $pdo->query("SELECT id, codigo_orden FROM ordenes WHERE estado_orden = 'En Proceso' ORDER BY codigo_orden")->fetchAll();


// Consulta principal adaptada a la vista
$estado_a_buscar = ($view === 'aprobados') ? 'Aprobado' : 'Pendiente';
$sql = "SELECT 
            r.id, r.fecha_trabajada, r.hora_inicio, r.hora_fin, r.transporte_aprobado,
            r.hora_gracia_antes, r.hora_gracia_despues,
            e.nombres, e.primer_apellido, 
            o.codigo_orden, o.id as orden_id, 
            z.nombre_zona_o_muelle, z.monto_transporte_completo,
            u_aprob.nombre_usuario as aprobador, r.fecha_aprobacion
        FROM RegistroHoras r 
        JOIN Contratos c ON r.id_contrato = c.id 
        JOIN Empleados e ON c.id_empleado = e.id 
        LEFT JOIN ordenes o ON r.id_orden = o.id 
        LEFT JOIN zonastransporte z ON o.id_lugar = z.id
        LEFT JOIN usuarios u_aprob ON r.id_usuario_aprobador = u_aprob.id
        WHERE r.estado_registro = ?
        ORDER BY r.fecha_aprobacion DESC, e.nombres, r.fecha_trabajada";

$stmt = $pdo->prepare($sql);
$stmt->execute([$estado_a_buscar]);
$registros = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Aprobación y Gestión de Horas</h1>

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
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark"><tr><th>Empleado</th><th>Fecha</th><th>Horario</th><th>Proyecto</th><th>Aprobado Por</th><th>Fecha Aprobación</th><th class="text-center">Acciones</th></tr></thead>
            <tbody>
                 <?php foreach ($registros as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                        <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                        <td>
                            <?php echo htmlspecialchars(date('H:i', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('H:i', strtotime($row['hora_fin']))); ?>
                            <!-- MEJORA: El badge ahora suma y muestra el total de horas de gracia -->
                            <?php
                                $horas_gracia_concedidas = 0;
                                if ($row['hora_gracia_antes']) { $horas_gracia_concedidas++; }
                                if ($row['hora_gracia_despues']) { $horas_gracia_concedidas++; }
                            
                                if ($horas_gracia_concedidas > 0):
                            ?>
                                <span class="badge bg-info ms-1" title="Incluye <?php echo $horas_gracia_concedidas; ?> hora(s) de gracia">+<?php echo $horas_gracia_concedidas; ?>H</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['nombre_proyecto']); ?></td>
                        <td><?php echo htmlspecialchars($row['aprobador']); ?></td>
                        <td><?php echo htmlspecialchars($row['fecha_aprobacion']); ?></td>
                        <td class="text-center">
                            <form action="update_status.php" method="POST" onsubmit="return confirm('¿Revertir este registro a Pendiente? Podrás editarlo después.');">
                                <input type="hidden" name="registros[<?php echo $row['id']; ?>][id]" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="action" value="Pendiente" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Revertir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
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
