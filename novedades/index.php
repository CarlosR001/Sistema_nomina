<?php
require_once '../config/init.php';

// --- Verificación de Seguridad y Rol ---
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}
// Solo los Administradores o Supervisores pueden registrar novedades.
if (!in_array($_SESSION['rol'], ['Administrador', 'Supervisor'])) {
    die('Acceso Denegado. No tienes los permisos necesarios para acceder a esta página.');
}
// --- Fin de la verificación ---

require_once '../includes/header.php';

// Obtener empleados y conceptos para los dropdowns
$empleados = $pdo->query("SELECT c.id as id_contrato, e.nombres, e.primer_apellido FROM Contratos c JOIN Empleados e ON c.id_empleado = e.id WHERE c.estado_contrato = 'Vigente' ORDER BY e.nombres")->fetchAll();
$conceptos = $pdo->query("SELECT id, descripcion_publica FROM ConceptosNomina WHERE origen_calculo = 'Novedad' ORDER BY descripcion_publica")->fetchAll();

// Obtener novedades recientes para mostrar en la tabla
$novedades = $pdo->query("SELECT n.*, e.nombres, e.primer_apellido, c.descripcion_publica
                          FROM NovedadesPeriodo n
                          JOIN Contratos co ON n.id_contrato = co.id
                          JOIN Empleados e ON co.id_empleado = e.id
                          JOIN ConceptosNomina c ON n.id_concepto = c.id
                          ORDER BY n.id DESC LIMIT 10")->fetchAll();
?>

<h1 class="mb-4">Registro de Novedades</h1>

<?php
// Manejo de mensajes de estado (éxito o error)
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        echo '<div class="alert alert-success">Novedad guardada correctamente.</div>';
    } elseif ($_GET['status'] === 'error') {
        echo '<div class="alert alert-danger">Error al guardar la novedad.</div>';
    }
}
?>

<div class="card mb-4">
    <div class="card-header">Añadir Nueva Novedad (Comisión, Bono, Préstamo, etc.)</div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="id_contrato" class="form-label">Empleado</label>
                    <select class="form-select" name="id_contrato" required>
                        <option value="">Seleccionar empleado...</option>
                        <?php foreach($empleados as $empleado): ?>
                            <option value="<?php echo $empleado['id_contrato']; ?>"><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="id_concepto" class="form-label">Concepto</label>
                    <select class="form-select" name="id_concepto" required>
                        <option value="">Seleccionar concepto...</option>
                         <?php foreach($conceptos as $concepto): ?>
                            <option value="<?php echo $concepto['id']; ?>"><?php echo htmlspecialchars($concepto['descripcion_publica']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="monto_valor" class="form-label">Monto</label>
                    <input type="number" step="0.01" class="form-control" name="monto_valor" placeholder="Ej: 5000.00" required>
                </div>
                <div class="col-md-4">
                    <label for="periodo_aplicacion" class="form-label">Aplicar en Período de (Fecha)</label>
                    <input type="date" class="form-control" name="periodo_aplicacion" required>
                </div>
                <div class="col-md-4 d-grid align-content-end">
                    <button type="submit" class="btn btn-primary">Guardar Novedad</button>
                </div>
            </div>
        </form>
    </div>
</div>

<h3 class="mt-5">Novedades Recientes</h3>
<table class="table table-sm table-striped">
    <thead class="table-light">
        <tr>
            <th>Empleado</th>
            <th>Concepto</th>
            <th class="text-end">Monto</th>
            <th>Fecha de Aplicación</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($novedades as $novedad): ?>
        <tr>
            <td><?php echo htmlspecialchars($novedad['nombres'] . ' ' . $novedad['primer_apellido']); ?></td>
            <td><?php echo htmlspecialchars($novedad['descripcion_publica']); ?></td>
            <td class="text-end">$<?php echo number_format($novedad['monto_valor'], 2); ?></td>
            <td><?php echo htmlspecialchars($novedad['periodo_aplicacion']); ?></td>
            <td>
                <span class="badge bg-<?php echo $novedad['estado_novedad'] == 'Pendiente' ? 'warning text-dark' : 'success'; ?>">
                    <?php echo htmlspecialchars($novedad['estado_novedad']); ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>