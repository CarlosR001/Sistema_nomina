<?php
// config/init.php - v3.0 (Configuración Multi-Entorno)

// ----------------------------------------------------------------------
// DETECCIÓN AUTOMÁTICA DE ENTORNO
// ----------------------------------------------------------------------
// Comprueba si el script se está ejecutando en un servidor local o de producción.
$is_local_environment = (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || substr($_SERVER['HTTP_HOST'], 0, 9) === 'localhost');

// ----------------------------------------------------------------------
// CONFIGURACIÓN DE SESIÓN BASADA EN EL ENTORNO
// ----------------------------------------------------------------------

if ($is_local_environment) {
    // Entorno Local (Tu PC): No se necesita una ruta de sesión especial.
    // PHP usará la configuración por defecto de XAMPP/WAMP.
} else {
    // Entorno de Producción (Bluehost): Se especifica la ruta de sesión.
    // Reemplaza 'johanse7' con tu usuario de cPanel si es diferente.
    ini_set('session.save_path', '/home3/johanse7/tmp');
}

// Iniciar la sesión SIEMPRE después de cualquier configuración de ini_set().
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------------
// CONFIGURACIÓN GENERAL DE LA APLICACIÓN
// ----------------------------------------------------------------------

// Definir la URL base (BASE_URL) de forma dinámica y robusta.
if (!defined('BASE_URL')) {
    if ($is_local_environment) {
        // Para desarrollo local (ej: http://localhost/Sistema_nomina/)
        // Asegura que la barra final esté presente.
        $base_url = sprintf(
            "%s://%s%s",
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
            $_SERVER['HTTP_HOST'],
            dirname($_SERVER['REQUEST_URI']) . '/'
        );
         // Si la URL base es solo "http://localhost//", corrígela.
        $base_url = str_replace('//', '/', $base_url);
        define('BASE_URL', $base_url);
    } else {
        // Para producción (ej: https://jyc.johansen.com.do/)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
        define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . '/');
    }
}

// Establecer la zona horaria correcta.
date_default_timezone_set('America/Santo_Domingo');

// Cargar la conexión a la base de datos.
require_once __DIR__ . '/database.php';

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
