<?php
// contracts/index.php - v2.2 (con Frecuencia de Deducción)

require_once '../auth.php';
require_login();
require_role('Admin');

if (!isset($_GET['employee_id']) || !is_numeric($_GET['employee_id'])) {
    header('Location: ../employees/index.php?status=error&message=ID de empleado no válido.');
    exit();
}
$employee_id = $_GET['employee_id'];

// Obtener datos del empleado
$stmt_employee = $pdo->prepare("SELECT nombres, primer_apellido FROM Empleados WHERE id = ?");
$stmt_employee->execute([$employee_id]);
$employee = $stmt_employee->fetch();
if (!$employee) {
    header('Location: ../employees/index.php?status=error&message=Empleado no encontrado.');
    exit();
}

// Obtener contratos
$stmt_contracts = $pdo->prepare('SELECT c.id, c.estado_contrato, p.nombre_posicion FROM Contratos c JOIN Posiciones p ON c.id_posicion = p.id WHERE c.id_empleado = ? ORDER BY c.fecha_inicio DESC');
$stmt_contracts->execute([$employee_id]);
$contracts = $stmt_contracts->fetchAll();

// Obtener deducciones recurrentes (con el nuevo campo 'quincena_aplicacion')
$stmt_deductions = $pdo->prepare("
    SELECT dr.id, dr.monto_deduccion, dr.estado, dr.quincena_aplicacion, cn.descripcion_publica, c.id as id_contrato, p.nombre_posicion
    FROM DeduccionesRecurrentes dr
    JOIN ConceptosNomina cn ON dr.id_concepto_deduccion = cn.id
    JOIN Contratos c ON dr.id_contrato = c.id
    JOIN Posiciones p ON c.id_posicion = p.id
    WHERE c.id_empleado = ?
    ORDER BY dr.estado
");
$stmt_deductions->execute([$employee_id]);
$deductions = $stmt_deductions->fetchAll();

// Obtener conceptos de tipo 'Deducción' para el formulario
$conceptos_deduccion = $pdo->query("SELECT id, descripcion_publica FROM ConceptosNomina WHERE tipo_concepto = 'Deducción'")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Gestión de: <?php echo htmlspecialchars($employee['nombres'] . ' ' . $employee['primer_apellido']); ?></h1>

<!-- Sección de Contratos -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>Contratos</h5>
        <a href="create.php?employee_id=<?php echo $employee_id; ?>" class="btn btn-primary">Añadir Nuevo Contrato</a>
    </div>
    <div class="card-body">
        <ul class="list-group">
            <?php foreach ($contracts as $contract): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?php echo htmlspecialchars($contract['nombre_posicion']); ?>
                    <div>
                        <span class="badge bg-<?php echo $contract['estado_contrato'] === 'Vigente' ? 'success' : 'secondary'; ?> me-2"><?php echo htmlspecialchars($contract['estado_contrato']); ?></span>
                        <a href="edit.php?id=<?php echo $contract['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                        <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea eliminar este contrato? Esta acción no se puede deshacer.');">
                            <input type="hidden" name="id" value="<?php echo $contract['id']; ?>">
                            <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<!-- Sección de Deducciones Recurrentes -->
<div class="card">
    <div class="card-header"><h5>Deducciones Recurrentes</h5></div>
    <div class="card-body">
        <!-- Formulario para añadir nueva deducción -->
        <form action="store_deduccion.php" method="POST" class="mb-4 border-bottom pb-4">
            <h6 class="mb-3">Añadir Nueva Deducción</h6>
            <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="id_contrato" class="form-label">Aplicar al Contrato</label>
                    <select class="form-select" name="id_contrato" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($contracts as $contract): if($contract['estado_contrato'] === 'Vigente'): ?>
                            <option value="<?php echo $contract['id']; ?>"><?php echo htmlspecialchars($contract['nombre_posicion']); ?> (Vigente)</option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="id_concepto" class="form-label">Concepto</label>
                    <select class="form-select" name="id_concepto_deduccion" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($conceptos_deduccion as $concepto): ?>
                            <option value="<?php echo $concepto['id']; ?>"><?php echo htmlspecialchars($concepto['descripcion_publica']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="monto" class="form-label">Monto</label>
                    <input type="number" step="0.01" class="form-control" name="monto_deduccion" required>
                </div>
                <div class="col-md-2">
                    <label for="quincena_aplicacion" class="form-label">Frecuencia</label>
                    <select class="form-select" name="quincena_aplicacion" required>
                        <option value="0">Siempre</option>
                        <option value="1">Solo 1ra Quincena</option>
                        <option value="2">Solo 2da Quincena</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">Añadir</button>
                </div>
            </div>
        </form>
        
        <!-- Tabla de deducciones existentes -->
        <h6 class="mt-4">Deducciones Configuradas</h6>
        <table class="table table-sm table-hover">
            <thead><tr><th>Concepto</th><th>Monto</th><th>Frecuencia</th><th>Estado</th><th>Contrato</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($deductions as $deduction): ?>
                    <?php
                        $frecuencia_texto = 'Siempre';
                        if ($deduction['quincena_aplicacion'] == 1) {
                            $frecuencia_texto = '1ra Quincena';
                        } elseif ($deduction['quincena_aplicacion'] == 2) {
                            $frecuencia_texto = '2da Quincena';
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($deduction['descripcion_publica']); ?></td>
                        <td>$<?php echo number_format($deduction['monto_deduccion'], 2); ?></td>
                        <td><span class="badge bg-info text-dark"><?php echo $frecuencia_texto; ?></span></td>
                        <td><span class="badge bg-<?php echo $deduction['estado'] === 'Activa' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($deduction['estado']); ?></span></td>
                        <td><?php echo htmlspecialchars($deduction['nombre_posicion']); ?></td>
                        <td>
                            <form action="toggle_deduccion.php" method="POST" class="d-inline">
                                <input type="hidden" name="id_deduccion" value="<?php echo $deduction['id']; ?>">
                                <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                                <button type="submit" class="btn btn-sm btn-<?php echo $deduction['estado'] === 'Activa' ? 'secondary' : 'success'; ?>">
                                    <?php echo $deduction['estado'] === 'Activa' ? 'Desactivar' : 'Activar'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<a href="<?php echo BASE_URL; ?>employees/index.php" class="btn btn-secondary mt-4">Volver a Empleados</a>

<?php require_once '../includes/footer.php'; ?>
