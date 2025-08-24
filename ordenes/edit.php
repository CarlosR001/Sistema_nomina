<?php
// ordenes/edit.php - v2.2 (Corrección de Consulta de Supervisor)

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

// Iniciar sesión para manejar tokens CSRF y mensajes flash
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $pdo->prepare("SELECT * FROM ordenes WHERE id = ?");
$stmt->execute([$id]);
$orden = $stmt->fetch();

if (!$orden) {
    redirect_with_error('index.php', 'Orden no encontrada.');
    exit;
}

// Cargar datos para los dropdowns
$clientes = $pdo->query("SELECT id, nombre_cliente FROM clientes ORDER BY nombre_cliente")->fetchAll();
$lugares = $pdo->query("SELECT id, nombre_zona_o_muelle FROM lugares WHERE parent_id IS NULL ORDER BY nombre_zona_o_muelle")->fetchAll();
$productos = $pdo->query("SELECT id, nombre_producto FROM productos ORDER BY nombre_producto")->fetchAll();
$operaciones = $pdo->query("SELECT id, nombre_operacion FROM operaciones ORDER BY nombre_operacion")->fetchAll();
$divisiones = $pdo->query("SELECT id, nombre_division FROM divisiones ORDER BY nombre_division")->fetchAll();

// --- CONSULTA CORREGIDA ---
// Ahora busca empleados que tengan el ROL de 'Supervisor'
$supervisores_stmt = $pdo->query("
    SELECT e.id, e.nombres, e.primer_apellido 
    FROM empleados e
    JOIN usuarios u ON e.id = u.id_empleado
    JOIN usuario_rol ur ON u.id = ur.id_usuario
    JOIN roles r ON ur.id_rol = r.id
    WHERE r.nombre_rol = 'Supervisor' AND e.estado_empleado = 'Activo'
    ORDER BY e.nombres, e.primer_apellido
");
$supervisores = $supervisores_stmt->fetchAll();


require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Orden de Trabajo</h1>

<div class="card">
    <div class="card-header">
        <h5>Editando Orden: <?php echo htmlspecialchars($orden['codigo_orden']); ?></h5>
    </div>
    <div class="card-body">
        <form action="update.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="id" value="<?php echo $orden['id']; ?>">
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="codigo_orden" class="form-label">Código de Orden</label>
                    <input type="text" class="form-control" id="codigo_orden" name="codigo_orden" value="<?php echo htmlspecialchars($orden['codigo_orden']); ?>" required>
                </div>
                
                <div class="col-md-4">
                    <label for="numero_orden_compra" class="form-label">Nº Orden de Compra (Opcional)</label>
                    <input type="text" class="form-control" id="numero_orden_compra" name="numero_orden_compra" value="<?php echo htmlspecialchars($orden['numero_orden_compra'] ?? ''); ?>">
                </div>

                <div class="col-md-4">
                    <label for="id_cliente" class="form-label">Cliente</label>
                    <select class="form-select" id="id_cliente" name="id_cliente" required>
                        <?php foreach($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" <?php echo ($orden['id_cliente'] == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['nombre_cliente']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="id_lugar" class="form-label">Lugar Principal</label>
                    <select class="form-select" id="id_lugar" name="id_lugar" required>
                        <?php foreach($lugares as $lugar): ?>
                            <option value="<?php echo $lugar['id']; ?>" <?php echo ($orden['id_lugar'] == $lugar['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lugar['nombre_zona_o_muelle']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="id_producto" class="form-label">Producto</label>
                    <select class="form-select" id="id_producto" name="id_producto" required>
                        <?php foreach($productos as $producto): ?>
                            <option value="<?php echo $producto['id']; ?>" <?php echo ($orden['id_producto'] == $producto['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($producto['nombre_producto']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="id_operacion" class="form-label">Operación</label>
                    <select class="form-select" id="id_operacion" name="id_operacion" required>
                        <?php foreach($operaciones as $operacion): ?>
                            <option value="<?php echo $operacion['id']; ?>" <?php echo ($orden['id_operacion'] == $operacion['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($operacion['nombre_operacion']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="id_division" class="form-label">División</label>
                    <select class="form-select" id="id_division" name="id_division">
                        <option value="">Seleccione...</option>
                        <?php foreach($divisiones as $division): ?>
                            <option value="<?php echo $division['id']; ?>" <?php echo ($orden['id_division'] == $division['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($division['nombre_division']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="id_supervisor" class="form-label">Supervisor Asignado</label>
                    <select class="form-select" id="id_supervisor" name="id_supervisor">
                        <option value="">Seleccione...</option>
                        <?php foreach($supervisores as $supervisor): ?>
                            <option value="<?php echo $supervisor['id']; ?>" <?php echo ($orden['id_supervisor'] == $supervisor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supervisor['nombres'] . ' ' . $supervisor['primer_apellido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="estado_orden" class="form-label">Estado</label>
                    <select class="form-select" id="estado_orden" name="estado_orden" required>
                        <option value="Pendiente" <?php echo ($orden['estado_orden'] == 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="En Proceso" <?php echo ($orden['estado_orden'] == 'En Proceso') ? 'selected' : ''; ?>>En Proceso</option>
                        <option value="Completada" <?php echo ($orden['estado_orden'] == 'Completada') ? 'selected' : ''; ?>>Completada</option>
                        <option value="Cancelada" <?php echo ($orden['estado_orden'] == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <label for="observaciones" class="form-label">Observaciones (Opcional)</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?php echo htmlspecialchars($orden['observaciones'] ?? ''); ?></textarea>
                </div>
            </div>

            <hr class="my-4">
            <button type="submit" class="btn btn-primary">Actualizar Orden</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
