<?php
// index.php (raíz del proyecto)
require_once 'config/init.php';

// Lógica de seguridad: si no hay sesión, se va al login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Si la sesión existe, cargamos el resto de la página.
require_once 'includes/header.php';
?>

<div class="p-5 mb-4 bg-light rounded-3">
    <div class="container-fluid py-5">
        <h1 class="display-5 fw-bold">Bienvenido a NóminaSYS, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>!</h1>
        <p class="col-md-8 fs-4">
            Este es el sistema de gestión de nóminas. Utilice la barra de navegación para acceder a los diferentes módulos.
        </p>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>