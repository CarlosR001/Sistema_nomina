<?php
// reports/orders_selection.php
require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

// Obtener datos para los filtros
$stmt_clientes = $pdo->query("SELECT id, nombre_cliente FROM clientes ORDER BY nombre_cliente");
$clientes = $stmt_clientes->fetchAll();

$stmt_estados = $pdo->query("SELECT DISTINCT estado_orden FROM ordenes ORDER BY estado_orden");
$estados = $stmt_estados->fetchAll(PDO::FETCH_COLUMN);

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">Reporte General de Órdenes</h1>
    <p class="lead">Utilice los filtros para generar un reporte detallado de las órdenes de trabajo.</p>

    <div class="card">
        <div class="card-header">
            Filtros del Reporte
        </div>
        <div class="card-body">
            <form action="generate_full_orders_report.php" method="POST" target="_blank">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="id_cliente" class="form-label">Cliente:</label>
                        <select name="id_cliente" id="id_cliente" class="form-control">
                            <option value="">-- Todos los Clientes --</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nombre_cliente']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="estado_orden" class="form-label">Estado de la Orden:</label>
                        <select name="estado_orden" id="estado_orden" class="form-control">
                            <option value="">-- Todos los Estados --</option>
                             <?php foreach ($estados as $estado): ?>
                                <option value="<?php echo htmlspecialchars($estado); ?>"><?php echo htmlspecialchars($estado); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="fecha_inicio" class="form-label">Fecha de Creación (Desde):</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label for="fecha_fin" class="form-label">Fecha de Creación (Hasta):</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" class="form-control">
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-3 align-items-end">
                     <div class="col-md-6">
                        <label for="formato" class="form-label">Formato de Salida:</label>
                        <select name="formato" id="formato" class="form-control">
                            <option value="html">Ver en Pantalla</option>
                            <option value="excel">Exportar a Excel</option>
                            <option value="print">Imprimir / Guardar como PDF</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-file-earmark-check"></i> Generar Reporte</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
