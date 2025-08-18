<?php
// ordenes/create.php - v2.1 (Corrección de Consulta de Supervisor)

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

// Cargar datos para los dropdowns
$clientes = $pdo->query("SELECT id, nombre_cliente FROM clientes WHERE estado = 'Activo' ORDER BY nombre_cliente")->fetchAll();
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

<h1 class="mb-4">Crear Nueva Orden de Trabajo</h1>

<div class="card">
    <div class="card-header">
        <h5>Detalles de la Orden</h5>
    </div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="codigo_orden" class="form-label">Código de Orden</label>
                    <input type="text" class="form-control" id="codigo_orden" name="codigo_orden" required>
                </div>
                
                <div class="col-md-4">
                    <label for="numero_orden_compra" class="form-label">Nº Orden de Compra (Opcional)</label>
                    <input type="text" class="form-control" id="numero_orden_compra" name="numero_orden_compra">
                </div>
                
                <div class="col-md-4">
                    <label for="id_cliente" class="form-label">Cliente</label>
                    <select class="form-select" id="id_cliente" name="id_cliente" required>
                        <option value="">Seleccione...</option>
                        <?php foreach($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nombre_cliente']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="id_lugar" class="form-label">Lugar Principal</label>
                    <select class="form-select" id="id_lugar" name="id_lugar" required>
                        <option value="">Seleccione...</option>
                         <?php foreach($lugares as $lugar): ?>
                            <option value="<?php echo $lugar['id']; ?>"><?php echo htmlspecialchars($lugar['nombre_zona_o_muelle']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="id_producto" class="form-label">Producto</label>
                    <select class="form-select" id="id_producto" name="id_producto" required>
                        <option value="">Seleccione...</option>
                        <?php foreach($productos as $producto): ?>
                            <option value="<?php echo $producto['id']; ?>"><?php echo htmlspecialchars($producto['nombre_producto']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="id_operacion" class="form-label">Operación</label>
                    <select class="form-select" id="id_operacion" name="id_operacion" required>
                        <option value="">Seleccione...</option>
                        <?php foreach($operaciones as $operacion): ?>
                            <option value="<?php echo $operacion['id']; ?>"><?php echo htmlspecialchars($operacion['nombre_operacion']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="id_division" class="form-label">División</label>
                    <select class="form-select" id="id_division" name="id_division">
                         <option value="">Seleccione...</option>
                        <?php foreach($divisiones as $division): ?>
                            <option value="<?php echo $division['id']; ?>"><?php echo htmlspecialchars($division['nombre_division']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="id_supervisor" class="form-label">Supervisor Asignado</label>
                    <select class="form-select" id="id_supervisor" name="id_supervisor">
                         <option value="">Seleccione...</option>
                        <?php foreach($supervisores as $supervisor): ?>
                            <option value="<?php echo $supervisor['id']; ?>"><?php echo htmlspecialchars($supervisor['nombres'] . ' ' . $supervisor['primer_apellido']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12">
                    <label for="observaciones" class="form-label">Observaciones (Opcional)</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                </div>

            </div>

            <hr class="my-4">
            <button type="submit" class="btn btn-primary">Crear Orden</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
