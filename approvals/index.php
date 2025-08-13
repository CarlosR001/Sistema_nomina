<?php
// approvals/index.php - v6.2 (Filtros Persistentes - Corrección Definitiva)
require_once '../auth.php';
require_login();
require_permission('aprobaciones.gestionar');

// --- Lógica de Filtros y Vistas ---
$view = $_GET['view'] ?? 'pendientes';
$estado_a_buscar = ($view === 'aprobados') ? 'Aprobado' : 'Pendiente';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$filtro_empleado_id = $_GET['empleado_id'] ?? '';
$filtro_orden_id = $_GET['orden_id'] ?? '';
// CREACIÓN DE LA CADENA DE FILTROS PARA PERSISTENCIA
$filter_query_string = http_build_query(array_filter(compact('view', 'filtro_fecha_desde', 'filtro_fecha_hasta', 'filtro_empleado_id', 'filtro_orden_id')));

// --- Carga de Datos ---
$empleados_con_registros = $pdo->query("SELECT DISTINCT e.id, e.nombres, e.primer_apellido FROM empleados e JOIN contratos c ON e.id = c.id_empleado JOIN registrohoras r ON c.id = r.id_contrato ORDER BY e.nombres")->fetchAll();
$ordenes_con_registros = $pdo->query("SELECT DISTINCT o.id, o.codigo_orden FROM ordenes o JOIN registrohoras r ON o.id = r.id_orden ORDER BY o.codigo_orden")->fetchAll();
$ordenes_para_modal = $pdo->query("SELECT id, codigo_orden FROM ordenes WHERE estado_orden = 'En Proceso' ORDER BY codigo_orden")->fetchAll();
$todos_los_lugares = $pdo->query("SELECT id, nombre_zona_o_muelle, parent_id FROM lugares ORDER BY nombre_zona_o_muelle")->fetchAll();

$sql = "SELECT 
            r.id, r.fecha_trabajada, r.hora_inicio, r.hora_fin, 
            r.transporte_aprobado, r.transporte_mitad, r.hora_gracia_antes, r.hora_gracia_despues,
            r.id_zona_trabajo, e.nombres, e.primer_apellido, 
            o.id as orden_id, o.codigo_orden, l.nombre_zona_o_muelle, l.monto_transporte_completo,
            u_aprob.nombre_usuario as aprobador, r.fecha_aprobacion
        FROM registrohoras r 
        JOIN contratos c ON r.id_contrato = c.id 
        JOIN empleados e ON c.id_empleado = e.id 
        LEFT JOIN ordenes o ON r.id_orden = o.id 
        LEFT JOIN lugares l ON r.id_zona_trabajo = l.id
        LEFT JOIN usuarios u_aprob ON r.id_usuario_aprobador = u_aprob.id
        WHERE r.estado_registro = ?";
