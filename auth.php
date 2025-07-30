<?php
// auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// --- Procesar el logout ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// --- Procesar el formulario de login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    if (isset($pdo)) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $password === $user['password']) { // ¡ALERTA DE SEGURIDAD!
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_rol'] = $user['rol'];
            $_SESSION['user_info'] = $user; // Contiene todos los datos del usuario

            // Si es inspector, obtener su contrato
            if ($user['rol'] === 'Inspector' && $user['id_empleado']) {
                $stmt_contrato = $pdo->prepare("SELECT id FROM Contratos WHERE id_empleado = ? AND tipo_nomina = 'Inspectores' AND estado_contrato = 'Vigente' LIMIT 1");
                $stmt_contrato->execute([$user['id_empleado']]);
                $contrato = $stmt_contrato->fetch();
                if ($contrato) {
                    $_SESSION['contrato_inspector_id'] = $contrato['id'];
                }
            }
            
            header('Location: ' . BASE_URL . 'index.php');
            exit();
        }
    }
    header('Location: ' . BASE_URL . 'login.php?error=1');
    exit();
}


// --- Funciones auxiliares de autenticación ---
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

function require_role($role) {
    if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== $role) {
        // Redirigir o mostrar error si el rol no es el correcto.
        header('HTTP/1.0 403 Forbidden');
        die('Acceso denegado. No tienes los permisos necesarios.');
    }
}

// --- Datos del usuario para uso global ---
$user = $_SESSION['user_info'] ?? null;
$contrato_inspector_id = $_SESSION['contrato_inspector_id'] ?? null;
?>
