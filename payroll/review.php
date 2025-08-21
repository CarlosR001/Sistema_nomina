<?php
// payroll/review.php - v2.3 (Muestra Tipo de Cálculo)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

function get_month_name_es($month_number) {
    $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    return $meses[(int)$month_number] ?? 'Mes Desconocido';
}

$stmt_nominas = $pdo->query("
    SELECT 
        id, 
        periodo_inicio, 
        periodo_fin, 
        tipo_nomina_procesada,
        tipo_calculo_nomina, -- Columna añadida
        estado_nomina, 
        fecha_ejecucion 
    FROM nominasprocesadas
    WHERE estado_nomina IN ('Aprobada y Finalizada', 'Pagada', 'Pendiente de Aprobación', 'Calculada')
    ORDER BY periodo_fin DESC
");
$nominas = $stmt_nominas->fetchAll(PDO::FETCH_ASSOC);

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

<div class="container-fluid px-4">
    <h1 class="mt-4">Revisión de Nóminas Procesadas</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item active">Revisión de Nóminas</li>
    </ol>

    <div class="accordion" id="accordionNominas">
        <?php if (empty($nominas_agrupadas)): ?>
            <div class="alert alert-info">Aún no hay nóminas procesadas para revisar.</div>
        <?php else: ?>
            <?php $index = 0; ?>
            <?php foreach ($nominas_agrupadas as $mes_anio => $nominas_del_mes): ?>
                <?php $collapse_id = "collapse-" . $index; ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-<?php echo $index; ?>">
                        <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapse_id; ?>">
                            Nóminas de <?php echo htmlspecialchars($mes_anio); ?> (<?php echo count($nominas_del_mes); ?> períodos)
                        </button>
                    </h2>
                    <div id="<?php echo $collapse_id; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading-<?php echo $index; ?>" data-bs-parent="#accordionNominas">
                        <div class="accordion-body p-0">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Tipo de Nómina</th>
                                        <th>Tipo de Cálculo</th>
                                        <th>Período</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nominas_del_mes as $nomina): ?>
                                    <tr>
                                        <td><?php echo $nomina['id']; ?></td>
                                        <td><?php echo htmlspecialchars($nomina['tipo_nomina_procesada']); ?></td>
                                        <td>
                                            <span class="badge bg-info text-dark"><?php echo htmlspecialchars($nomina['tipo_calculo_nomina']); ?></span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($nomina['periodo_inicio'])) . ' - ' . date('d/m/Y', strtotime($nomina['periodo_fin'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch ($nomina['estado_nomina']) {
                                                    case 'Aprobada y Finalizada': case 'Pagada': echo 'success'; break;
                                                    case 'Pendiente de Aprobación': case 'Calculada': echo 'warning text-dark'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>"><?php echo htmlspecialchars($nomina['estado_nomina']); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo BASE_URL; ?>payroll/show.php?id=<?php echo $nomina['id']; ?>" class="btn btn-primary btn-sm">Ver Detalles</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php $index++; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
