<?php
// ordenes/index.php - v3.0 (Filtros y Menú de Acciones)

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

// --- 1. Lógica de Filtros ---
$filtro_cliente = $_GET['id_cliente'] ?? '';
$filtro_lugar = $_GET['id_lugar'] ?? '';
$filtro_supervisor = $_GET['id_supervisor'] ?? '';
$filtro_estado = $_GET['estado_orden'] ?? '';

// --- 2. Carga de Datos para los Dropdowns de Filtro ---
$clientes = $pdo->query("SELECT id, nombre_cliente FROM clientes ORDER BY nombre_cliente")->fetchAll();
$lugares = $pdo->query("SELECT id, nombre_zona_o_muelle FROM lugares WHERE parent_id IS NULL ORDER BY nombre_zona_o_muelle")->fetchAll();
$supervisores = $pdo->query("SELECT e.id, e.nombres, e.primer_apellido FROM empleados e JOIN usuarios u ON e.id = u.id_empleado JOIN usuario_rol ur ON u.id = ur.id_usuario JOIN roles r ON ur.id_rol = r.id WHERE r.nombre_rol = 'Supervisor' ORDER BY e.nombres")->fetchAll();

// --- 3. Construcción de la Consulta Dinámica ---
$sql = "
    SELECT 
        o.id, o.codigo_orden, o.numero_orden_compra,
        c.nombre_cliente, l.nombre_zona_o_muelle, 
        p.nombre_producto, op.nombre_operacion,
        CONCAT(sup.nombres, ' ', sup.primer_apellido) as supervisor_nombre,
        o.estado_orden, o.observaciones
    FROM ordenes o
    JOIN clientes c ON o.id_cliente = c.id
    JOIN lugares l ON o.id_lugar = l.id
    LEFT JOIN productos p ON o.id_producto = p.id
    LEFT JOIN operaciones op ON o.id_operacion = op.id
    LEFT JOIN empleados sup ON o.id_supervisor = sup.id
";
$where_clauses = [];
$params = [];

if (!empty($filtro_cliente)) { $where_clauses[] = "o.id_cliente = ?"; $params[] = $filtro_cliente; }
if (!empty($filtro_lugar)) { $where_clauses[] = "o.id_lugar = ?"; $params[] = $filtro_lugar; }
if (!empty($filtro_supervisor)) { $where_clauses[] = "o.id_supervisor = ?"; $params[] = $filtro_supervisor; }
if (!empty($filtro_estado)) { $where_clauses[] = "o.estado_orden = ?"; $params[] = $filtro_estado; }

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY o.fecha_creacion DESC, o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Gestión de Órdenes de Trabajo</h1>
    <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nueva Orden</a>
</div>

<!-- PANEL DE FILTROS AÑADIDO -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-filter"></i> Filtrar Órdenes</div>
    <div class="card-body">
        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label">Cliente</label><select name="id_cliente" class="form-select"><option value="">Todos</option><?php foreach($clientes as $c):?><option value="<?php echo $c['id'];?>" <?php echo $filtro_cliente == $c['id'] ? 'selected' : '';?>><?php echo htmlspecialchars($c['nombre_cliente']);?></option><?php endforeach;?></select></div>
                <div class="col-md-3"><label class="form-label">Lugar</label><select name="id_lugar" class="form-select"><option value="">Todos</option><?php foreach($lugares as $l):?><option value="<?php echo $l['id'];?>" <?php echo $filtro_lugar == $l['id'] ? 'selected' : '';?>><?php echo htmlspecialchars($l['nombre_zona_o_muelle']);?></option><?php endforeach;?></select></div>
                <div class="col-md-2"><label class="form-label">Supervisor</label><select name="id_supervisor" class="form-select"><option value="">Todos</option><?php foreach($supervisores as $s):?><option value="<?php echo $s['id'];?>" <?php echo $filtro_supervisor == $s['id'] ? 'selected' : '';?>><?php echo htmlspecialchars($s['nombres'].' '.$s['primer_apellido']);?></option><?php endforeach;?></select></div>
                <div class="col-md-2"><label class="form-label">Estado</label><select name="estado_orden" class="form-select"><option value="">Todos</option><option value="Pendiente" <?php echo $filtro_estado=='Pendiente'?'selected':'';?>>Pendiente</option><option value="En Proceso" <?php echo $filtro_estado=='En Proceso'?'selected':'';?>>En Proceso</option><option value="Completada" <?php echo $filtro_estado=='Completada'?'selected':'';?>>Completada</option><option value="Cancelada" <?php echo $filtro_estado=='Cancelada'?'selected':'';?>>Cancelada</option></select></div>
                <div class="col-md-2 text-end"><button type="submit" class="btn btn-primary">Filtrar</button><a href="index.php" class="btn btn-secondary ms-2">Limpiar</a></div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>Código Orden</th><th>Nº Compra</th><th>Cliente</th><th>Lugar</th><th>Supervisor</th><th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ordenes)): ?>
                        <tr><td colspan="7" class="text-center">No hay órdenes que coincidan con los filtros.</td></tr>
                    <?php else: ?>
                        <?php foreach($ordenes as $orden): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($orden['codigo_orden']); ?></strong></td>
                                <td><?php echo htmlspecialchars($orden['numero_orden_compra'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($orden['nombre_cliente']); ?></td>
                                <td><?php echo htmlspecialchars($orden['nombre_zona_o_muelle']); ?></td>
                                <td><?php echo htmlspecialchars($orden['supervisor_nombre'] ?? 'No asignado'); ?></td>
                                <td>
                                    <?php
                                        $estado = htmlspecialchars($orden['estado_orden']);
                                        $clase_badge = 'bg-secondary';
                                        if ($estado == 'En Proceso') $clase_badge = 'bg-primary';
                                        if ($estado == 'Completada') $clase_badge = 'bg-success';
                                        if ($estado == 'Cancelada') $clase_badge = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $clase_badge; ?>"><?php echo $estado; ?></span>
                                </td>
                                <!-- MENÚ DE ACCIONES MEJORADO -->
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton-<?php echo $orden['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            Acciones
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton-<?php echo $orden['id']; ?>">
                                            <li><a class="dropdown-item" href="edit.php?id=<?php echo $orden['id']; ?>"><i class="bi bi-pencil-fill me-2"></i>Editar</a></li>
                                            <li><a class="dropdown-item" href="assign.php?id=<?php echo $orden['id']; ?>"><i class="bi bi-person-plus-fill me-2"></i>Asignar Inspectores</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="delete.php" method="POST" onsubmit="return confirm('¿Está seguro? Esta acción es irreversible.');" class="d-inline">
                                                    <input type="hidden" name="id" value="<?php echo $orden['id']; ?>">
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash-fill me-2"></i>Eliminar</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
