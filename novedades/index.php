<?php
// novedades/index.php - v2.0
// Convierte la página en un centro de control con filtros y opciones de edición/eliminación.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

// --- Lógica de Filtros ---
$filtro_contrato_id = $_GET['contrato_id'] ?? '';
$filtro_periodo = $_GET['periodo'] ?? '';

$where_clauses = [];
$params = [];

if (!empty($filtro_contrato_id)) {
    $where_clauses[] = "co.id = :contrato_id";
    $params[':contrato_id'] = $filtro_contrato_id;
}

if (!empty($filtro_periodo)) {
    $where_clauses[] = "n.periodo_aplicacion = :periodo";
    $params[':periodo'] = $filtro_periodo;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : '';

// --- Obtener datos para la página ---
$empleados = $pdo->query("SELECT c.id as id_contrato, e.nombres, e.primer_apellido FROM Contratos c JOIN Empleados e ON c.id_empleado = e.id WHERE c.estado_contrato = 'Vigente' ORDER BY e.nombres")->fetchAll();
$conceptos = $pdo->query("SELECT id, descripcion_publica FROM ConceptosNomina WHERE origen_calculo = 'Novedad' ORDER BY descripcion_publica")->fetchAll();
$periodos_disponibles = $pdo->query("SELECT DISTINCT periodo_aplicacion FROM NovedadesPeriodo ORDER BY periodo_aplicacion DESC")->fetchAll(PDO::FETCH_COLUMN);

$stmt_novedades = $pdo->prepare("SELECT n.id, n.monto_valor, n.periodo_aplicacion, n.estado_novedad, 
                                 e.nombres, e.primer_apellido, c.descripcion_publica
                          FROM NovedadesPeriodo n
                          JOIN Contratos co ON n.id_contrato = co.id
                          JOIN Empleados e ON co.id_empleado = e.id
                          JOIN ConceptosNomina c ON n.id_concepto = c.id
                          $where_sql
                          ORDER BY n.id DESC");
$stmt_novedades->execute($params);
$novedades = $stmt_novedades->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Gestión de Novedades</h1>
    <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseForm" aria-expanded="false" aria-controls="collapseForm">
        <i class="bi bi-plus-lg"></i> Añadir Nueva Novedad
    </button>
</div>

<?php // --- BLOQUE DE MENSAJES CORREGIDO ---
if (isset($_GET['status'])):
    $status_message = htmlspecialchars(urldecode($_GET['message'] ?? 'Operación completada con éxito.'));
    $alert_type = $_GET['status'] === 'success' ? 'alert-success' : 'alert-danger';
?>
    <div class="alert <?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $status_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="collapse mb-4" id="collapseForm">
    <div class="card card-body">
         <form action="store.php" method="POST">
            <!-- Formulario para añadir novedad (mismo que antes) -->
             <div class="row g-3">
                <div class="col-md-6"><label for="id_contrato" class="form-label">Empleado</label><select class="form-select" name="id_contrato" required><option value="">Seleccionar empleado...</option><?php foreach($empleados as $empleado): ?><option value="<?php echo htmlspecialchars($empleado['id_contrato']); ?>"><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label for="id_concepto" class="form-label">Concepto</label><select class="form-select" name="id_concepto" required><option value="">Seleccionar concepto...</option><?php foreach($conceptos as $concepto): ?><option value="<?php echo htmlspecialchars($concepto['id']); ?>"><?php echo htmlspecialchars($concepto['descripcion_publica']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label for="monto_valor" class="form-label">Monto</label><input type="number" step="0.01" class="form-control" name="monto_valor" placeholder="Ej: 5000.00" required></div>
                <div class="col-md-4"><label for="periodo_aplicacion" class="form-label">Fecha de Aplicación</label><input type="date" class="form-control" name="periodo_aplicacion" required></div>
                <div class="col-md-4 d-grid align-content-end"><button type="submit" class="btn btn-primary">Guardar Novedad</button></div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Filtros de Búsqueda</div>
    <div class="card-body">
        <form method="GET" action="index.php">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="contrato_id" class="form-label">Empleado</label>
                    <select name="contrato_id" class="form-select">
                        <option value="">Todos los empleados</option>
                        <?php foreach($empleados as $empleado): ?>
                            <option value="<?php echo htmlspecialchars($empleado['id_contrato']); ?>" <?php echo $filtro_contrato_id == $empleado['id_contrato'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="periodo" class="form-label">Período de Aplicación</label>
                    <select name="periodo" class="form-select">
                        <option value="">Todos los períodos</option>
                        <?php foreach($periodos_disponibles as $p): ?>
                            <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $filtro_periodo == $p ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-info w-100">Filtrar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped mt-4">
    <thead class="table-light">
        <tr>
            <th>Empleado</th>
            <th>Concepto</th>
            <th class="text-end">Monto</th>
            <th>Fecha Aplicación</th>
            <th>Estado</th>
            <th class="text-center">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($novedades)): ?>
            <tr><td colspan="6" class="text-center">No se encontraron novedades con los filtros seleccionados.</td></tr>
        <?php else: ?>
            <?php foreach($novedades as $novedad): ?>
            <tr>
                <td><?php echo htmlspecialchars($novedad['nombres'] . ' ' . $novedad['primer_apellido']); ?></td>
                <td><?php echo htmlspecialchars($novedad['descripcion_publica']); ?></td>
                <td class="text-end">$<?php echo number_format($novedad['monto_valor'], 2); ?></td>
                <td><?php echo htmlspecialchars($novedad['periodo_aplicacion']); ?></td>
                <td><span class="badge bg-<?php echo $novedad['estado_novedad'] == 'Pendiente' ? 'warning text-dark' : 'success'; ?>"><?php echo htmlspecialchars($novedad['estado_novedad']); ?></span></td>
                <td class="text-center">
                    <?php if ($novedad['estado_novedad'] == 'Pendiente'): ?>
                        <a href="edit.php?id=<?php echo $novedad['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta novedad?');">
                            <input type="hidden" name="id" value="<?php echo $novedad['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    <?php else: ?>
                        <span class="text-muted" title="No se puede editar una novedad ya aplicada."><i class="bi bi-lock-fill"></i></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>
