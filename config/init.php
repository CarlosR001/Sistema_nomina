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

// 3. El sistema de autenticación (`auth.php`) ahora se carga bajo demanda
// en las páginas que lo necesitan, en lugar de cargarse aquí.
