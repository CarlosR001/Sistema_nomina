<?php
// approvals/index.php - v2.1
// Adapta el modal de edición para usar horas en formato numérico.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

$view = $_GET['view'] ?? 'pendientes';
$proyectos = $pdo->query("SELECT id, nombre_proyecto FROM Proyectos WHERE estado_proyecto = 'Activo'")->fetchAll();
$zonas = $pdo->query("SELECT id, nombre_zona_o_muelle FROM ZonasTransporte")->fetchAll();

$estado_a_buscar = ($view === 'aprobados') ? 'Aprobado' : 'Pendiente';
$sql = "SELECT r.id, r.fecha_trabajada, r.hora_inicio, r.hora_fin, e.nombres, e.primer_apellido, p.nombre_proyecto, p.id as proyecto_id, r.id_zona_trabajo, u_aprob.nombre_usuario as aprobador, r.fecha_aprobacion
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

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?php echo ($view === 'pendientes') ? 'active' : ''; ?>" href="?view=pendientes">Pendientes de Aprobación</a></li>
    <li class="nav-item"><a class="nav-link <?php echo ($view === 'aprobados') ? 'active' : ''; ?>" href="?view=aprobados">Aprobados Recientemente</a></li>
</ul>

<?php if ($view === 'pendientes'): ?>
    <form action="update_status.php" method="POST">
        <table class="table table-striped table-hover">
            <thead class="table-dark"><tr><th><input class="form-check-input" type="checkbox" id="selectAll"></th><th>Empleado</th><th>Fecha</th><th>Horario</th><th>Proyecto</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($registros as $row): ?>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" name="registros[]" value="<?php echo $row['id']; ?>"></td>
                        <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                        <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                        <td><?php echo htmlspecialchars(date('H:i', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('H:i', strtotime($row['hora_fin']))); ?></td>
                        <td><?php echo htmlspecialchars($row['nombre_proyecto']); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning btn-edit" data-bs-toggle="modal" data-bs-target="#editModal"
                                    data-id="<?php echo $row['id']; ?>"
                                    data-fecha="<?php echo $row['fecha_trabajada']; ?>"
                                    data-inicio="<?php echo date('H', strtotime($row['hora_inicio'])); // Enviar solo la hora ?>"
                                    data-fin="<?php echo (date('H', strtotime($row['hora_fin'])) == 23) ? 24 : date('H', strtotime($row['hora_fin'])); // Convertir 23:59 a 24 ?>"
                                    data-proyecto-id="<?php echo $row['proyecto_id']; ?>"
                                    data-zona-id="<?php echo $row['id_zona_trabajo']; ?>">
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
    <table class="table table-striped table-hover">
        <thead class="table-dark"><tr><th>Empleado</th><th>Fecha</th><th>Horario</th><th>Proyecto</th><th>Aprobado Por</th><th>Fecha Aprobación</th><th>Acciones</th></tr></thead>
        <tbody>
             <?php foreach ($registros as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                    <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                    <td><?php echo htmlspecialchars(date('H:i', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('H:i', strtotime($row['hora_fin']))); ?></td>
                    <td><?php echo htmlspecialchars($row['nombre_proyecto']); ?></td>
                    <td><?php echo htmlspecialchars($row['aprobador']); ?></td>
                    <td><?php echo htmlspecialchars($row['fecha_aprobacion']); ?></td>
                    <td>
                        <form action="update_status.php" method="POST" onsubmit="return confirm('¿Revertir este registro a Pendiente? Podrás editarlo después.');">
                            <input type="hidden" name="registros[]" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="action" value="Pendiente" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Revertir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Editar Registro de Horas</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <form id="editForm" action="update_record.php" method="POST">
          <input type="hidden" name="registro_id" id="edit-registro-id">
          <div class="mb-3"><label for="edit-fecha" class="form-label">Fecha</label><input type="date" class="form-control" id="edit-fecha" name="fecha_trabajada" required></div>
          <div class="row">
              <div class="col"><label for="edit-inicio" class="form-label">Hora Inicio (Formato 24h)</label><input type="number" class="form-control" id="edit-inicio" name="hora_inicio" min="0" max="24" required></div>
              <div class="col"><label for="edit-fin" class="form-label">Hora Fin (Formato 24h)</label><input type="number" class="form-control" id="edit-fin" name="hora_fin" min="0" max="24" required></div>
          </div>
          <div class="mb-3 mt-3"><label for="edit-proyecto" class="form-label">Proyecto</label><select class="form-select" id="edit-proyecto" name="id_proyecto" required><?php foreach ($proyectos as $proyecto): ?><option value="<?php echo $proyecto['id']; ?>"><?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?></option><?php endforeach; ?></select></div>
          <div class="mb-3"><label for="edit-zona" class="form-label">Zona / Muelle</label><select class="form-select" id="edit-zona" name="id_zona_trabajo" required><?php foreach ($zonas as $zona): ?><option value="<?php echo $zona['id']; ?>"><?php echo htmlspecialchars($zona['nombre_zona_o_muelle']); ?></option><?php endforeach; ?></select></div>
      </form>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="editForm" class="btn btn-primary">Guardar Cambios</button></div>
  </div></div>
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
        var proyectoId = button.getAttribute('data-proyecto-id');
        var zonaId = button.getAttribute('data-zona-id');
        
        editModal.querySelector('#edit-registro-id').value = id;
        editModal.querySelector('#edit-fecha').value = fecha;
        editModal.querySelector('#edit-inicio').value = inicio;
        editModal.querySelector('#edit-fin').value = fin;
        editModal.querySelector('#edit-proyecto').value = proyectoId;
        editModal.querySelector('#edit-zona').value = zonaId;
    });
    document.getElementById('selectAll')?.addEventListener('click', function(event) {
        document.querySelectorAll('input[name="registros[]"]').forEach(c => c.checked = event.target.checked);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
