<?php
// config/init.php

// Iniciar la sesión SOLO SI no hay una ya activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- DEFINICIÓN FIJA DE LA RUTA BASE ---
// Esta es la URL completa de tu proyecto. Asegúrate de que sea correcta.
define('BASE_URL', 'http://localhost/sistema_nomina/');

// Incluir la conexión a la base de datos
require_once 'database.php';