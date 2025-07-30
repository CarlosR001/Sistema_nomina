<?php
// includes/header.php

// La autenticación ya se ha verificado en la página que incluye este archivo.
// Se asume que $_SESSION['user_id'] y $_SESSION['user_rol'] ya están disponibles.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Nómina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> <!-- 'defer' attribute removed -->
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>index.php">NóminaSYS</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php $rol = $_SESSION['user_rol'] ?? ''; ?>

                <?php if (in_array($rol, ['Admin', 'Contabilidad', 'Supervisor'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>employees/index.php">Empleados</a>
                    </li>
                <?php endif; ?>

                <?php if ($rol === 'Inspector'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>time_tracking/index.php">Partes de Horas</a>
                    </li>
                <?php endif; ?>

                <?php if (in_array($rol, ['Admin', 'Supervisor'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>approvals/index.php">Aprobar Horas</a>
                    </li>
                <?php endif; ?>
                
                <?php if (in_array($rol, ['Admin', 'Contabilidad'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>novedades/index.php">Novedades</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Nómina
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>payroll/index.php">Procesar Nómina</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>payroll/review.php">Historial</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if ($rol === 'Admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarConfigDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Configuración
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarConfigDropdown">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>departments/index.php">Departamentos</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>positions/index.php">Cargos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>projects/index.php">Proyectos</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>reporting_periods/index.php">Períodos</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>zones/index.php">Zonas</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_info'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo htmlspecialchars($_SESSION['user_info']['nombre_usuario']); ?> (<?php echo htmlspecialchars($rol); ?>)
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                        <li><a class="dropdown-item" href="#">Mi Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>auth.php?action=logout">Cerrar Sesión</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-4">
