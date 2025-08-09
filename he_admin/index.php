<?php
// he_admin/index.php - v3.1 (Corregido y Funcional)

require_once '../auth.php';
require_login();
require_permission('reportes.horas_extras.ver');

$user_id_empleado = $_SESSION['user_id_empleado'] ?? null;
$empleados_para_el_formulario = [];

// Se usa la nueva función has_permission() en lugar de la antigua is_admin()
if (has_permission('*')) {
    // Un Admin (con permiso '*') puede ver a todos los empleados con permiso de horas extras.
    $stmt = $pdo->query("
        SELECT DISTINCT e.id, c.id as id_contrato, e.nombres, e.primer_apellido 
        FROM empleados e
        JOIN contratos c ON e.id = c.id_empleado
        WHERE c.permite_horas_extras = 1 AND c.estado_contrato = 'Vigente'
        ORDER BY e.nombres
    ");
    $empleados_para_el_formulario = $stmt->fetchAll();
} elseif ($user_id_empleado) {
    // Un usuario sin permiso '*' solo se ve a sí mismo.
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.id, c.id as id_contrato, e.nombres, e.primer_apellido 
        FROM empleados e
        JOIN contratos c ON e.id = c.id_empleado
        WHERE c.permite_horas_extras = 1 AND c.estado_contrato = 'Vigente' AND e.id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id_empleado]);
    $empleado_actual = $stmt->fetch();
    
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
                <div class="col-md-<?php echo has_permission('*') ? '4' : '6'; ?>">
                    <label for="id_contrato" class="form-label">Empleado</label>
                    <select class="form-select" id="id_contrato" name="id_contrato" required <?php echo !has_permission('*') ? 'disabled' : ''; ?>>
                        <?php if (has_permission('*')): ?>
                            <option value="">Seleccione un empleado...</option>
                        <?php endif; ?>
                        <?php foreach ($empleados_para_el_formulario as $empleado): ?>
                            <option value="<?php echo $empleado['id_contrato']; ?>"><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></option>
                        <?php endforeach; ?>
                    </select>
                     <?php if (!has_permission('*')): ?>
                        <input type="hidden" name="id_contrato" value="<?php echo $empleados_para_el_formulario[0]['id_contrato']; ?>">
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label for="fecha_trabajada" class="form-label">Fecha</label>
                    <input type="date" class="form-control" id="fecha_trabajada" name="fecha_trabajada" required>
                </div>
                <div class="col-md-2">
                    <label for="hora_inicio" class="form-label">Hora Inicio (0-24)</label>
                    <input type="number" class="form-control" id="hora_inicio" name="hora_inicio" min="0" max="24" required>
                </div>
                <div class="col-md-2">
                    <label for="hora_fin" class="form-label">Hora Fin (0-24)</label>
                    <input type="number" class="form-control" id="hora_fin" name="hora_fin" min="0" max="24" required>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Registrar</button>
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
