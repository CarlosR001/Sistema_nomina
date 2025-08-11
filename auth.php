<?php
// auth.php - v2.0 (Lógica de Login Simplificada y Segura)

// La sesión ya se inicia en init.php, pero lo dejamos por si se llama a este archivo directamente.
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
        // Unir con la tabla de empleados para obtener el nombre
        $stmt = $pdo->prepare(
            "SELECT u.*, e.nombres, e.primer_apellido 
             FROM usuarios u 
             LEFT JOIN empleados e ON u.id_empleado = e.id 
             WHERE u.nombre_usuario = ?"
        );
        $stmt->execute([$_POST['username']]);
        $user = $stmt->fetch();

        if ($user && password_verify($_POST['password'], $user['contrasena'])) {
            // **MEJORA DE SEGURIDAD: Regenerar el ID de sesión**
            session_regenerate_id(true);

            // Guardar datos esenciales en la sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['nombre_usuario'];
            $_SESSION['user_id_empleado'] = $user['id_empleado'];
            $_SESSION['user_full_name'] = trim(($user['nombres'] ?? '') . ' ' . ($user['primer_apellido'] ?? ''));

            // Cargar todos los permisos del usuario
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

            // Lógica para el contrato de inspector (si aplica)
            if ($user['id_empleado'] && in_array('horas.registrar', $permissions)) {
                $stmt_contrato = $pdo->prepare("SELECT id FROM contratos WHERE id_empleado = ? AND tipo_nomina = 'Inspectores' AND estado_contrato = 'Vigente' LIMIT 1");
                $stmt_contrato->execute([$user['id_empleado']]);
                $contrato = $stmt_contrato->fetch();
                if ($contrato) {
                    $_SESSION['contrato_inspector_id'] = $contrato['id'];
                }
            }

            // **MEJORA DE LÓGICA: Redirección ÚNICA al dashboard principal**
            header('Location: ' . BASE_URL . 'index.php');
            exit();

        } else {
            // Si la autenticación falla
            header('Location: ' . BASE_URL . 'login.php?error=1');
            exit();
        }
    }
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

function has_permission($permission) {
    // El super-permiso '*' concede acceso a todo
    if (isset($_SESSION['user_permissions']) && in_array('*', $_SESSION['user_permissions'])) {
        return true;
    }
    return isset($_SESSION['user_permissions']) && in_array($permission, $_SESSION['user_permissions']);
}

function require_permission($permission) {
    require_login();
    if (!has_permission($permission)) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'No tiene permiso para acceder a esta sección.'
        ];
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}
