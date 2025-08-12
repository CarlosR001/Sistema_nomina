<?php
// approvals/index.php - v3.0 (Lógica de Transporte Simplificada)
require_once '../auth.php';
require_login();
require_permission('aprobaciones.gestionar');

// --- Lógica de Filtros (sin cambios) ---
$view = $_GET['view'] ?? 'pendientes';
$estado_a_buscar = ($view === 'aprobados') ? 'Aprobado' : 'Pendiente';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$filtro_empleado_id = $_GET['empleado_id'] ?? '';
$filtro_orden_id = $_GET['orden_id'] ?? '';

$empleados_con_registros = $pdo->query("SELECT DISTINCT e.id, e.nombres, e.primer_apellido FROM empleados e JOIN contratos c ON e.id = c.id_empleado JOIN registrohoras r ON c.id = r.id_contrato ORDER BY e.nombres")->fetchAll();
$ordenes_con_registros = $pdo->query("SELECT DISTINCT o.id, o.codigo_orden FROM ordenes o JOIN registrohoras r ON o.id = r.id_orden ORDER BY o.codigo_orden")->fetchAll();

// --- Construcción de la Consulta Dinámica ---
$sql = "SELECT 
            r.id, r.fecha_trabajada, r.hora_inicio, r.hora_fin, 
            r.transporte_aprobado, r.transporte_mitad, -- <-- Nueva columna
            r.hora_gracia_antes, r.hora_gracia_despues,
            e.id as empleado_id, e.nombres, e.primer_apellido, 
            o.codigo_orden, o.id as orden_id, 
            l.nombre_zona_o_muelle, l.monto_transporte_completo,
            u_aprob.nombre_usuario as aprobador, r.fecha_aprobacion
        FROM registrohoras r 
        JOIN contratos c ON r.id_contrato = c.id 
        JOIN empleados e ON c.id_empleado = e.id 
        LEFT JOIN ordenes o ON r.id_orden = o.id 
        LEFT JOIN lugares l ON o.id_lugar = l.id
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
<!-- Formulario de Filtros (sin cambios) -->
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
                        <th>Empleado</th>
                        <th>Fecha y Horario</th>
                        <th>Orden</th>
                        <th>Transporte 100%</th>
                        <th>Transporte 50%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $row): ?>
                        <tr>
                            <td><input class="form-check-input" type="checkbox" name="registros[<?php echo $row['id']; ?>][id]" value="<?php echo $row['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['fecha_trabajada']); ?><br>
                                <span class="text-muted"><?php echo htmlspecialchars(date('H:i', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('H:i', strtotime($row['hora_fin']))); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['codigo_orden'] ?? 'N/A'); ?></td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="registros[<?php echo $row['id']; ?>][transporte]" value="1" <?php echo ($row['transporte_aprobado'] ? 'checked' : ''); ?>>
                                    <label class="form-check-label"><?php echo htmlspecialchars($row['nombre_zona_o_muelle'] ?? 'N/A'); ?></label>
                                </div>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="registros[<?php echo $row['id']; ?>][transporte_mitad]" value="1" <?php echo ($row['transporte_mitad'] ? 'checked' : ''); ?>>
                                    <label class="form-check-label">Pagar Mitad</label>
                                </div>
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
<?php else: // Vista de Aprobados ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark"><tr><th>Empleado</th><th>Fecha</th><th>Horario</th><th>Orden</th><th>Aprobado Por</th><th>Fecha Aprobación</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($registros as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                        <td><?php echo htmlspecialchars($row['fecha_trabajada']); ?></td>
                        <td><?php echo htmlspecialchars(date('H:i', strtotime($row['hora_inicio']))) . " - " . htmlspecialchars(date('H:i', strtotime($row['hora_fin']))); ?></td>
                        <td><?php echo htmlspecialchars($row['codigo_orden'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['aprobador']); ?></td>
                        <td><?php echo htmlspecialchars($row['fecha_aprobacion']); ?></td>
                        <td>
                            <form action="update_status.php" method="POST" onsubmit="return confirm('¿Revertir este registro a Pendiente?');">
                                <input type="hidden" name="registros[<?php echo $row['id']; ?>][id]" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="action" value="Pendiente" class="btn btn-sm btn-outline-secondary" title="Revertir a Pendiente"><i class="bi bi-arrow-counterclockwise"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('selectAll');
    if(selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function(e) {
            document.querySelectorAll('input[name^="registros["]').forEach(c => {
                if(c.type === 'checkbox') c.checked = e.target.checked;
            });
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
