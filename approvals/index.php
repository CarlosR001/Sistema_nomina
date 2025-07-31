<?php
// approvals/index.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

// Obtener datos para los dropdowns del modal
$proyectos = $pdo->query("SELECT id, nombre_proyecto FROM Proyectos WHERE estado_proyecto = 'Activo'")->fetchAll();
$zonas = $pdo->query("SELECT id, nombre_zona_o_muelle FROM ZonasTransporte")->fetchAll();

// Consulta principal para obtener registros pendientes
$sql = "SELECT r.id, r.fecha_trabajada, r.hora_inicio, r.hora_fin, e.nombres, e.primer_apellido, p.nombre_proyecto 
        FROM RegistroHoras r 
        JOIN Contratos c ON r.id_contrato = c.id 
        JOIN Empleados e ON c.id_empleado = e.id 
        JOIN Proyectos p ON r.id_proyecto = p.id 
        WHERE r.estado_registro = 'Pendiente' 
        ORDER BY e.nombres, r.fecha_trabajada";
$stmt = $pdo->query($sql);
$registros_pendientes = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Aprobación y Gestión de Horas</h1>

<?php if (isset($_GET['message'])): ?>
    <div class="alert alert-<?php echo ($_GET['status'] === 'success') ? 'success' : 'danger'; ?>">
        <?php echo htmlspecialchars($_GET['message']); ?>
    </div>
<?php endif; ?>

<form action="update_status.php" method="POST">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th><input class="form-check-input" type="checkbox" id="selectAll"></th>
                <th>Empleado</th>
                <th>Fecha</th>
                <th>Horario</th>
                <th>Proyecto</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($registros_pendientes as $row): ?>
                <tr>
                    <td><input class="form-check-input" type="checkbox" name="registros[]" value="<?php echo $row['id']; ?>"></td>
                    <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                    <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                    <td><?php echo htmlspecialchars(date('h:i A', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('h:i A', strtotime($row['hora_fin']))); ?></td>
                    <td><?php echo htmlspecialchars($row['nombre_proyecto']); ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-warning btn-edit" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editModal"
                                data-id="<?php echo $row['id']; ?>"
                                data-fecha="<?php echo $row['fecha_trabajada']; ?>"
                                data-inicio="<?php echo $row['hora_inicio']; ?>"
                                data-fin="<?php echo $row['hora_fin']; ?>">
                            Editar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="d-flex justify-content-end">
        <button type="submit" name="action" value="Aprobado" class="btn btn-success me-2">Aprobar Seleccionados</button>
        <button type="submit" name="action" value="Rechazado" class="btn btn-danger">Rechazar Seleccionados</button>
    </div>
</form>

<!-- Modal de Edición -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar Registro de Horas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editForm" action="update_record.php" method="POST">
            <input type="hidden" name="registro_id" id="edit-registro-id">
            <div class="mb-3">
                <label for="edit-fecha" class="form-label">Fecha</label>
                <input type="date" class="form-control" id="edit-fecha" name="fecha_trabajada" required>
            </div>
            <div class="row">
                <div class="col">
                    <label for="edit-inicio" class="form-label">Hora Inicio</label>
                    <input type="time" class="form-control" id="edit-inicio" name="hora_inicio" required>
                </div>
                <div class="col">
                    <label for="edit-fin" class="form-label">Hora Fin</label>
                    <input type="time" class="form-control" id="edit-fin" name="hora_fin" required>
                </div>
            </div>
            <div class="mb-3 mt-3">
                <label for="edit-proyecto" class="form-label">Proyecto</label>
                <select class="form-select" id="edit-proyecto" name="id_proyecto" required>
                    <?php foreach ($proyectos as $proyecto): ?>
                        <option value="<?php echo $proyecto['id']; ?>"><?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="edit-zona" class="form-label">Zona / Muelle</label>
                <select class="form-select" id="edit-zona" name="id_zona_trabajo" required>
                     <?php foreach ($zonas as $zona): ?>
                        <option value="<?php echo $zona['id']; ?>"><?php echo htmlspecialchars($zona['nombre_zona_o_muelle']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" form="editForm" class="btn btn-primary">Guardar Cambios</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var id = button.getAttribute('data-id');
        var fecha = button.getAttribute('data-fecha');
        var inicio = button.getAttribute('data-inicio');
        var fin = button.getAttribute('data-fin');
        
        var modalTitle = editModal.querySelector('.modal-title');
        var idInput = editModal.querySelector('#edit-registro-id');
        var fechaInput = editModal.querySelector('#edit-fecha');
        var inicioInput = editModal.querySelector('#edit-inicio');
        var finInput = editModal.querySelector('#edit-fin');
        
        modalTitle.textContent = 'Editar Registro de Horas #' + id;
        idInput.value = id;
        fechaInput.value = fecha;
        inicioInput.value = inicio;
        finInput.value = fin;
    });

    document.getElementById('selectAll').addEventListener('click', function(event) {
        let checkboxes = document.querySelectorAll('input[name="registros[]"]');
        for (let checkbox of checkboxes) {
            checkbox.checked = event.target.checked;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
