<?php
// auth.php - v1.1
// Hace que la definición de funciones sea idempotente (segura para múltiples inclusiones).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se usa require_once para asegurar que la config solo se cargue una vez.
require_once __DIR__ . '/config/init.php'; 

// La constante BASE_URL ahora se define en init.php, por lo que no necesita re-definirse aquí.

// --- Procesamiento de Login/Logout ---
// Esta sección solo se ejecuta si la página es la que procesa el login/logout,
// no cuando el archivo es simplemente incluido por otro.

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE nombre_usuario = ?");
        $stmt->execute([$_POST['username']]);
        $user = $stmt->fetch();

        if ($user && password_verify($_POST['password'], $user['contrasena'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['nombre_usuario'];
            $_SESSION['user_rol'] = $user['rol'];
            
            header('Location: ' . BASE_URL . 'index.php');
            exit();
        }
    }
    header('Location: ' . BASE_URL . 'login.php?error=1');
    exit();
}


// --- Definición de Funciones Auxiliares ---

if (!function_exists('is_logged_in')) {
    /**
     * Verifica si hay un usuario logueado en la sesión.
     * @return bool
     */
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('require_login')) {
    /**
     * Si no hay un usuario logueado, redirige a la página de login.
     */
    function require_login() {
        if (!is_logged_in()) {
            header('Location: ' . BASE_URL . 'login.php');
            exit();
        }
    }
}

if (!function_exists('require_role')) {
    /**
     * Verifica que el usuario logueado tenga un rol específico.
     * @param string|array $role El rol o roles requeridos.
     */
    function require_role($role) {
        if (!isset($_SESSION['user_rol'])) {
            header('HTTP/1.0 403 Forbidden');
            die('Acceso denegado. Rol no definido.');
        }

        $user_rol = $_SESSION['user_rol'];
        $is_allowed = is_array($role) ? in_array($user_rol, $role) : ($user_rol === $role);

        if (!$is_allowed) {
            header('HTTP/1.0 403 Forbidden');
            die('Acceso denegado. No tienes los permisos necesarios.');
        }
    }
}
