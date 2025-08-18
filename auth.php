<?php
// auth.php - v2.3 (Control de Acceso Híbrido - RBAC con ACL)

require_once __DIR__ . '/config/init.php';

// --- Procesamiento de Logout ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// --- Procesamiento de Login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (isset($pdo)) {
        $stmt = $pdo->prepare(
            "SELECT u.*, e.nombres, e.primer_apellido 
             FROM usuarios u 
             LEFT JOIN empleados e ON u.id_empleado = e.id 
             WHERE u.nombre_usuario = ?"
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($password, $user['contrasena'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['nombre_usuario'];
                $_SESSION['user_id_empleado'] = $user['id_empleado'];
                $_SESSION['user_full_name'] = trim(($user['nombres'] ?? '') . ' ' . ($user['primer_apellido'] ?? ''));

                // Cargar permisos basados en roles
                $stmt_roles = $pdo->prepare("
                    SELECT DISTINCT p.clave_permiso
                    FROM permisos p
                    JOIN rol_permiso rp ON p.id = rp.id_permiso
                    JOIN usuario_rol ur ON rp.id_rol = ur.id_rol
                    WHERE ur.id_usuario = ?
                ");
                $stmt_roles->execute([$user['id']]);
                $_SESSION['user_permissions'] = $stmt_roles->fetchAll(PDO::FETCH_COLUMN, 0);

                // Cargar permisos explícitos (anulaciones) del usuario
                $stmt_explicit = $pdo->prepare("
                    SELECT p.clave_permiso, pu.tiene_permiso
                    FROM permisos_usuario pu
                    JOIN permisos p ON pu.id_permiso = p.id
                    WHERE pu.id_usuario = ?
                ");
                $stmt_explicit->execute([$user['id']]);
                $_SESSION['user_explicit_permissions'] = $stmt_explicit->fetchAll(PDO::FETCH_KEY_PAIR);


                if ($user['id_empleado'] && in_array('horas.registrar', $_SESSION['user_permissions'])) {
                    $stmt_contrato = $pdo->prepare("SELECT id FROM contratos WHERE id_empleado = ? AND tipo_nomina = 'Inspectores' AND estado_contrato = 'Vigente' LIMIT 1");
                    $stmt_contrato->execute([$user['id_empleado']]);
                    $contrato = $stmt_contrato->fetch();
                    if ($contrato) {
                        $_SESSION['contrato_inspector_id'] = $contrato['id'];
                    }
                }

                header('Location: ' . BASE_URL . 'index.php');
                exit();
            } else {
                header('Location: ' . BASE_URL . 'login.php?error=wrong_password&username=' . urlencode($username));
                exit();
            }
        } else {
            header('Location: ' . BASE_URL . 'login.php?error=user_not_found');
            exit();
        }
    }
}


// --- Funciones de Autenticación y Autorización (v2.3) ---

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
 * Verifica si el usuario actual tiene un permiso específico.
 * La lógica sigue este orden de prioridad:
 * 1. Super Admin ('*') tiene acceso a todo.
 * 2. Una regla explícita de DENEGACIÓN en permisos_usuario anula todo lo demás.
 * 3. Una regla explícita de PERMISO en permisos_usuario concede el acceso.
 * 4. Si no hay reglas explícitas, se verifica si alguno de los roles del usuario tiene el permiso.
 *
 * @param string $permission La clave del permiso a verificar.
 * @return bool True si tiene permiso, False si no.
 */
function has_permission($permission) {
    // 1. Super Admin siempre tiene acceso
    if (isset($_SESSION['user_permissions']) && in_array('*', $_SESSION['user_permissions'])) {
        return true;
    }

    // 2. Verificar reglas explícitas del usuario (anulaciones)
    if (isset($_SESSION['user_explicit_permissions'][$permission])) {
        // tiene_permiso es 1 para PERMITIR, 0 para DENEGAR.
        // Lo convertimos a booleano.
        return (bool) $_SESSION['user_explicit_permissions'][$permission];
    }

    // 3. Si no hay regla explícita, verificar permisos heredados de roles
    return isset($_SESSION['user_permissions']) && in_array($permission, $_SESSION['user_permissions']);
}

/**
 * Requiere que un usuario tenga un permiso específico. Si no lo tiene,
 * lo redirige al index con un mensaje de error.
 *
 * @param string $permission La clave del permiso requerido.
 */
function require_permission($permission) {
    require_login();
    if (!has_permission($permission)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'No tiene permiso para acceder a esta sección.'];
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}
