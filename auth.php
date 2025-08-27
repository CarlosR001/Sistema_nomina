<?php
// auth.php - v2.7 (Estructura Corregida y Lógica Completa)

require_once __DIR__ . '/config/init.php';

// ----------------------------------------------------------------------
// DEFINICIÓN DE FUNCIONES DE AUTENTICACIÓN Y AUTORIZACIÓN
// Se mueven al principio para garantizar que siempre estén disponibles.
// ----------------------------------------------------------------------

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('require_login')) {A
    function require_login() {
        if (!is_logged_in()) {
            header('Location: ' . BASE_URL . 'login.php');
            exit();
        }
        
        // Si el usuario está marcado para cambiar su contraseña, lo redirigimos.
        if (isset($_SESSION['force_password_change'])) {
            $current_page = basename($_SERVER['PHP_SELF']);
            if ($current_page !== 'force_change_password.php') {
                header('Location: ' . BASE_URL . 'users/force_change_password.php');
                exit();
            }
        }

        // Lógica de timeout de sesión por inactividad.
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            log_activity('Cerró sesión por inactividad');
            session_unset();
            session_destroy();
            header('Location: ' . BASE_URL . 'login.php?status=session_expired');
            exit();
        }
        $_SESSION['last_activity'] = time();
    }
}

if (!function_exists('has_permission')) {
    function has_permission($permission) {
        if (isset($_SESSION['user_permissions']) && in_array('*', $_SESSION['user_permissions'])) {
            return true;
        }
        if (isset($_SESSION['user_explicit_permissions'][$permission])) {
            return (bool) $_SESSION['user_explicit_permissions'][$permission];
        }
        return isset($_SESSION['user_permissions']) && in_array($permission, $_SESSION['user_permissions']);
    }
}

if (!function_exists('require_permission')) {
    function require_permission($permission) {
        require_login();
        if (!has_permission($permission)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'No tiene permiso para acceder a esta sección.'];
            header('Location: ' . BASE_URL . 'index.php');
            exit();
        }
    }
}

// ----------------------------------------------------------------------
// PROCESAMIENTO DE ACCIONES (LOGIN/LOGOUT)
// ----------------------------------------------------------------------

// --- Procesamiento de Logout ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION['user_id'])) {
        log_activity('Cerró sesión');
    }
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// --- Procesamiento de Login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    // ... (El bloque de procesamiento de login no cambia, solo se reubica)
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT u.* FROM usuarios u WHERE u.nombre_usuario = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['contrasena'])) {
            if ($user['debe_cambiar_contrasena']) {
                session_regenerate_id(true);
                $_SESSION['force_password_change'] = true;
                $_SESSION['user_id_temp'] = $user['id'];
                header('Location: ' . BASE_URL . 'users/force_change_password.php');
                exit();
            }
            
            if (password_needs_rehash($user['contrasena'], PASSWORD_ARGON2ID, ['memory_cost' => ARGON2_MEMORY_COST, 'time_cost'   => ARGON2_TIME_COST, 'threads'     => ARGON2_THREADS])) {
                $newHash = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => ARGON2_MEMORY_COST, 'time_cost'   => ARGON2_TIME_COST, 'threads'     => ARGON2_THREADS]);
                $stmt_update_hash = $pdo->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?");
                $stmt_update_hash->execute([$newHash, $user['id']]);
            }
            
            session_regenerate_id(true);
            $stmt_empleado = $pdo->prepare("SELECT nombres, primer_apellido FROM empleados WHERE id = ?");
            $stmt_empleado->execute([$user['id_empleado']]);
            $empleado = $stmt_empleado->fetch();

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['nombre_usuario'];
            $_SESSION['user_id_empleado'] = $user['id_empleado'];
            $_SESSION['user_full_name'] = trim(($empleado['nombres'] ?? '') . ' ' . ($empleado['primer_apellido'] ?? ''));
            $_SESSION['last_activity'] = time();
    
            $stmt_roles = $pdo->prepare("SELECT DISTINCT p.clave_permiso FROM permisos p JOIN rol_permiso rp ON p.id = rp.id_permiso JOIN usuario_rol ur ON rp.id_rol = ur.id_rol WHERE ur.id_usuario = ?");
            $stmt_roles->execute([$user['id']]);
            $_SESSION['user_permissions'] = $stmt_roles->fetchAll(PDO::FETCH_COLUMN, 0);
    
            $stmt_explicit = $pdo->prepare("SELECT p.clave_permiso, pu.tiene_permiso FROM permisos_usuario pu JOIN permisos p ON pu.id_permiso = p.id WHERE pu.id_usuario = ?");
            $stmt_explicit->execute([$user['id']]);
            $_SESSION['user_explicit_permissions'] = $stmt_explicit->fetchAll(PDO::FETCH_KEY_PAIR);
    
            log_activity('Inició sesión exitosamente');
    
            if ($user['id_empleado'] && in_array('horas.registrar', $_SESSION['user_permissions'])) {
                $stmt_contrato = $pdo->prepare("SELECT id FROM contratos WHERE id_empleado = ? AND tipo_nomina = 'Inspectores' AND estado_contrato = 'Vigente' LIMIT 1");
                $stmt_contrato->execute([$user['id_empleado']]);
                if($contrato = $stmt_contrato->fetch()){
                    $_SESSION['contrato_inspector_id'] = $contrato['id'];
                }
            }

            header('Location: ' . BASE_URL . 'index.php');
            exit();

        } else {
            if ($user) {
                $sql = "INSERT INTO logdeactividad (id_usuario, accion_realizada, detalle) VALUES (?, 'Intento de inicio de sesión fallido', ?)";
                $pdo->prepare($sql)->execute([$user['id'], 'Contraseña incorrecta']);
            }
            header('Location: ' . BASE_URL . 'login.php?error=wrong_password&username=' . urlencode($username));
            exit();
        }
    }
}
?>
