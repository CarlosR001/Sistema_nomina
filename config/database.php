<?php

// --- Credenciales para la Base de Datos LOCAL ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'nominajyc'); // Nombre de tu base de datos local
define('DB_USER', 'root');      // Usuario por defecto en XAMPP/WAMP
define('DB_PASS', '');          // Contraseña por defecto (vacía)
define('DB_CHAR', 'utf8mb4');

// --- DSN (Data Source Name) ---
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHAR;

// --- Opciones de PDO ---
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Crear la instancia de PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // En un entorno de producción, nunca muestres detalles del error.
    // En su lugar, registra el error y muestra un mensaje genérico.
    error_log('Error de conexión a la base de datos: ' . $e->getMessage());
    // Para el usuario, podrías mostrar algo como esto:
    // die('Error de conexión. Por favor, intente más tarde.');
    
    // Durante el desarrollo, está bien lanzar la excepción.
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
