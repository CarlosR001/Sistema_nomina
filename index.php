<?php
// index.php (Página principal) - v3.1 (Dashboard Inteligente y Robusto)
require_once 'auth.php';
require_login();

// --- Redirección automática para Inspectores ---
if (has_permission('horas.registrar') && !has_permission('nomina.procesar')) {
    header('Location: ' . BASE_URL . 'time_tracking/index.php');
    exit();
}

// --- Dashboard para Roles Administrativos ---

// Cargar estadísticas clave para el dashboard
try {
    $stmt_pending = $pdo->query("SELECT COUNT(id) FROM registrohoras WHERE estado_registro = 'Pendiente'");
    $horas_pendientes = $stmt_pending->fetchColumn();
    
    $stmt_active_emp = $pdo->query("SELECT COUNT(id) FROM empleados WHERE estado_empleado = 'Activo'");
    $empleados_activos = $stmt_active_emp->fetchColumn();

} catch (PDOException $e) {
    $horas_pendientes = 'N/A';
    $empleados_activos = 'N/A';
}

// CORRECCIÓN: Usar el nombre de usuario como fallback si el nombre completo no está en la sesión.
$welcome_name = !empty($_SESSION['user_full_name']) ? $_SESSION['user_full_name'] : $_SESSION['username'];

require_once 'includes/header.php';
?>

<div class="p-5 mb-4 bg-light rounded-3">
    <div class="container-fluid py-5">
        <h1 class="display-5 fw-bold">Bienvenido, <?php echo htmlspecialchars($welcome_name); ?></h1>
        <p class="col-md-8 fs-4">
            Este es el panel de control principal. Desde aquí puede acceder a las funciones clave del sistema.
        </p>
    </div>
</div>

<!-- Fila de Estadísticas -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="card-title"><?php echo $horas_pendientes; ?></h3>
                        <p class="card-text">Horas Pendientes de Aprobación</p>
                    </div>
                    <i class="bi bi-clock-history" style="font-size: 3rem;"></i>
                </div>
                <a href="<?php echo BASE_URL; ?>approvals/index.php" class="text-white stretched-link">Ver detalles</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-white bg-info">
             <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="card-title"><?php echo $empleados_activos; ?></h3>
                        <p class="card-text">Empleados Activos</p>
                    </div>
                    <i class="bi bi-people-fill" style="font-size: 3rem;"></i>
                </div>
                 <a href="<?php echo BASE_URL; ?>employees/index.php" class="text-white stretched-link">Gestionar empleados</a>
            </div>
        </div>
    </div>
</div>


<!-- Fila de Accesos Directos -->
<div class="row row-cols-1 row-cols-md-3 g-4">
    <?php if (has_permission('nomina.procesar')): ?>
    <div class="col">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-calculator-fill me-2"></i>Nómina Semanal</h5>
                <p class="card-text">Genere novedades y procese la nómina para el personal de inspectores.</p>
                <a href="<?php echo BASE_URL; ?>payroll/index.php" class="btn btn-primary">Procesar</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('nomina.procesar')): ?>
    <div class="col">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-building me-2"></i>Nómina Administrativa</h5>
                <p class="card-text">Calcule y gestione la nómina quincenal para el personal fijo.</p>
                <a href="<?php echo BASE_URL; ?>nomina_administrativa/index.php" class="btn btn-secondary">Procesar</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('empleados.gestionar')): ?>
    <div class="col">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-person-lines-fill me-2"></i>Gestión de Empleados</h5>
                <p class="card-text">Añada, vea o modifique la información de los empleados y sus contratos.</p>
                <a href="<?php echo BASE_URL; ?>employees/index.php" class="btn btn-info">Gestionar</a>
            </div>
        </div>
    </div>
     <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>
