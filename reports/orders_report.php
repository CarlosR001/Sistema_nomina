<?php
// reports/orders_report.php - Interfaz para generar reportes de órdenes

require_once '../auth.php';
require_login();
require_permission('nomina.procesar'); 

// Cargar datos para los dropdowns de los filtros
$clientes = $pdo->query("SELECT id, nombre_cliente FROM clientes WHERE estado = 'Activo' ORDER BY nombre_cliente")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Reporte de Órdenes</h1>

<div class="card">
    <div class="card-header">
        <h5>Seleccione los Parámetros del Reporte</h5>
    </div>
    <div class="card-body">
        <form action="generate_orders_report.php" method="POST" target="_blank">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="id_cliente" class="form-label">Cliente</label>
                    <select class="form-select" id="id_cliente" name="id_cliente">
                        <option value="all">-- Todos los Clientes --</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>">
                                <?php echo htmlspecialchars($cliente['nombre_cliente']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="estado_orden" class="form-label">Estado de la Orden</label>
                    <select class="form-select" id="estado_orden" name="estado_orden">
                        <option value="all">-- Todos los Estados --</option>
                        <option value="Pendiente">Pendiente</option>
                        <option value="En Proceso">En Proceso</option>
                        <option value="Completada">Completada</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="fecha_desde" class="form-label">Desde Fecha Creación</label>
                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde">
                </div>

                <div class="col-md-2">
                    <label for="fecha_hasta" class="form-label">Hasta Fecha Creación</label>
                    <input type="date" class="form-control" id="fecha_hasta">
                </div>

                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Generar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
