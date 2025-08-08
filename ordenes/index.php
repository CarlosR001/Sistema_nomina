<?php
// ordenes/index.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

// Consulta para obtener las órdenes con los nombres de las tablas relacionadas
$stmt = $pdo->query("
    SELECT 
        o.id, 
        o.codigo_orden, 
        c.nombre_cliente, 
        z.nombre_zona_o_muelle as lugar,
        p.nombre_producto,
        op.nombre_operacion,
        o.fecha_creacion,
        o.estado_orden
    FROM ordenes o
    JOIN clientes c ON o.id_cliente = c.id
    JOIN zonastransporte z ON o.id_lugar = z.id
    JOIN productos p ON o.id_producto = p.id
    JOIN operaciones op ON o.id_operacion = op.id
    ORDER BY o.fecha_creacion DESC, o.id DESC
");
$ordenes = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Órdenes de Trabajo</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item active">Órdenes</li>
    </ol>
    
    <div class="mb-4">
        <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Crear Nueva Orden</a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-file-alt me-1"></i>
            Listado de Órdenes
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="datatablesSimple" class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Lugar</th>
                            <th>Producto</th>
                            <th>Operación</th>
                            <th>Fecha Creación</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ordenes)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No hay órdenes registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ordenes as $orden): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($orden['codigo_orden']); ?></td>
                                    <td><?php echo htmlspecialchars($orden['nombre_cliente']); ?></td>
                                    <td><?php echo htmlspecialchars($orden['lugar']); ?></td>
                                    <td><?php echo htmlspecialchars($orden['nombre_producto']); ?></td>
                                    <td><?php echo htmlspecialchars($orden['nombre_operacion']); ?></td>
                                    <td><?php echo date("d/m/Y", strtotime($orden['fecha_creacion'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            switch ($orden['estado_orden']) {
                                                case 'Pendiente': echo 'secondary'; break;
                                                case 'En Proceso': echo 'primary'; break;
                                                case 'Completada': echo 'success'; break;
                                                case 'Cancelada': echo 'danger'; break;
                                                default: echo 'light';
                                            }
                                        ?>"><?php echo htmlspecialchars($orden['estado_orden']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <a href="edit.php?id=<?php echo $orden['id']; ?>" class="btn btn-warning btn-sm" title="Editar">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="assign.php?id=<?php echo $orden['id']; ?>" class="btn btn-info btn-sm" title="Asignar Inspectores">
                                            <i class="bi bi-person-plus"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
