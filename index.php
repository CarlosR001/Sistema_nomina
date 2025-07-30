<?php
// index.php (Página principal)

// Cargar el sistema de autenticación y la configuración de la BD.
require_once 'auth.php';
require_login(); // Asegurarse de que el usuario ha iniciado sesión.

// Cargar el header de la página.
require_once 'includes/header.php'; 
?>

<div class="p-5 mb-4 bg-light rounded-3">
    <div class="container-fluid py-5">
        <h1 class="display-5 fw-bold">Bienvenido a NóminaSYS</h1>
        <p class="col-md-8 fs-4">
            Ha iniciado sesión como <strong><?php echo htmlspecialchars($_SESSION['user_rol']); ?></strong>.
            Utilice la barra de navegación para acceder a los módulos disponibles.
        </p>
        
        <?php if ($_SESSION['user_rol'] === 'Inspector'): ?>
            <div class="alert alert-info mt-4">
                <p><strong>Módulo de Inspector:</strong></p>
                <p>Como inspector, puede registrar el tiempo de trabajo en la sección de <strong>Partes de Horas</strong>. 
                Esto es fundamental para el cálculo de su nómina.</p>
                <a class="btn btn-primary" href="time_tracking/index.php">Ir a Partes de Horas</a>
            </div>
        <?php elseif ($_SESSION['user_rol'] === 'Administrador'): ?>
            <div class="alert alert-info mt-4">
                <p><strong>Panel de Administrador:</strong></p>
                <p>Tiene acceso completo a todos los módulos del sistema. Puede gestionar empleados, contratos, nóminas y más.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Incluir el footer
require_once 'includes/footer.php';
?>