$params = [$estado_a_buscar];
if (!empty($filtro_fecha_desde)) { $sql .= " AND r.fecha_trabajada >= ?"; $params[] = $filtro_fecha_desde; }
if (!empty($filtro_fecha_hasta)) { $sql .= " AND r.fecha_trabajada <= ?"; $params[] = $filtro_fecha_hasta; }
if (!empty($filtro_empleado_id)) { $sql .= " AND e.id = ?"; $params[] = $filtro_empleado_id; }
if (!empty($filtro_orden_id)) { $sql .= " AND o.id = ?"; $params[] = $filtro_orden_id; }
$sql .= " ORDER BY r.fecha_trabajada DESC, e.nombres, r.hora_inicio";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Aprobación de Horas</h1>
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-filter"></i> Filtrar Registros</div>
    <div class="card-body">
        <form method="GET">
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label">Desde</label><input type="date" class="form-control" name="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>"></div>
                <div class="col-md-3"><label class="form-label">Hasta</label><input type="date" class="form-control" name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>"></div>
                <div class="col-md-2"><label class="form-label">Empleado</label><select class="form-select" name="empleado_id"><option value="">Todos</option><?php foreach ($empleados_con_registros as $e): ?><option value="<?php echo $e['id']; ?>" <?php echo ($filtro_empleado_id == $e['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($e['nombres'] . ' ' . $e['primer_apellido']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Orden</label><select class="form-select" name="orden_id"><option value="">Todas</option><?php foreach ($ordenes_con_registros as $o): ?><option value="<?php echo $o['id']; ?>" <?php echo ($filtro_orden_id == $o['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($o['codigo_orden']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2 text-end"><button type="submit" class="btn btn-primary">Filtrar</button><a href="index.php?view=<?php echo htmlspecialchars($view); ?>" class="btn btn-secondary ms-2">Limpiar</a></div>
            </div>
        </form>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?php echo ($view === 'pendientes') ? 'active' : ''; ?>" href="?view=pendientes">Pendientes</a></li>
    <li class="nav-item"><a class="nav-link <?php echo ($view === 'aprobados') ? 'active' : ''; ?>" href="?view=aprobados">Aprobados</a></li>
</ul>

<?php if ($view === 'pendientes'): ?>
    <form action="update_status.php" method="POST">
        <input type="hidden" name="filters" value="<?php echo htmlspecialchars($filter_query_string); ?>">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th><input class="form-check-input" type="checkbox" id="selectAll"></th>
                        <th>Empleado</th><th>Fecha/Hora</th><th>Orden</th><th>Lugar</th>
                        <th class="text-end">Monto</th><th>Aprobar (100%)</th><th>Reducir (50%)</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $row): ?>
                        <tr class="registro-row" data-monto-completo="<?php echo htmlspecialchars($row['monto_transporte_completo'] ?? 0); ?>">
                            <td><input class="form-check-input record-checkbox" type="checkbox" name="registros[<?php echo $row['id']; ?>][id]" value="<?php echo $row['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                            <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?><br><span class="text-muted"><?php echo date('H:i', strtotime($row['hora_inicio'])) . " - " . date('H:i', strtotime($row['hora_fin'])); ?></span>
                                <?php $horas_gracia = ($row['hora_gracia_antes'] ? 1 : 0) + ($row['hora_gracia_despues'] ? 1 : 0); if ($horas_gracia > 0): ?>
                                    <span class="badge bg-info ms-1" title="Incluye <?php echo $horas_gracia; ?> hora(s) de gracia">+<?php echo $horas_gracia; ?>H</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['codigo_orden'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_zona_o_muelle'] ?? 'N/A'); ?></td>
                            <td class="text-end fw-bold monto-transporte-cell">$0.00</td>
                            <td><div class="form-check form-switch"><input class="form-check-input transporte-cien" type="checkbox" name="registros[<?php echo $row['id']; ?>][transporte]" value="1" <?php echo ($row['transporte_aprobado'] ? 'checked' : ''); ?>></div></td>
                            <td><div class="form-check form-switch"><input class="form-check-input transporte-mitad" type="checkbox" name="registros[<?php echo $row['id']; ?>][transporte_mitad]" value="1" <?php echo ($row['transporte_mitad'] ? 'checked' : ''); ?>></div></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
                                    data-id="<?php echo $row['id']; ?>" data-fecha="<?php echo $row['fecha_trabajada']; ?>" data-inicio="<?php echo (int)date('H', strtotime($row['hora_inicio'])); ?>" data-fin="<?php echo (date('H:i', strtotime($row['hora_fin'])) == '23:59') ? 24 : (int)date('H', strtotime($row['hora_fin'])); ?>"
                                    data-orden-id="<?php echo $row['orden_id']; ?>" data-zona-id="<?php echo $row['id_zona_trabajo']; ?>"
                                    data-transporte-aprobado="<?php echo $row['transporte_aprobado']; ?>" data-transporte-mitad="<?php echo $row['transporte_mitad']; ?>"
                                    data-gracia-antes="<?php echo $row['hora_gracia_antes']; ?>" data-gracia-despues="<?php echo $row['hora_gracia_despues']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($registros)): ?>
        <div class="d-flex justify-content-end mt-3">
            <button type="submit" name="action" value="Aprobado" class="btn btn-success me-2"><i class="bi bi-check-all"></i> Aprobar Seleccionados</button>
            <button type="submit" name="action" value="Rechazado" class="btn btn-danger"><i class="bi bi-x-circle"></i> Rechazar Seleccionados</button>
        </div>
        <?php endif; ?>
    </form>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark"><tr><th>Empleado</th><th>Fecha/Hora</th><th>Orden</th><th>Lugar</th><th>Aprobado Por</th><th>Fecha</th><th class="text-center">Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($registros as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($row['fecha_trabajada']); ?> <span class="text-muted"><?php echo date('H:i', strtotime($row['hora_inicio'])) ."-". date('H:i', strtotime($row['hora_fin'])); ?></span>
                            <?php $horas_gracia = ($row['hora_gracia_antes'] ? 1 : 0) + ($row['hora_gracia_despues'] ? 1 : 0); if ($horas_gracia > 0): ?>
                                <span class="badge bg-info ms-1" title="Incluye <?php echo $horas_gracia; ?> hora(s) de gracia">+<?php echo $horas_gracia; ?>H</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['codigo_orden'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['nombre_zona_o_muelle'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['aprobador'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['fecha_aprobacion'] ?? 'N/A'); ?></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
                                data-id="<?php echo $row['id']; ?>" data-fecha="<?php echo $row['fecha_trabajada']; ?>" data-inicio="<?php echo (int)date('H', strtotime($row['hora_inicio'])); ?>" data-fin="<?php echo (date('H:i', strtotime($row['hora_fin'])) == '23:59') ? 24 : (int)date('H', strtotime($row['hora_fin'])); ?>"
                                data-orden-id="<?php echo $row['orden_id']; ?>" data-zona-id="<?php echo $row['id_zona_trabajo']; ?>"
                                data-transporte-aprobado="<?php echo $row['transporte_aprobado']; ?>" data-transporte-mitad="<?php echo $row['transporte_mitad']; ?>"
                                data-gracia-antes="<?php echo $row['hora_gracia_antes']; ?>" data-gracia-despues="<?php echo $row['hora_gracia_despues']; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="update_status.php" method="POST" class="d-inline">
                                <input type="hidden" name="filters" value="<?php echo htmlspecialchars($filter_query_string); ?>">
                                <input type="hidden" name="registros[<?php echo $row['id']; ?>][id]" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="action" value="Pendiente" class="btn btn-sm btn-outline-secondary" title="Revertir a Pendiente" onclick="return confirm('¿Revertir este registro a Pendiente?');"><i class="bi bi-arrow-counterclockwise"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Editar Registro</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
          <form action="update_record.php" method="POST" id="editForm">
              <input type="hidden" name="filters" value="<?php echo htmlspecialchars($filter_query_string); ?>">
              <input type="hidden" id="edit-registro-id" name="registro_id">
              <div class="row g-3">
                  <div class="col-md-6"><label class="form-label">Fecha</label><input type="date" class="form-control" id="edit-fecha" name="fecha_trabajada" required></div>
                  <div class="col-md-6"><label class="form-label">Orden</label><select class="form-select" id="edit-orden" name="id_orden" required><?php foreach ($ordenes_para_modal as $o): ?><option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['codigo_orden']); ?></option><?php endforeach; ?></select></div>
                  <div class="col-md-12"><label class="form-label">Lugar Específico</label><select class="form-select" id="edit-zona" name="id_zona_trabajo" required><option value="">Seleccione...</option><?php foreach ($todos_los_lugares as $lugar): ?><option value="<?php echo $lugar['id']; ?>" class="<?php echo $lugar['parent_id'] ? 'sublugar' : ''; ?>"><?php echo $lugar['parent_id'] ? '&nbsp;&nbsp;&nbsp;' : ''; ?><?php echo htmlspecialchars($lugar['nombre_zona_o_muelle']); ?></option><?php endforeach; ?></select></div>
                  <div class="col-md-6"><label class="form-label">Hora Inicio</label><input type="number" class="form-control" id="edit-inicio" name="hora_inicio" min="0" max="24" required></div>
                  <div class="col-md-6"><label class="form-label">Hora Fin</label><input type="number" class="form-control" id="edit-fin" name="hora_fin" min="0" max="24" required></div>
                  <hr class="my-3"><div class="col-12">
                      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="transporte_aprobado" id="edit-transporte" value="1"><label class="form-check-label" for="edit-transporte">Aprobar transporte (100%)</label></div>
                      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="transporte_mitad" id="edit-mitad" value="1"><label class="form-check-label" for="edit-mitad">Reducir a mitad (50%)</label></div>
                      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="hora_gracia_antes" id="edit-gracia-antes" value="1"><label class="form-check-label" for="edit-gracia-antes">Aprobar gracia ANTES</label></div>
                      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="hora_gracia_despues" id="edit-gracia-despues" value="1"><label class="form-check-label" for="edit-gracia-despues">Aprobar gracia DESPUÉS</label></div>
                  </div>
              </div>
              <div class="modal-footer mt-3"><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
          </form>
      </div>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) { selectAllCheckbox.addEventListener('change', e => document.querySelectorAll('.record-checkbox').forEach(c => c.checked = e.target.checked)); }
    document.querySelectorAll('.registro-row').forEach(row => {
        const checkCien = row.querySelector('.transporte-cien');
        const checkMitad = row.querySelector('.transporte-mitad');
        const montoCell = row.querySelector('.monto-transporte-cell');
        const montoCompleto = parseFloat(row.dataset.montoCompleto);
        function actualizarMonto() {
            let monto = 0;
            if (checkCien && checkCien.checked) { monto = checkMitad.checked ? montoCompleto * 0.5 : montoCompleto; }
            if(montoCell) montoCell.textContent = '$' + monto.toFixed(2);
        }
        if (checkCien && checkMitad) {
            checkMitad.addEventListener('change', () => { if (checkMitad.checked) { checkCien.checked = true; } actualizarMonto(); });
            checkCien.addEventListener('change', () => { if (!checkCien.checked) { checkMitad.checked = false; } actualizarMonto(); });
        }
        actualizarMonto();
    });
    const editModal = document.getElementById('editModal');
    if(editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('edit-registro-id').value = button.dataset.id;
            document.getElementById('edit-fecha').value = button.dataset.fecha;
            document.getElementById('edit-inicio').value = button.dataset.inicio;
            document.getElementById('edit-fin').value = button.dataset.fin;
            document.getElementById('edit-orden').value = button.dataset.ordenId;
            document.getElementById('edit-zona').value = button.dataset.zonaId;
            document.getElementById('edit-transporte').checked = (button.dataset.transporteAprobado === '1');
            document.getElementById('edit-mitad').checked = (button.dataset.transporteMitad === '1');
            document.getElementById('edit-gracia-antes').checked = (button.dataset.graciaAntes === '1');
            document.getElementById('edit-gracia-despues').checked = (button.dataset.graciaDespues === '1');
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
