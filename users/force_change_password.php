<?php
// users/force_change_password.php
require_once '../config/init.php'; // Usamos init para la sesión y la BD, pero no auth.php completo

// Si no está el flag de forzar cambio, no debería estar aquí.
if (!isset($_SESSION['force_password_change']) || !isset($_SESSION['user_id_temp'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['contrasena'];
    $confirm_password = $_POST['confirmar_contrasena'];
    $user_id = $_SESSION['user_id_temp'];

    if (empty($password) || empty($confirm_password)) {
        $error_message = "Ambos campos son obligatorios.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 6) {
        $error_message = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        // Todo es correcto, procedemos a actualizar
        try {
            $new_hash = password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => ARGON2_MEMORY_COST,
                'time_cost'   => ARGON2_TIME_COST,
                'threads'     => ARGON2_THREADS
            ]);

            $stmt = $pdo->prepare("UPDATE usuarios SET contrasena = ?, debe_cambiar_contrasena = FALSE WHERE id = ?");
            $stmt->execute([$new_hash, $user_id]);

            // Limpiamos los flags temporales
            unset($_SESSION['force_password_change']);
            unset($_SESSION['user_id_temp']);

            // Forzamos un re-login para que la sesión se cargue con todos los permisos
            session_destroy();
            header('Location: ' . BASE_URL . 'login.php?status=password_changed');
            exit();

        } catch (PDOException $e) {
            $error_message = "Error al actualizar la contraseña. Intente de nuevo.";
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambio de Contraseña Obligatorio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 500px; margin-top: 100px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow">
            <div class="card-body p-5">
                <h3 class="card-title text-center mb-4">Cambio de Contraseña Requerido</h3>
                <p class="text-center text-muted">Por su seguridad, debe establecer una nueva contraseña para continuar.</p>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="contrasena" class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" id="contrasena" name="contrasena" required>
                    </div>
                    <div class="mb-4">
                        <label for="confirmar_contrasena" class="form-label">Confirmar Nueva Contraseña</label>
                        <input type="password" class="form-control" id="confirmar_contrasena" name="confirmar_contrasena" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Establecer Contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
