<?php
// config/init.php - v3.2 (Configuración Multi-Entorno Final)

// ----------------------------------------------------------------------
// DETECCIÓN AUTOMÁTICA DE ENTORNO
// ----------------------------------------------------------------------
$is_local_environment = (
    in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || 
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
);

// ----------------------------------------------------------------------
// CONFIGURACIÓN DE SESIÓN BASADA EN EL ENTORNO
// ----------------------------------------------------------------------

if (!$is_local_environment) {
    // Entorno de Producción (Bluehost): Se especifica la ruta de sesión.
    ini_set('session.save_path', '/home3/johanse7/tmp');
}

// Iniciar la sesión SIEMPRE después de cualquier configuración de ini_set().
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------------
// CONFIGURACIÓN GENERAL DE LA APLICACIÓN
// ----------------------------------------------------------------------

// Definir la URL base (BASE_URL) de forma robusta.
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];

    // Para un servidor de desarrollo que se ejecuta en la raíz del proyecto (como `php -S` o un Virtual Host),
    // la URL base es simplemente el host. Para producción, es lo mismo.
    // La diferencia es manejada por el propio `host` (`localhost:3000` vs `jyc.johansen.com.do`).
    define('BASE_URL', $protocol . '://' . $host . '/');
}

// Establecer la zona horaria correcta.
date_default_timezone_set('America/Santo_Domingo');

// Cargar la conexión a la base de datos.
require_once __DIR__ . '/database.php';



//------------------------------------------------------------
// FUNCIÓN DE LOG DE ACTIVIDAD
// -----------------------------------------------------------
if (!function_exists('log_activity')) {
  /**
     * Registra una acción del usuario en la base de datos.
     *
     * @param string $action Descripción de la acción (ej: 'Inició sesión').
     * @param string|null $table La tabla afectada (ej: 'clientes').
     * @param int|null $record_id El ID del registro afectado.
     * @param string|null $details Detalles adicionales en formato de texto.
     */
    function log_activity($action, $table = null, $record_id = null, $details = null) {
        global $pdo;
        // Solo registra si hay un usuario en la sesión y una conexión a la BD.
        if (!isset($_SESSION['user_id']) || !$pdo) {
            return;
        }

        try {
            $sql = "INSERT INTO logdeactividad (id_usuario, accion_realizada, tabla_afectada, id_registro_afectado, detalle) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_SESSION['user_id'], $action, $table, $record_id, $details]);
        } catch (PDOException $e) {
            // Si el log falla, no debe detener la aplicación.
            // Opcional: registrar el error del log en un archivo de texto.
            error_log("Error al registrar actividad en el log: " . $e->getMessage());
        }
    }
}

//-------------------------------------------------------------
// ----------------------------------------------------------------------
// MANEJO DE ERRORES (MODO DEPURACIÓN)
// ----------------------------------------------------------------------
// Mostrar errores en local, ocultarlos en producción.
if ($is_local_environment) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

?>
