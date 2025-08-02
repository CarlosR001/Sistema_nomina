<?php
// config/init.php - v1.3 (Definitivo)
// Implementa la detección automática de BASE_URL para funcionar en cualquier entorno.

// 1. Iniciar la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Definir la URL base automáticamente
if (!defined('BASE_URL')) {
    // Obtiene la ruta del script actual (ej. /Sistema_nomina/config/init.php)
    $script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

    // Sube un nivel para obtener la raíz del proyecto (ej. /Sistema_nomina)
    $base_url = rtrim(dirname($script_path), '/') . '/';

    define('BASE_URL', $base_url);
}


// 3. Cargar la conexión a la base de datos
require_once __DIR__ . '/database.php';
