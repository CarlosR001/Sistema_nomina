<?php
// vendor/autoload.php

spl_autoload_register(function ($class) {
    // Define el prefijo del namespace y el directorio base
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';

    // Comprueba si la clase usa el prefijo
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, pasar al siguiente autoloader registrado
        return;
    }

    // Obtiene el nombre relativo de la clase
    $relative_class = substr($class, $len);

    // Reemplaza el namespace con el separador de directorios y añade .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Si el archivo existe, lo requiere
    if (file_exists($file)) {
        require $file;
    }
});
