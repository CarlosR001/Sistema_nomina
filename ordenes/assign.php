<?php
// ordenes/assign.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

$orden_id = $_GET['id'] ?? null;
if (!$orden_id) {
    header('Location: index.php');
    exit;
}

// Cargar datos de la orden
$stmt_orden = $pdo->prepare("SELECT o.id, o.codigo_orden, c.nombre_cliente FROM ordenes o JOIN clientes c ON o.id_cliente = c.id WHERE o.id = ?");
$stmt_orden->execute([$orden_id]);
$orden = $stmt_orden->fetch();

if (!$orden) {
    header('Location: index.php?status=error&message=' . urlencode('Orden no encontrada.'));
    exit;
}

// Cargar inspectores ya asignados a esta orden
$stmt_asignados = $pdo->prepare("
    SELECT c.id as id_contrato, e.nombres, e.primer_apellido, p.nombre_posicion
    FROM orden_asignaciones oa
    JOIN contratos c ON oa.id_contrato_inspector = c.id
    JOIN empleados e ON c.id_empleado = e.id
    JOIN posiciones p ON c.id_posicion = p.id
    WHERE oa.id_orden = ?
");
$stmt_asignados->execute([$orden_id]);
$inspectores_asignados = $stmt_asignados->fetchAll();
$ids_asignados = array_column($inspectores_asignados, 'id_contrato');

// Cargar todos los inspectores disponibles que no están asignados a esta orden
$sql_disponibles = "SELECT c.id as id_contrato, e.nombres, e.primer_apellido, p.nombre_posicion 
                   FROM contratos c
                   JOIN empleados e ON c.id_empleado = e.id
                   JOIN posiciones p ON c.id_posicion = p.id
                   WHERE c.tipo_nomina = 'Inspectores' AND c.estado_contrato = 'Vigente'";
if (!empty($ids_asignados)) {
    $placeholders = implode(',', array_fill(0, count($ids_asignados), '?'));
    $sql_disponibles .= " AND c.id NOT IN ($placeholders)";
}
$stmt_disponibles = $pdo->prepare($sql_disponibles);
$stmt_disponibles->execute($ids_asignados);
$inspectores_disponibles = $stmt_disponibles->fetchAll();


require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Asignar Inspectores a Orden</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Órdenes</a></li>
        <li class="breadcrumb-item active">Asignar Inspectores</li>
    </ol>

    <div class="alert alert-info">
        <strong>Orden:</strong> <?php echo htmlspecialchars($orden['codigo_orden']); ?> <br>
        <strong>Cliente:</strong> <?php echo htmlspecialchars($orden['nombre_cliente']); ?>
    </div>

    <div class="row">
        <!-- Columna de Inspectores Asignados -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-user-check me-1"></i>Inspectores Asignados</div>
                <div class="card-body">
                    <?php if (empty($inspectores_asignados)): ?>
                        <p class="text-center">No hay inspectores asignados a esta orden.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($inspectores_asignados as $inspector): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($inspector['nombres'] . ' ' . $inspector['primer_apellido']); ?>
                                    <form action="unassign_inspector.php" method="POST" onsubmit="return confirm('¿Quitar a este inspector de la orden?');">
                                        <input type="hidden" name="id_orden" value="<?php echo $orden_id; ?>">
                                        <input type="hidden" name="id_contrato" value="<?php echo $inspector['id_contrato']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Columna de Inspectores Disponibles -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-user-plus me-1"></i>Asignar Nuevo Inspector</div>
                <div class="card-body">
                    <form action="assign_inspector.php" method="POST">
                        <input type="hidden" name="id_orden" value="<?php echo $orden_id; ?>">
                        <div class="input-group">
                            <select name="id_contrato" class="form-select" required>
                                <option value="">Seleccionar inspector...</option>
                                <?php foreach ($inspectores_disponibles as $inspector): ?>
                                    <option value="<?php echo $inspector['id_contrato']; ?>">
                                        <?php echo htmlspecialchars($inspector['nombres'] . ' ' . $inspector['primer_apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Asignar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <a href="index.php" class="btn btn-secondary">Volver al listado de Órdenes</a>
</div>

<?php require_once '../includes/footer.php'; ?>
