<?php
// index.php (Página principal) - v2.1
// Se elimina la lógica de redirección. Su única función es ser el dashboard para roles de gestión.

require_once 'auth.php';
require_login(); 
 
// Ya no es necesario comprobar el rol aquí, porque el login en auth.php ya habría redirigido a los inspectores.
// Si llegamos a este punto, es porque el rol es Admin, Supervisor o Contabilidad.

require_once 'includes/header.php'; 
?>
  
<div class="p-5 mb-4 bg-light rounded-3">
    <div class="container-fluid py-5">
        <h1 class="display-5 fw-bold">Bienvenido a NóminaSYS</h1>
        <p class="col-md-8 fs-4">
            Ha iniciado sesión como <strong><?php echo htmlspecialchars($_SESSION['user_rol']); ?></strong>.
        </p>
        <hr>
        <p>Utilice la barra de navegación superior para acceder a los diferentes módulos del sistema.</p>
    </div>
</div>

<div class="row row-cols-1 row-cols-md-3 g-4">
    <div class="col">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Procesar Nómina</h5>
                <p class="card-text">Inicie el cálculo de la nómina para un período de reporte abierto.</p>
                <a href="<?php echo BASE_URL; ?>payroll/index.php" class="btn btn-primary">Ir a Procesar</a>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Aprobaciones</h5>
                <p class="card-text">Revise y apruebe las horas registradas por los inspectores.</p>
                <a href="<?php echo BASE_URL; ?>approvals/index.php" class="btn btn-success">Ir a Aprobaciones</a>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Gestión de Empleados</h5>
                <p class="card-text">Añada, vea o modifique la información de los empleados y sus contratos.</p>
                <a href="<?php echo BASE_URL; ?>employees/index.php" class="btn btn-info">Ir a Empleados</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
