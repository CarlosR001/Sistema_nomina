<?php
// config/init.php - v1.5 (MANUAL Y DEFINITIVO)
// Establece la BASE_URL a la raíz '/' que es la configuración correcta para este entorno de servidor.

// 1. Iniciar la sesión si no está iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Definir la URL base del proyecto.
// Para un entorno donde el servidor (ej. localhost:3000) apunta directamente a la 
// carpeta del proyecto, la URL base es simplemente una barra inclinada "/".
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// 3. Cargar la conexión a la base de datos.
require_once __DIR__ . '/database.php';
