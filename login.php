<?php
// login.php - v2.0 (Diseño Profesional)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Si el usuario ya está logueado, redirigir al dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/init.php';

// --- Lógica para Mensajes de Error y Usuario ---
$error_message = '';
$username_value = '';

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'wrong_password') {
        $error_message = 'Contraseña incorrecta. Por favor, inténtelo de nuevo.';
        // Si la contraseña es incorrecta, recuperamos el username para no borrarlo.
        $username_value = isset($_GET['username']) ? htmlspecialchars($_GET['username']) : '';
    } elseif ($_GET['error'] === 'user_not_found') {
        $error_message = 'El usuario no existe.';
    } else {
        $error_message = 'Credenciales inválidas.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Nómina J&C</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .login-card {
            max-width: 450px;
            width: 100%;
            padding: 2rem;
        }
        .card-title {
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>

    <div class="card login-card shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-center">
                <i class="bi bi-person-circle me-2"></i>
                Inicio de Sesión
            </h2>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="auth.php" method="POST">
                <div class="mb-3 input-group">
                    <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                    <input type="text" class="form-control" name="username" placeholder="Nombre de usuario" value="<?php echo $username_value; ?>" required autofocus>
                </div>
                <div class="mb-4 input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" class="form-control" name="password" placeholder="Contraseña" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Acceder</button>
                </div>
            </form>
        </div>
        <div class="card-footer text-center text-muted">
            &copy; <?php echo date('Y'); ?> J&C Group. Todos los derechos reservados.
        </div>
    </div>

</body>
</html>
