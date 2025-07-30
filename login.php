<?php
require_once 'config/init.php'; 

if (isset($_SESSION['usuario_id'])) {
    // Si ya hay sesión, redirigir al inicio
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}
$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NóminaSYS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; padding-top: 40px; padding-bottom: 40px; background-color: #f5f5f5; height: 100vh; }
        .form-signin { width: 100%; max-width: 330px; padding: 15px; margin: auto; }
    </style>
</head>
<body class="text-center">
    <main class="form-signin">
      <form action="<?php echo BASE_URL; ?>auth.php" method="POST">
            <h1 class="h3 mb-3 fw-normal">NóminaSYS</h1>
            <h2 class="h5 mb-3 fw-normal">Iniciar Sesión</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger">Usuario o contraseña incorrectos.</div>
            <?php endif; ?>
            <div class="form-floating">
                <input type="text" class="form-control" id="username" name="username" placeholder="Usuario" required autofocus>
                <label for="username">Usuario</label>
            </div>
            <div class="form-floating mt-2">
                <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                <label for="password">Contraseña</label>
            </div>
            <button class="w-100 btn btn-lg btn-primary mt-3" type="submit">Entrar</button>
        </form>
    </main>
</body>
</html>