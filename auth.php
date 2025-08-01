<?php
// auth.php - v1.4 DEFINITIVA
// Restaura la lógica para buscar y guardar el ID del contrato del inspector en la sesión.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/init.php'; 

// --- Procesamiento de Logout ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// --- Procesamiento de Login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ?");
        $stmt->execute([$_POST['username']]);
        $user = $stmt->fetch();

        if ($user && password_verify($_POST['password'], $user['contrasena'])) {
            // Asignaciones de sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['nombre_usuario'];
            $_SESSION['user_rol'] = $user['rol'];

            // --- LÓGICA DE CONTRATO RESTAURADA (LA CORRECCIÓN) ---
            if ($user['rol'] === 'Inspector' && $user['id_empleado']) {
                $stmt_contrato = $pdo->prepare("SELECT id FROM contratos WHERE id_empleado = ? AND tipo_nomina = 'Inspectores' AND estado_contrato = 'Vigente' LIMIT 1");
                $stmt_contrato->execute([$user['id_empleado']]);
                $contrato = $stmt_contrato->fetch();
                if ($contrato) {
                    $_SESSION['contrato_inspector_id'] = $contrato['id'];
                }
            }
            // --- FIN DE LA CORRECCIÓN ---

            // Redirección centralizada
            if ($user['rol'] === 'Inspector') {
                header('Location: ' . BASE_URL . 'time_tracking/index.php');
            } else {
                header('Location: ' . BASE_URL . 'index.php');
            }
            exit();
        }
    }
    // Si el login falla, redirige con error.
    header('Location: ' . BASE_URL . 'login.php?error=1');
    exit();
}


// --- Definición de Funciones Auxiliares ---

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('require_login')) {
    function require_login() {
        if (!is_logged_in()) {
            header('Location: ' . BASE_URL . 'login.php');
            exit();
        }
    }
}

if (!function_exists('require_role')) {
    function require_role($role) {
        $user_rol = $_SESSION['user_rol'] ?? null;
        if (!$user_rol) {
            header('HTTP/1.0 403 Forbidden');
            die('Acceso denegado. Rol no definido.');
        }
        $is_allowed = is_array($role) ? in_array($user_rol, $role) : ($user_rol === $role);
        if (!$is_allowed) {
            header('HTTP/1.0 403 Forbidden');
            die('Acceso denegado. No tienes los permisos necesarios.');
        }
    }
}
