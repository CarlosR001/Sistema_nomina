<?php
// config/init.php - v2.6 (Configuración de Timeout de Sesión)

// ----------------------------------------------------------------------
// FORZAR CODIFICACIÓN UTF-8 A NIVEL DE APLICACIÓN
// ----------------------------------------------------------------------
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (function_exists('mb_http_output')) {
    mb_http_output('UTF-8');
}
header('Content-Type: text/html; charset=utf-8');

// ----------------------------------------------------------------------
// CONFIGURACIÓN DE SESIÓN Y TIMEOUT
// ----------------------------------------------------------------------
define('SESSION_TIMEOUT', 1800); // Duración en segundos (1800 = 30 minutos)
ini_set('session.save_path', '/home3/johanse7/tmp');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ----------------------------------------------------------------------
// CONFIGURACIÓN GENERAL DE LA APLICACIÓN
// ----------------------------------------------------------------------
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
    define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . '/');
}
date_default_timezone_set('America/Santo_Domingo');

// ----------------------------------------------------------------------
// CONFIGURACIÓN DE HASHING DE CONTRASEÑAS (Argon2id)
// ----------------------------------------------------------------------
define('ARGON2_MEMORY_COST', 32768);
define('ARGON2_TIME_COST', 4);
define('ARGON2_THREADS', 2);

// Cargar la conexión a la base de datos
require_once __DIR__ . '/database.php';

// ----------------------------------------------------------------------
// MANEJO DE ERRORES
// ----------------------------------------------------------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ----------------------------------------------------------------------
// FUNCIONES DE AYUDA
// ----------------------------------------------------------------------

if (!function_exists('log_activity')) {
    function log_activity($action, $table = null, $record_id = null, $details = null) {
        global $pdo;
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id || !$pdo) { return; }
        try {
            $sql = "INSERT INTO logdeactividad (id_usuario, accion_realizada, tabla_afectada, id_registro_afectado, detalle) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $action, $table, $record_id, $details]);
        } catch (PDOException $e) {
            error_log("Error al registrar actividad en el log: " . $e->getMessage());
        }
    }
}

function set_flash_message($type, $message) {
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

function display_flash_messages() {
    if (isset($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $flash) {
            $type = htmlspecialchars($flash['type']);
            $alert_class = ($type === 'error') ? 'danger' : $type;
            $message = htmlspecialchars($flash['message']);
            
            echo "<div class='alert alert-{$alert_class} alert-dismissible fade show' role='alert'>";
            echo $message;
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
        unset($_SESSION['flash_messages']);
    }
}

function redirect_with_error($url, $message) {
    set_flash_message('error', $message);
    header('Location: ' . $url);
    exit();
}

function redirect_with_success($url, $message) {
    set_flash_message('success', $message);
    header('Location: ' . $url);
    exit();
}
?>
