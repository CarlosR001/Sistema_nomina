<?php
// he_admin/index.php - v3.2 (Reporte Funcional)

require_once '../auth.php';
require_login();
require_permission('reportes.horas_extras.ver');

$user_id_empleado = $_SESSION['user_id_empleado'] ?? null;
$empleados_para_el_formulario = [];
$is_admin = has_permission('*');

if ($is_admin) {
    $stmt = $pdo->query("
        SELECT DISTINCT e.id, c.id as id_contrato, e.nombres, e.primer_apellido 
        FROM empleados e JOIN contratos c ON e.id = c.id_empleado
        WHERE c.permite_horas_extras = 1 AND c.estado_contrato = 'Vigente' ORDER BY e.nombres
    ");
    $empleados_para_el_formulario = $stmt->fetchAll();
} elseif ($user_id_empleado) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.id, c.id as id_contrato, e.nombres, e.primer_apellido 
        FROM empleados e JOIN contratos c ON e.id = c.id_empleado
        WHERE c.permite_horas_extras = 1 AND c.estado_contrato = 'Vigente' AND e.id = ? LIMIT 1
    ");
    $stmt->execute([$user_id_empleado]);
    $empleado_actual = $stmt->fetch();
    if ($empleado_actual) { $empleados_para_el_formulario[] = $empleado_actual; }
}

// --- LÓGICA DEL REPORTE ---
$resultados = [];
$id_contrato_seleccionado = null;
$fecha_inicio = null;
$fecha_fin = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'filter') {
    $id_contrato_seleccionado = $_GET['id_contrato_filtro'] ?? null;
    $fecha_inicio = $_GET['fecha_inicio'] ?? null;
    $fecha_fin = $_GET['fecha_fin'] ?? null;
    
    $sql = "
        SELECT e.nombres, e.primer_apellido, np.monto_valor, np.periodo_aplicacion, np.estado_novedad
        FROM novedadesperiodo np
        JOIN conceptosnomina cn ON np.id_concepto = cn.id
        JOIN contratos c ON np.id_contrato = c.id
        JOIN empleados e ON c.id_empleado = e.id
        WHERE cn.codigo_concepto = 'ING-HE-ADMIN'
    ";
    $params = [];
    if ($id_contrato_seleccionado) {
        $sql .= " AND np.id_contrato = ?";
        $params[] = $id_contrato_seleccionado;
    }
    if ($fecha_inicio) {
        $sql .= " AND np.periodo_aplicacion >= ?";
        $params[] = $fecha_inicio;
    }
    if ($fecha_fin) {
        $sql .= " AND np.periodo_aplicacion <= ?";
        $params[] = $fecha_fin;
    }
    $sql .= " ORDER BY np.periodo_aplicacion DESC";
    
    $stmt_reporte = $pdo->prepare($sql);
    $stmt_reporte->execute($params);
    $resultados = $stmt_reporte->fetchAll();
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <h1 class="mb-4">Horas Extras (Personal Fijo)</h1>
    
    <?php if (empty($empleados_para_el_formulario)): ?>
        <div class="alert alert-danger">No hay empleados con permiso de Horas Extras disponibles para su usuario.</div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-header">Registrar Nuevo Parte de Horas</div>
            <div class="card-body">
                <form action="store.php" method="POST">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-<?php echo $is_admin ? '4' : '6'; ?>">
                            <label for="id_contrato_registro" class="form-label">Empleado</label>
                            <select class="form-select" id="id_contrato_registro" name="id_contrato" required <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                <?php if ($is_admin): ?><option value="">Seleccione...</option><?php endif; ?>
                                <?php foreach ($empleados_para_el_formulario as $empleado): ?>
                                    <option value="<?php echo $empleado['id_contrato']; ?>"><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></option>
                                <?php endforeach; ?>
                            </select>
                             <?php if (!$is_admin): ?><input type="hidden" name="id_contrato" value="<?php echo $empleados_para_el_formulario[0]['id_contrato']; ?>"><?php endif; ?>
                        </div>
                        <div class="col-md-2"><label for="fecha_trabajada" class="form-label">Fecha</label><input type="date" class="form-control" name="fecha_trabajada" required></div>
                        <div class="col-md-2"><label for="hora_inicio" class="form-label">Hora Inicio (0-24)</label><input type="number" class="form-control" name="hora_inicio" min="0" max="24" required></div>
                        <div class="col-md-2"><label for="hora_fin" class="form-label">Hora Fin (0-24)</label><input type="number" class="form-control" name="hora_fin" min="0" max="24" required></div>
                        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Registrar</button></div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- SECCIÓN DE REPORTE AÑADIDA -->
    <div class="card mt-4">
        <div class="card-header">Consultar Horas Extras Registradas</div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="action" value="filter">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="id_contrato_filtro" class="form-label">Empleado</label>
                        <select class="form-select" name="id_contrato_filtro" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                            <?php if ($is_admin): ?><option value="">Todos</option><?php endif; ?>
                            <?php foreach ($empleados_para_el_formulario as $empleado): ?>
                                <option value="<?php echo $empleado['id_contrato']; ?>" <?php echo ($id_contrato_seleccionado == $empleado['id_contrato']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                         <?php if (!$is_admin): ?><input type="hidden" name="id_contrato_filtro" value="<?php echo $empleados_para_el_formulario[0]['id_contrato']; ?>"><?php endif; ?>
                    </div>
                    <div class="col-md-3"><label for="fecha_inicio" class="form-label">Desde</label><input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio ?? ''); ?>"></div>
                    <div class="col-md-3"><label for="fecha_fin" class="form-label">Hasta</label><input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin ?? ''); ?>"></div>
                    <div class="col-md-1"><button type="submit" class="btn btn-info w-100">Ver</button></div>
                </div>
            </form>
            
            <?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])): ?>
            <div class="table-responsive mt-4">
                <table class="table table-bordered table-hover">
                    <thead class="table-light"><tr><th>Empleado</th><th>Fecha Aplicación</th><th class="text-end">Monto Pagado</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php if (empty($resultados)): ?>
                            <tr><td colspan="4" class="text-center">No se encontraron registros con los filtros seleccionados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($resultados as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nombres'] . ' ' . $row['primer_apellido']); ?></td>
                                <td><?php echo htmlspecialchars($row['periodo_aplicacion']); ?></td>
                                <td class="text-end">$<?php echo number_format($row['monto_valor'], 2); ?></td>
                                <td><span class="badge bg-<?php echo $row['estado_novedad'] == 'Pendiente' ? 'warning text-dark' : 'success'; ?>"><?php echo htmlspecialchars($row['estado_novedad']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
