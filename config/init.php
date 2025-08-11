<?php
// config/init.php - v2.0 (Configuración para Bluehost)

// ----------------------------------------------------------------------
// CONFIGURACIÓN ESPECIAL PARA BLUEHOST
// ----------------------------------------------------------------------

// 1. Ruta de sesión para Bluehost.
// ¡MUY IMPORTANTE! Debe estar ANTES de session_start().
// Reemplaza 'johanse7' con tu nombre de usuario de cPanel si es diferente.
ini_set('session.save_path', '/home3/johanse7/tmp');

// 2. Iniciar la sesión (ahora sí, después de definir la ruta).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------------
// CONFIGURACIÓN GENERAL DE LA APLICACIÓN
// ----------------------------------------------------------------------

// 3. Definir la URL base dinámica para el entorno de producción.
if (!defined('BASE_URL')) {
    // Esto crea la URL base como "https://tu-dominio.com"
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
    define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . '/');
}

// 4. Establecer la zona horaria correcta para República Dominicana.
date_default_timezone_set('America/Santo_Domingo');

// 5. Cargar la conexión a la base de datos.
// __DIR__ asegura que la ruta siempre sea correcta.
require_once __DIR__ . '/database.php';


// ----------------------------------------------------------------------
// MANEJO DE ERRORES (MODO DEPURACIÓN)
// ----------------------------------------------------------------------
// Para la prueba piloto, es útil ver los errores directamente.
// Cuando el sistema esté en producción final, cambia '1' a '0'.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>
