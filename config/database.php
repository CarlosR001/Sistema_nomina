<?php

define('DB_HOST', 'localhost'); // O 'localhost'
define('DB_NAME', 'nominajyc');   // 
define('DB_USER', 'root');
define('DB_PASS', '');          
define('DB_CHAR', 'utf8mb4');


// -- DSN (Data Source Name) --
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHAR;

// -- Opciones de PDO --
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Crear la instancia de PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
  
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}