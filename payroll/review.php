<?php
// payroll/review.php - v2.0
// Rediseña la página para agrupar las nóminas procesadas por mes y año en un acordeón.

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// Función para obtener el nombre del mes en español
function get_month_name_es($month_number) {
    $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    return $meses[(int)$month_number] ?? 'Mes Desconocido';
}

// 1. Obtener todas las nóminas procesadas, ordenadas por fecha
$stmt_nominas = $pdo->query("
    SELECT 
        id, 
        periodo_inicio, 
        periodo_fin, 
        tipo_nomina_procesada, 
        estado_nomina, 
        fecha_ejecucion 
    FROM nominasprocesadas -- CORRECCIÓN: Nombre de la tabla en minúsculas
    ORDER BY periodo_fin DESC
");
$nominas = $stmt_nominas->fetchAll(PDO::FETCH_ASSOC);


// 2. Agrupar las nóminas por mes y año en un array de PHP
$nominas_agrupadas = [];
foreach ($nominas as $nomina) {
    $fecha = new DateTime($nomina['periodo_fin']);
    $nombre_mes = get_month_name_es($fecha->format('n'));
    $anio = $fecha->format('Y');
    $key = ucfirst($nombre_mes) . ' ' . $anio;
    $nominas_agrupadas[$key][] = $nomina;
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Revisión de Nóminas Procesadas</h1>
</div>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="accordion" id="accordionNominas">
    <?php if (empty($nominas_por_mes)): ?>
        <div class="alert alert-info">Aún no hay nóminas procesadas para revisar.</div>
    <?php else: ?>
        <?php foreach ($nominas_por_mes as $mes_key => $nominas_del_mes): ?>
            <?php
                // Formatear la clave '2025-07' a un formato legible como "Julio de 2025"
                $fecha_mes = new DateTime($mes_key . '-01');
                $nombre_mes = strftime('%B de %Y', $fecha_mes->getTimestamp());
            ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-<?php echo $mes_key; ?>">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $mes_key; ?>" aria-expanded="true" aria-controls="collapse-<?php echo $mes_key; ?>">
                        Nóminas de <?php echo ucfirst($nombre_mes); ?> (<?php echo count($nominas_del_mes); ?> períodos)
                    </button>
                </h2>
                <div id="collapse-<?php echo $mes_key; ?>" class="accordion-collapse collapse show" aria-labelledby="heading-<?php echo $mes_key; ?>" data-bs-parent="#accordionNominas">
                    <div class="accordion-body p-0">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Período</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Fecha Ejecución</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nominas_del_mes as $nomina): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($nomina['id']); ?></td>
                                        <td><?php echo htmlspecialchars($nomina['periodo_inicio']) . ' al ' . htmlspecialchars($nomina['periodo_fin']); ?></td>
                                        <td><?php echo htmlspecialchars($nomina['tipo_nomina_procesada']); ?></td>
                                        <td>
                                            <?php
                                                $estado = htmlspecialchars($nomina['estado_nomina'] ?? 'Desconocido');
                                                $clase_badge = 'bg-secondary'; // Color por defecto
                                                if ($estado === 'Calculada') {
                                                    $clase_badge = 'bg-warning text-dark';
                                                } elseif ($estado === 'Aprobada y Finalizada') {
                                                    $clase_badge = 'bg-success';
                                                }
                                            ?>
                                            <span class="badge <?php echo $clase_badge; ?>">
                                                <?php echo $estado; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($nomina['fecha_ejecucion']); ?></td>
                                        <td class="text-center">
                                            <a href="show.php?id=<?php echo $nomina['id']; ?>" class="btn btn-sm btn-primary">Ver Detalles</a>
                                            <form action="delete_payroll.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está SEGURO de que desea eliminar esta nómina por completo? Esta acción es irreversible.');">
                                                <input type="hidden" name="nomina_id" value="<?php echo $nomina['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger ms-1">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
