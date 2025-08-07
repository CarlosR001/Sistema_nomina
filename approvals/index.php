<?php
// approvals/index.php - v2.5 (Corrección definitiva del modal de edición)
// 1. Se añaden campos faltantes a la consulta SQL.
// 2. Se añaden los atributos data-* correspondientes al botón de editar.
// 3. Se reescribe el JavaScript para que lea los atributos del botón y rellene el modal correctamente.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

// Determinar la pestaña activa
$view = $_GET['view'] ?? 'pendientes'; 

// Obtener datos para los dropdowns del modal
$proyectos = $pdo->query("SELECT id, nombre_proyecto FROM Proyectos WHERE estado_proyecto = 'Activo'")->fetchAll();
$zonas = $pdo->query("SELECT id, nombre_zona_o_muelle FROM ZonasTransporte")->fetchAll();

// Consulta principal adaptada a la vista
$estado_a_buscar = ($view === 'aprobados') ? 'Aprobado' : 'Pendiente';
// CORRECCIÓN 1: Añadir todos los campos necesarios para el modal
$sql = "SELECT 
            r.id, r.fecha_trabajada, r.hora_inicio, r.hora_fin, r.transporte_aprobado,
            r.hora_gracia_antes, r.hora_gracia_despues,
            e.nombres, e.primer_apellido, 
            p.nombre_proyecto, p.id as proyecto_id, 
            r.id_zona_trabajo, z.nombre_zona_o_muelle, z.monto_transporte_completo,
            u_aprob.nombre_usuario as aprobador, r.fecha_aprobacion
        FROM RegistroHoras r 
        JOIN Contratos c ON r.id_contrato = c.id 
        JOIN Empleados e ON c.id_empleado = e.id 
        JOIN Proyectos p ON r.id_proyecto = p.id 
        JOIN ZonasTransporte z ON r.id_zona_trabajo = z.id
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
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th><input class="form-check-input" type="checkbox" id="selectAll"></th>
                        <th>Empleado</th><th>Fecha</th><th>Horario</th><th>Proyecto</th><th>Transporte</th><th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $row): ?>
                        <tr>
                            <td><input class="form-check-input" type="checkbox" name="registros[<?php echo $row['id']; ?>][id]" value="<?php echo $row['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                            <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                            <td><?php echo htmlspecialchars(date('H:i', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('H:i', strtotime($row['hora_fin']))); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_proyecto']); ?></td>
                            <td>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="registros[<?php echo $row['id']; ?>][transporte]" value="1" checked><label class="form-check-label"><?php echo htmlspecialchars($row['nombre_zona_o_muelle']); ?> <span class="text-muted">($<?php echo number_format($row['monto_transporte_completo'], 2); ?>)</span></label></div>
                            </td>
                            <td class="text-center">
                                <!-- CORRECCIÓN 2: Añadir todos los atributos data-* necesarios -->
                                <button type="button" class="btn btn-sm btn-warning btn-edit" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $row['id']; ?>"
                                        data-fecha="<?php echo $row['fecha_trabajada']; ?>"
                                        data-inicio="<?php echo (int)date('H', strtotime($row['hora_inicio'])); ?>"
                                        data-fin="<?php echo (date('H:i', strtotime($row['hora_fin'])) == '23:59') ? 24 : (int)date('H', strtotime($row['hora_fin'])); ?>"
                                        data-proyecto-id="<?php echo $row['proyecto_id']; ?>"
                                        data-zona-id="<?php echo $row['id_zona_trabajo']; ?>"
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
            <thead class="table-dark"><tr><th>Empleado</th><th>Fecha</th><th>Horario</th><th>Proyecto</th><th>Aprobado Por</th><th>Fecha Aprobación</th><th class="text-center">Acciones</th></tr></thead>
            <tbody>
                 <?php foreach ($registros as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                        <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                        <td><?php echo htmlspecialchars(date('H:i', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('H:i', strtotime($row['hora_fin']))); ?></td>
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

<!-- Modal de Edición -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalTitle">Editar Registro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="update_record.php" method="POST" id="editForm">
        <div class="modal-body">
            <input type="hidden" id="edit-registro-id" name="registro_id">
            <div class="row g-3">
                <div class="col-md-6"><label for="edit-fecha" class="form-label">Fecha Trabajada</label><input type="date" class="form-control" id="edit-fecha" name="fecha_trabajada" required></div>
                <div class="col-md-6"><label for="edit-proyecto" class="form-label">Proyecto</label><select class="form-select" id="edit-proyecto" name="id_proyecto" required><?php foreach ($proyectos as $proyecto): ?><option value="<?php echo $proyecto['id']; ?>"><?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label for="edit-inicio" class="form-label">Hora Inicio (0-24)</label><input type="number" class="form-control" id="edit-inicio" name="hora_inicio" min="0" max="24" required></div>
                <div class="col-md-6"><label for="edit-fin" class="form-label">Hora Fin (0-24)</label><input type="number" class="form-control" id="edit-fin" name="hora_fin" min="0" max="24" required></div>
                <div class="col-12"><label for="edit-zona" class="form-label">Zona / Muelle</label><select class="form-select" id="edit-zona" name="id_zona_trabajo" required><?php foreach ($zonas as $zona): ?><option value="<?php echo $zona['id']; ?>"><?php echo htmlspecialchars($zona['nombre_zona_o_muelle']); ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><hr>
                    <label class="form-label fw-bold">Gestión de Pagos Adicionales:</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="transporte_aprobado" id="edit-transporte" value="1">
                        <label class="form-check-label" for="edit-transporte">Aprobar pago de transporte</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="hora_gracia_antes" id="edit-gracia-antes" value="1">
                        <label class="form-check-label" for="edit-gracia-antes">Aprobar hora de gracia ANTES del turno</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="hora_gracia_despues" id="edit-gracia-despues" value="1">
                        <label class="form-check-label" for="edit-gracia-despues">Aprobar hora de gracia DESPUÉS del turno</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CORRECCIÓN 3: JavaScript reescrito para funcionar correctamente -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Botón que activó el modal

            // Extraer información de los atributos data-* del botón
            var recordId = button.getAttribute('data-id');
            var fecha = button.getAttribute('data-fecha');
            var inicio = button.getAttribute('data-inicio');
            var fin = button.getAttribute('data-fin');
            var proyectoId = button.getAttribute('data-proyecto-id');
            var zonaId = button.getAttribute('data-zona-id');
            var transporteAprobado = button.getAttribute('data-transporte-aprobado');
            var graciaAntes = button.getAttribute('data-gracia-antes');
            var graciaDespues = button.getAttribute('data-gracia-despues');

            // Poblar el formulario del modal
            document.getElementById('edit-registro-id').value = recordId;
            document.getElementById('edit-fecha').value = fecha;
            document.getElementById('edit-inicio').value = inicio;
            document.getElementById('edit-fin').value = fin;
            document.getElementById('edit-proyecto').value = proyectoId;
            document.getElementById('edit-zona').value = zonaId;
            document.getElementById('editModalTitle').textContent = 'Editar Registro ID: ' + recordId;

            // Para los checkboxes, '1' significa marcado, cualquier otro valor (incluyendo '0' o null) es desmarcado.
            document.getElementById('edit-transporte').checked = (transporteAprobado === '1');
            document.getElementById('edit-gracia-antes').checked = (graciaAntes === '1');
            document.getElementById('edit-gracia-despues').checked = (graciaDespues === '1');
        });
    }

    // Lógica para el checkbox "seleccionar todo"
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
