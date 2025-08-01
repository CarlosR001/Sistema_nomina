<?php
// config/init.php - v1.2
// Establece la BASE_URL a la raíz '/' para entornos donde el servidor apunta directamente al proyecto.

// 1. Iniciar la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Definir la URL base
// Para entornos de desarrollo donde el servidor (ej. localhost:3000) apunta
// directamente a la carpeta del proyecto, la URL base es simplemente "/".
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

/*
// NOTA: Si en el futuro mueves este proyecto a una subcarpeta del servidor 
// (ej. htdocs/proyectos/nomina), puedes usar la siguiente lógica automática:
if (!defined('BASE_URL')) {
    $project_folder = basename(dirname(__DIR__)); 
    $base_url = "/" . $project_folder . "/";
    define('BASE_URL', $base_url);
}
*/

// 3. Cargar la conexión a la base de datos
require_once __DIR__ . '/database.php';
