<?php
// config/init.php - v1.1
// Implementa la detección automática de BASE_URL para funcionar en cualquier entorno.

// 1. Iniciar la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Definir la URL base automáticamente
if (!defined('BASE_URL')) {
    // Obtiene el nombre de la carpeta del proyecto (ej. /Sistema_nomina/)
    $project_folder = basename(dirname(__DIR__)); 
    
    // Lo construye como /nombre_proyecto/
    $base_url = "/" . $project_folder . "/";
    
    // Si tu proyecto está en la raíz de XAMPP (htdocs), descomenta la siguiente línea:
    // $base_url = '/';

    define('BASE_URL', $base_url);
}


// 3. Cargar la conexión a la base de datos
require_once __DIR__ . '/database.php';
