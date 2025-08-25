<?php
// logs/index.php
require_once '../auth.php';
require_login();
// Solo los administradores deberían ver el log completo
require_permission('usuarios.gestionar'); 

// Paginación
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

// Obtener el total de registros para la paginación
$total_stmt = $pdo->query("SELECT COUNT(*) FROM logdeactividad");
$total_results = $total_stmt->fetchColumn();
$total_pages = ceil($total_results / $limit);

// Obtener los registros de la página actual
$stmt = $pdo->prepare("
    SELECT 
        l.id,
        l.fecha_hora,
        l.accion_realizada,
        l.tabla_afectada,
        l.id_registro_afectado,
        l.detalle,
        u.nombre_usuario,
        e.nombres,
        e.primer_apellido
    FROM logdeactividad l
    JOIN usuarios u ON l.id_usuario = u.id
    LEFT JOIN empleados e ON u.id_empleado = e.id
    ORDER BY l.fecha_hora DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">Log de Actividad del Sistema</h1>

    <div class="card">
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Fecha y Hora</th>
                        <th>Usuario</th>
                        <th>Acción Realizada</th>
                        <th>Detalles</th>
                        <th>Recurso Afectado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" class="text-center">No hay actividad registrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['fecha_hora'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($log['nombres'] . ' ' . $log['primer_apellido']); ?>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($log['nombre_usuario']); ?></small>
                                </td>
                                <td><strong><?php echo htmlspecialchars($log['accion_realizada']); ?></strong></td>
                                <td><?php echo htmlspecialchars($log['detalle'] ?? ''); ?></td>
                                <td>
                                    <?php if ($log['tabla_afectada']): ?>
                                        <?php echo htmlspecialchars(ucfirst($log['tabla_afectada'])); ?>
                                        <?php if ($log['id_registro_afectado']): ?>
                                            (ID: <?php echo htmlspecialchars($log['id_registro_afectado']); ?>)
                                        <?php endif; ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
