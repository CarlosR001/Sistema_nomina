<?php
// index.php (Página principal)

// Cargar el archivo de inicialización que contiene todo lo necesario
require_once 'config/init.php';

// Esta página requiere que el usuario haya iniciado sesión, 
// pero no requiere un rol específico. El header se encargará de la redirección.
require_once 'includes/header.php';
?>

<div class="p-5 mb-4 bg-light rounded-3">
    <div class="container-fluid py-5">
        <h1 class="display-5 fw-bold">Bienvenido a NóminaSYS</h1>
        <p class="col-md-8 fs-4">
            Ha iniciado sesión como <strong><?php echo htmlspecialchars($user['rol']); ?></strong>.
            Utilice la barra de navegación para acceder a los módulos disponibles para su rol.
        </p>
    </div>
</div>

<?php
// Incluir el footer
require_once 'includes/footer.php';
?>
