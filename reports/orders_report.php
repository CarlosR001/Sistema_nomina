<?php
// reports/orders_report.php - v2.1 (con Filtro de Orden)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar'); 

// Cargar datos para los dropdowns de los filtros
$clientes = $pdo->query("SELECT id, nombre_cliente FROM clientes WHERE estado = 'Activo' ORDER BY nombre_cliente")->fetchAll();
$ordenes = $pdo->query("SELECT id, codigo_orden FROM ordenes ORDER BY codigo_orden")->fetchAll();

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
                <div class="col-md-3">
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
                    <label for="id_orden" class="form-label">Orden Específica</label>
                    <select class="form-select" id="id_orden" name="id_orden">
                        <option value="all">-- Todas las Órdenes --</option>
                        <?php foreach ($ordenes as $orden): ?>
                            <option value="<?php echo $orden['id']; ?>">
                                <?php echo htmlspecialchars($orden['codigo_orden']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="estado_orden" class="form-label">Estado</label>
                    <select class="form-select" id="estado_orden" name="estado_orden">
                        <option value="all">-- Todos --</option>
                        <option value="Pendiente">Pendiente</option>
                        <option value="En Proceso">En Proceso</option>
                        <option value="Completada">Completada</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="fecha_desde" class="form-label">Desde Fecha</label>
                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde">
                </div>

                <div class="col-md-2">
                    <label for="fecha_hasta" class="form-label">Hasta Fecha</label>
                    <input type="date" class="form-control" id="fecha_hasta">
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Generar Reporte</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
