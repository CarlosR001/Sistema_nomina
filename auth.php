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
            // Asignaciones de sesión básicas
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['nombre_usuario'];

            // Cargar todos los permisos del usuario a través de sus roles
            $stmt_perms = $pdo->prepare("
                SELECT DISTINCT p.clave_permiso
                FROM permisos p
                JOIN rol_permiso rp ON p.id = rp.id_permiso
                JOIN usuario_rol ur ON rp.id_rol = ur.id_rol
                WHERE ur.id_usuario = ?
            ");
            $stmt_perms->execute([$user['id']]);
            $permissions = $stmt_perms->fetchAll(PDO::FETCH_COLUMN, 0);
            $_SESSION['user_permissions'] = $permissions;

                       // --- LÓGICA DE CONTRATO Y EMPLEADO (AHORA BASADA EN PERMISOS) ---
                       if ($user['id_empleado']) {
                        // Si el usuario está vinculado a un empleado, guardar siempre el ID de empleado
                        $_SESSION['user_id_empleado'] = $user['id_empleado'];
        
                        // Si además tiene permiso para registrar horas, buscar su contrato
                        if (in_array('horas.registrar', $permissions)) {
                            $stmt_contrato = $pdo->prepare("SELECT id FROM contratos WHERE id_empleado = ? AND tipo_nomina = 'Inspectores' AND estado_contrato = 'Vigente' LIMIT 1");
                            $stmt_contrato->execute([$user['id_empleado']]);
                            $contrato = $stmt_contrato->fetch();
                            if ($contrato) {
                                $_SESSION['contrato_inspector_id'] = $contrato['id'];
                            }
                        }
                    }
        
      
            // --- REDIRECCIÓN INTELIGENTE (AHORA BASADA EN PERMISOS) ---
            if (in_array('horas.registrar', $permissions)) {
                header('Location: ' . BASE_URL . 'time_tracking/index.php');
            } elseif (in_array('reportes.horas_extras.ver', $permissions)) {
                header('Location: ' . BASE_URL . 'he_admin/index.php');
            } else {
                // Admin, Supervisor, Contabilidad, etc., van al dashboard.
                header('Location: ' . BASE_URL . 'index.php');
            }
            exit();
        } else {
            // Si la contraseña es incorrecta
            header('Location: ' . BASE_URL . 'login.php?error=1');
            exit();
        }
    } // Cierre del if(isset($pdo))
} // Cierre del if($_SERVER['REQUEST_METHOD'] === 'POST' ...)

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

/**
 * Comprueba si el usuario logueado tiene un permiso específico.
 * Es la nueva función central de seguridad.
 */
function has_permission($permission) {
    if (isset($_SESSION['user_permissions']) && in_array('*', $_SESSION['user_permissions'])) {
        return true; // El permiso '*' concede acceso a todo
    }
    return isset($_SESSION['user_permissions']) && in_array($permission, $_SESSION['user_permissions']);
}

/**
 * Exige que un usuario tenga un permiso para acceder. Si no, redirige.
 * Reemplaza a la antigua require_role().
 */
function require_permission($permission) {
    require_login(); // Primero nos aseguramos de que esté logueado
    if (!has_permission($permission)) {
        // Redirigir a una página de 'acceso denegado' o al inicio.
        header('Location: ' . BASE_URL . 'index.php?status=error&message=' . urlencode('Acceso no autorizado a esta sección.'));
        exit();
    }
}
