<?php
// config/init.php - v1.6 (Modo de Depuración Activado)
// AÑADIDO: Código para mostrar todos los errores de PHP en el navegador.

// ----------------------------------------------------
// MODO DE DEPURACIÓN - ¡SOLO PARA DESARROLLO!
// Muestra todos los errores de PHP. Comentar o eliminar en producción.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ----------------------------------------------------

// 1. Iniciar la sesión si no está iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Definir la URL base del proyecto.
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// 3. Cargar la conexión a la base de datos.
require_once __DIR__ . '/database.php';
