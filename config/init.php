<?php
// config/init.php

// 0. Definir la URL base para toda la aplicación
// Esto soluciona los errores de redirección y hace los enlaces más robustos.
define('BASE_URL', '/');

// 1. Iniciar la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Cargar la conexión a la base de datos
require_once __DIR__ . '/database.php';

// 3. Cargar el sistema de autenticación
require_once __DIR__ . '/../auth.php';
