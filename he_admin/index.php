<?php
// he_admin/index.php - v2.0 (Corregido con lógica de roles)

require_once '../auth.php';
require_login();
require_role(['Admin', 'ReporteHorasExtras']);

$user_rol = $_SESSION['user_rol'];
$user_id_empleado = $_SESSION['user_id_empleado'] ?? null;

$empleados_para_el_formulario = [];
$empleado_actual_nombre = '';

if ($user_rol === 'Admin') {
    $stmt = $pdo->query("
        SELECT c.id as id_contrato, e.nombres, e.primer_apellido 
        FROM Contratos c 
        JOIN Empleados e ON c.id_empleado = e.id 
        WHERE c.permite_horas_extras = 1 AND c.estado_contrato = 'Vigente'
        ORDER BY e.nombres
    ");
    $empleados_para_el_formulario = $stmt->fetchAll();
} else { // Rol: ReporteHorasExtras
    if (!$user_id_empleado) {
        die('Error: Su cuenta de usuario no está vinculada a un registro de empleado.');
    }
    $stmt = $pdo->prepare("
        SELECT c.id as id_contrato, e.nombres, e.primer_apellido 
        FROM Contratos c 
        JOIN Empleados e ON c.id_empleado = e.id 
        WHERE c.permite_horas_extras = 1 AND c.estado_contrato = 'Vigente' AND e.id = ?
    ");
    $stmt->execute([$user_id_empleado]);
    $empleado_actual = $stmt->fetch();
    
    if(!$empleado_actual){
        // Usamos die() porque el header.php ya se cargó
        die('<div class="container mt-4"><div class="alert alert-danger">Acceso denegado. Su contrato actual no tiene habilitado el permiso para registrar horas extras.</div></div>');
    }
    $empleados_para_el_formulario[] = $empleado_actual;
    $empleado_actual_nombre = $empleado_actual['nombres'] . ' ' . $empleado_actual['primer_apellido'];
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Registro de Horas Extras (Personal Fijo)</h1>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars(urldecode($_GET['message'])); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Registrar Nuevo Parte de Horas</div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row g-3">
                <?php if ($user_rol === 'Admin'): ?>
                    <div class="col-md-12">
                        <label for="id_contrato" class="form-label">Empleado</label>
                        <select class="form-select" id="id_contrato" name="id_contrato" required>
                            <option value="">Seleccione un empleado...</option>
                            <?php foreach ($empleados_para_el_formulario as $empleado): ?>
                                <option value="<?php echo $empleado['id_contrato']; ?>"><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: // Para ReporteHorasExtras, el contrato es fijo ?>
                    <input type="hidden" name="id_contrato" value="<?php echo $empleados_para_el_formulario[0]['id_contrato']; ?>">
                    <div class="col-md-12">
                        <div class="alert alert-info">Registrando horas para: <strong><?php echo htmlspecialchars($empleado_actual_nombre); ?></strong></div>
                    </div>
                <?php endif; ?>

                <div class="col-md-4">
                    <label for="fecha_trabajada" class="form-label">Fecha</label>
                    <input type="date" class="form-control" name="fecha_trabajada" required>
                </div>
                <div class="col-md-4">
                    <label for="hora_inicio" class="form-label">Hora Real de Inicio</label>
                    <input type="time" class="form-control" name="hora_inicio" required>
                </div>
                <div class="col-md-4">
                    <label for="hora_fin" class="form-label">Hora Real de Salida</label>
                    <input type="time" class="form-control" name="hora_fin" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Calcular y Registrar Horas Extras</button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
