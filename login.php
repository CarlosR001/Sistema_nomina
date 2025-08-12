<?php
// login.php - v3.0 (Diseño Premium)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/init.php';

$error_message = '';
$username_value = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'wrong_password') {
        $error_message = 'Contraseña incorrecta. Por favor, inténtelo de nuevo.';
        $username_value = isset($_GET['username']) ? htmlspecialchars($_GET['username']) : '';
    } elseif ($_GET['error'] === 'user_not_found') {
        $error_message = 'El usuario especificado no fue encontrado.';
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
    <title>Bienvenido a Nómina J&C</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-color-darker: #0a58ca;
            --background-gradient-start: #e9ecef;
            --background-gradient-end: #dee2e6;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--background-gradient-start), var(--background-gradient-end));
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-card {
            max-width: 450px;
            width: 100%;
            background-color: white;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden; /* Para que el borde superior no se salga */
            border-top: 5px solid var(--primary-color);
        }

        .login-header {
            text-align: center;
            padding: 2.5rem 1.5rem 1.5rem 1.5rem;
        }

        .login-header .icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .login-header h2 {
            font-weight: 700;
            color: #333;
        }

        .card-body {
            padding: 2rem 2.5rem;
        }

        .form-control {
            height: 50px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            border-color: var(--primary-color);
        }

        .input-group-text {
            border-radius: 8px 0 0 8px;
        }

        .btn-primary {
            padding: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            background-color: var(--primary-color);
            border: none;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .btn-primary:hover {
            background-color: var(--primary-color-darker);
            transform: translateY(-2px);
        }
        
        .card-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <div class="icon"><i class="bi bi-shield-lock-fill"></i></div>
            <h2>Nómina J&C</h2>
            <p class="text-muted">Por favor, inicie sesión para continuar</p>
        </div>
        <div class="card-body pt-0">
            <?php if ($error_message): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div><?php echo $error_message; ?></div>
                </div>
            <?php endif; ?>

            <form action="auth.php" method="POST">
                <div class="mb-3 input-group">
                    <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                    <input type="text" class="form-control" name="username" placeholder="Usuario" value="<?php echo $username_value; ?>" required autofocus>
                </div>
                <div class="mb-4 input-group">
                    <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                    <input type="password" class="form-control" name="password" placeholder="Contraseña" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Acceder al Sistema</button>
                </div>
            </form>
        </div>
        <div class="card-footer text-center text-muted">
            &copy; <?php echo date('Y'); ?> J&C Group
        </div>
    </div>

</body>
</html>
