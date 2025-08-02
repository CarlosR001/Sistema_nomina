<?php
// config/init.php - v1.4 (MANUAL Y DEFINITIVO)
// Establece la BASE_URL de forma manual y explícita para garantizar el funcionamiento.

// 1. Iniciar la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Definir la URL base
// Esta es la configuración correcta para un proyecto en una subcarpeta.
// Si el nombre de tu carpeta de proyecto cambia, solo necesitas cambiarlo aquí.
if (!defined('BASE_URL')) {
    define('BASE_URL', '/Sistema_nomina/');
}

// 3. Cargar la conexión a la base de datos
require_once __DIR__ . '/database.php';
