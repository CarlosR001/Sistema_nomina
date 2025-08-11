<?php
// ordenes/create.php - v2.2 (CON CÓDIGO DE DIAGNÓSTICO DE ROLES)

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

// --- INICIO: CÓDIGO DE DIAGNÓSTICO ---
// Este bloque mostrará los roles disponibles para ayudarnos a depurar.
try {
    $roles_disponibles = $pdo->query("SELECT id, nombre_rol FROM roles")->fetchAll(PDO::FETCH_ASSOC);
    echo '<div style="background-color: #ffc; border: 1px solid #e6e6e6; padding: 15px; margin: 15px; font-family: monospace;">';
    echo '<strong>Diagnóstico de Roles:</strong><br>';
    echo 'Por favor, copie y pegue esta lista en el chat.<br><br>';
    if ($roles_disponibles) {
        foreach ($roles_disponibles as $rol) {
            echo 'ID: ' . htmlspecialchars($rol['id']) . ', Nombre: ' . htmlspecialchars($rol['nombre_rol']) . '<br>';
        }
    } else {
        echo 'No se encontraron roles en la tabla `roles`.';
    }
    echo '</div>';
} catch (Exception $e) {
    echo '<div style="background-color: #fdd; border: 1px solid red; padding: 15px; margin: 15px;">Error al consultar roles: ' . $e->getMessage() . '</div>';
}
// --- FIN: CÓDIGO DE DIAGNÓSTICO ---


// Cargar datos para los dropdowns
$clientes = $pdo->query("SELECT id, nombre_cliente FROM clientes WHERE estado = 'Activo' ORDER BY nombre_cliente")->fetchAll();
$lugares = $pdo->query("SELECT id, nombre_zona_o_muelle FROM lugares ORDER BY nombre_zona_o_muelle")->fetchAll();
$productos = $pdo->query("SELECT id, nombre_producto FROM productos ORDER BY nombre_producto")->fetchAll();
$operaciones = $pdo->query("SELECT id, nombre_operacion FROM operaciones ORDER BY nombre_operacion")->fetchAll();
$divisiones = $pdo->query("SELECT id, nombre_division FROM divisiones ORDER BY nombre_division")->fetchAll();

// La consulta original que intentamos arreglar
$supervisores = $pdo->query("
    SELECT e.id, e.nombres, e.primer_apellido 
    FROM empleados e 
    JOIN usuarios u ON e.id = u.id_empleado
    JOIN usuario_rol ur ON u.id = ur.id_usuario
    JOIN roles r ON ur.id_rol = r.id
    WHERE LOWER(r.nombre_rol) LIKE '%supervisor%'
    ORDER BY e.nombres
")->fetchAll();


require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Crear Nueva Orden de Trabajo</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Órdenes</a></li>
        <li class="breadcrumb-item active">Crear Orden</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-plus-circle me-1"></i>
            Detalles de la Nueva Orden
        </div>
        <div class="card-body">
            <form action="store.php" method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="codigo_orden" class="form-label">Código de Orden</label>
                        <input type="text" class="form-control" id="codigo_orden" name="codigo_orden" required>
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_creacion" class="form-label">Fecha de Creación</label>
                        <input type="date" class="form-control" id="fecha_creacion" name="fecha_creacion" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="id_cliente" class="form-label">Cliente</label>
                        <select class="form-select" id="id_cliente" name="id_cliente" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nombre_cliente']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="id_lugar" class="form-label">Lugar / Muelle</label>
                        <select class="form-select" id="id_lugar" name="id_lugar" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($lugares as $lugar): ?>
                                <option value="<?php echo $lugar['id']; ?>"><?php echo htmlspecialchars($lugar['nombre_zona_o_muelle']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="id_producto" class="form-label">Producto</label>
                        <select class="form-select" id="id_producto" name="id_producto" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($productos as $producto): ?>
                                <option value="<?php echo $producto['id']; ?>"><?php echo htmlspecialchars($producto['nombre_producto']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="id_operacion" class="form-label">Operación</label>
                        <select class="form-select" id="id_operacion" name="id_operacion" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($operaciones as $operacion): ?>
                                <option value="<?php echo $operacion['id']; ?>"><?php echo htmlspecialchars($operacion['nombre_operacion']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="id_supervisor" class="form-label">Supervisor Asignado</label>
                        <select class="form-select" id="id_supervisor" name="id_supervisor">
                             <option value="">(Lista Vacía)</option>
                            <?php foreach ($supervisores as $supervisor): ?>
                                <option value="<?php echo $supervisor['id']; ?>"><?php echo htmlspecialchars($supervisor['nombres'] . ' ' . $supervisor['primer_apellido']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
           
                    <div class="col-md-4">
                        <label for="id_division" class="form-label">División</label>
                        <select class="form-select" id="id_division" name="id_division" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($divisiones as $division): ?>
                                <option value="<?php echo $division['id']; ?>"><?php echo htmlspecialchars($division['nombre_division']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_finalizacion" class="form-label">Fecha de Finalización (Opcional)</label>
                        <input type="date" class="form-control" id="fecha_finalizacion" name="fecha_finalizacion">
                    </div>
                     <div class="col-md-4">
                        <label for="estado_orden" class="form-label">Estado Inicial</label>
                        <select class="form-select" id="estado_orden" name="estado_orden" required>
                            <option value="Pendiente" selected>Pendiente</option>
                            <option value="En Proceso">En Proceso</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Guardar Orden</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
