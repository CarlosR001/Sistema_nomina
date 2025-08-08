<?php
// he_admin/index.php - v3.1 (Corregido y Funcional)

require_once '../auth.php';
require_login();
require_permission('reportes.horas_extras.ver');

$user_id_empleado = $_SESSION['user_id_empleado'] ?? null;
$empleados_para_el_formulario = [];

// Se usa la nueva función is_admin() que se basa en permisos.
if (is_admin()) {
    // El Admin puede ver a todos los empleados con permiso de horas extras.
    $stmt = $pdo->query("
        SELECT DISTINCT e.id, c.id as id_contrato, e.nombres, e.primer_apellido 
        FROM empleados e
        JOIN contratos c ON e.id = c.id_empleado
        WHERE c.permite_horas_extras = 1 AND c.estado_contrato = 'Vigente'
        ORDER BY e.nombres
    ");
    $empleados_para_el_formulario = $stmt->fetchAll();
} elseif ($user_id_empleado) {
    // Un usuario con el rol 'ReporteHorasExtras' solo se ve a sí mismo.
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.id, c.id as id_contrato, e.nombres, e.primer_apellido 
        FROM empleados e
        JOIN contratos c ON e.id = c.id_empleado
        WHERE c.permite_horas_extras = 1 AND c.estado_contrato = 'Vigente' AND e.id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id_empleado]);
    $empleado_actual = $stmt->fetch();
    
    // Si se encontró al empleado y tiene permiso de HE, se añade a la lista
    if ($empleado_actual) {
        $empleados_para_el_formulario[] = $empleado_actual;
    }
}

// Lógica para procesar el formulario si se envía (sin cambios)
$resultados = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_contrato_seleccionado = $_POST['id_contrato'] ?? null;
    $fecha_inicio = $_POST['fecha_inicio'] ?? null;
    $fecha_fin = $_POST['fecha_fin'] ?? null;
    
    // Aquí iría la lógica para consultar y mostrar el reporte de horas extra...
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <h1 class="mb-4">Registro de Horas Extras (Personal Fijo)</h1>
    
    <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars(urldecode($_GET['message'])); ?></div>
    <?php endif; ?>

    <?php if (empty($empleados_para_el_formulario)): ?>
        <div class="alert alert-danger">
            Acceso denegado. No hay empleados con permiso de Horas Extras disponibles para su usuario.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">Registrar Nuevo Parte de Horas</div>
            <div class="card-body">
                <form action="store.php" method="POST">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-<?php echo is_admin() ? '6' : '12'; ?>">
                            <label for="id_contrato" class="form-label">Empleado</label>
                            <select class="form-select" id="id_contrato" name="id_contrato" required <?php echo !is_admin() ? 'disabled' : ''; ?>>
                                <?php if (is_admin()): ?>
                                    <option value="">Seleccione un empleado...</option>
                                <?php endif; ?>
                                <?php foreach ($empleados_para_el_formulario as $empleado): ?>
                                    <option value="<?php echo $empleado['id_contrato']; ?>"><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></option>
                                <?php endforeach; ?>
                            </select>
                             <?php if (!is_admin()): ?>
                                <!-- Campo oculto para que el usuario no-admin envíe su propio ID de contrato -->
                                <input type="hidden" name="id_contrato" value="<?php echo $empleados_para_el_formulario[0]['id_contrato']; ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label for="fecha" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="fecha" name="fecha" required>
                        </div>
                        <div class="col-md-3">
                            <label for="cantidad_horas" class="form-label">Cantidad de Horas Extras</label>
                            <input type="number" step="0.01" class="form-control" id="cantidad_horas" name="cantidad_horas" required>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Registrar Horas</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Aquí se mostrarían los resultados del reporte -->
    <div class="mt-4" id="resultados_reporte">
        
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
