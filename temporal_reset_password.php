<?php
// temporal_reset_password.php
// Este script restablece la contraseña del usuario 'admin'.

require_once 'config/init.php';

$username_to_reset = 'admin';
$new_password = 'admin'; // Vamos a restablecerla a 'admin'

try {
    // Hashear la nueva contraseña de la misma manera que lo hace el sistema
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    // Preparar y ejecutar la actualización
    $stmt = $pdo->prepare("UPDATE usuarios SET contrasena = :password WHERE nombre_usuario = :username");
    $stmt->execute([
        ':password' => $hashed_password,
        ':username' => $username_to_reset
    ]);

    // Verificar si la actualización fue exitosa
    $affected_rows = $stmt->rowCount();

    if ($affected_rows > 0) {
        echo "<h1>Contraseña Restablecida</h1>";
        echo "<p>La contraseña para el usuario '<strong>" . htmlspecialchars($username_to_reset) . "</strong>' ha sido cambiada exitosamente a '<strong>" . htmlspecialchars($new_password) . "</strong>'.</p>";
        echo "<p>Ya puedes iniciar sesión con las nuevas credenciales.</p>";
    } else {
        echo "<h1>Error</h1>";
        echo "<p>No se pudo encontrar al usuario '<strong>" . htmlspecialchars($username_to_reset) . "</strong>' en la base de datos para restablecer la contraseña.</p>";
    }

} catch (Exception $e) {
    echo "<h1>Error Crítico</h1>";
    echo "<p>Ocurrió un error al conectar con la base de datos o al ejecutar la consulta: " . $e->getMessage() . "</p>";
}

echo "<p style='color:red; font-weight:bold;'>IMPORTANTE: Por favor, borra este archivo (temporal_reset_password.php) de tu servidor ahora mismo por seguridad.</p>";

?>
