<?php
// ordenes/edit.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

$orden_id = $_GET['id'] ?? null;
if (!$orden_id) {
    header('Location: index.php');
    exit;
}

// Cargar datos de la orden
$stmt = $pdo->prepare("SELECT * FROM ordenes WHERE id = ?");
$stmt->execute([$orden_id]);
$orden = $stmt->fetch();

if (!$orden) {
    header('Location: index.php?status=error&message=' . urlencode('Orden no encontrada.'));
    exit;
}

// Cargar datos para los dropdowns
$clientes = $pdo->query("SELECT id, nombre_cliente FROM clientes WHERE estado = 'Activo' ORDER BY nombre_cliente")->fetchAll();
$lugares = $pdo->query("SELECT id, nombre_zona_o_muelle FROM lugares ORDER BY nombre_zona_o_muelle")->fetchAll();
$productos = $pdo->query("SELECT id, nombre_producto FROM productos ORDER BY nombre_producto")->fetchAll();
$operaciones = $pdo->query("SELECT id, nombre_operacion FROM operaciones ORDER BY nombre_operacion")->fetchAll();
$divisiones = $pdo->query("SELECT id, nombre_division FROM divisiones ORDER BY nombre_division")->fetchAll();
// AÑADIDO: Cargar lista de supervisores
$supervisores = $pdo->query("SELECT e.id, e.nombres, e.primer_apellido FROM empleados e JOIN usuarios u ON e.id = u.id_empleado WHERE u.rol = 'Supervisor' ORDER BY e.nombres")->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Editar Orden de Trabajo</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Órdenes</a></li>
        <li class="breadcrumb-item active">Editar Orden</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-edit me-1"></i>Detalles de la Orden</div>
        <div class="card-body">
            <form action="update.php" method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($orden['id']); ?>">
                <div class="row g-3">
                    <div class="col-md-4"><label for="codigo_orden" class="form-label">Código de Orden</label><input type="text" class="form-control" id="codigo_orden" name="codigo_orden" value="<?php echo htmlspecialchars($orden['codigo_orden']); ?>" required></div>
                    <div class="col-md-4"><label for="fecha_creacion" class="form-label">Fecha de Creación</label><input type="date" class="form-control" id="fecha_creacion" name="fecha_creacion" value="<?php echo htmlspecialchars($orden['fecha_creacion']); ?>" required></div>
                    <div class="col-md-4"><label for="id_cliente" class="form-label">Cliente</label><select class="form-select" id="id_cliente" name="id_cliente" required>
                        <?php foreach ($clientes as $cliente): ?><option value="<?php echo $cliente['id']; ?>" <?php echo ($orden['id_cliente'] == $cliente['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cliente['nombre_cliente']); ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="col-md-4"><label for="id_lugar" class="form-label">Lugar / Muelle</label><select class="form-select" id="id_lugar" name="id_lugar" required>
                        <?php foreach ($lugares as $lugar): ?><option value="<?php echo $lugar['id']; ?>" <?php echo ($orden['id_lugar'] == $lugar['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lugar['nombre_zona_o_muelle']); ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="col-md-4"><label for="id_producto" class="form-label">Producto</label><select class="form-select" id="id_producto" name="id_producto" required>
                        <?php foreach ($productos as $producto): ?><option value="<?php echo $producto['id']; ?>" <?php echo ($orden['id_producto'] == $producto['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($producto['nombre_producto']); ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="col-md-4"><label for="id_operacion" class="form-label">Operación</label><select class="form-select" id="id_operacion" name="id_operacion" required>
                        <?php foreach ($operaciones as $operacion): ?><option value="<?php echo $operacion['id']; ?>" <?php echo ($orden['id_operacion'] == $operacion['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($operacion['nombre_operacion']); ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="col-md-4"><label for="id_supervisor" class="form-label">Supervisor Asignado</label>
    <select class="form-select" id="id_supervisor" name="id_supervisor" required>
        <?php foreach ($supervisores as $supervisor): ?>
            <option value="<?php echo $supervisor['id']; ?>" <?php echo ($orden['id_supervisor'] == $supervisor['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($supervisor['nombres'] . ' ' . $supervisor['primer_apellido']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

                    <div class="col-md-4"><label for="id_division" class="form-label">División</label>
    <select class="form-select" id="id_division" name="id_division" required>
        <?php foreach ($divisiones as $division): ?>
            <option value="<?php echo $division['id']; ?>" <?php echo ($orden['id_division'] == $division['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($division['nombre_division']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-4"><label for="fecha_finalizacion" class="form-label">Fecha de Finalización (Opcional)</label>
    <input type="date" class="form-control" id="fecha_finalizacion" name="fecha_finalizacion" value="<?php echo htmlspecialchars($orden['fecha_finalizacion']); ?>">
</div>
<div class="col-md-4"><label for="estado_orden" class="form-label">Estado</label>
    <select class="form-select" id="estado_orden" name="estado_orden" required>
        <option value="Pendiente" <?php echo ($orden['estado_orden'] == 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
        <option value="En Proceso" <?php echo ($orden['estado_orden'] == 'En Proceso') ? 'selected' : ''; ?>>En Proceso</option>
        <option value="Completada" <?php echo ($orden['estado_orden'] == 'Completada') ? 'selected' : ''; ?>>Completada</option>
        <option value="Cancelada" <?php echo ($orden['estado_orden'] == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>
    </select>
</div>

                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Actualizar Orden</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/header.php'; ?>
