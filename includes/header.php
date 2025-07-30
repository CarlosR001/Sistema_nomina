<?php
// includes/header.php
// El archivo que incluye este header DEBE haber incluido 'config/init.php' primero.

// Proteger la página (la función ya existe gracias a init.php)
require_login();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Nómina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/index.php">NóminaSYS</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if ($user && in_array($user['rol'], ['Admin', 'Contabilidad', 'Supervisor'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/employees/index.php">Empleados</a>
                    </li>
                <?php endif; ?>

                <?php if ($user && $user['rol'] === 'Inspector'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/time_tracking/index.php">Registrar Horas</a>
                    </li>
                <?php endif; ?>

                <?php if ($user && in_array($user['rol'], ['Admin', 'Supervisor'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/approvals/index.php">Aprobar Horas</a>
                    </li>
                <?php endif; ?>
                
                <?php if ($user && in_array($user['rol'], ['Admin', 'Contabilidad'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/novedades/index.php">Novedades</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Nómina
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="/payroll/index.php">Procesar Nómina</a></li>
                            <li><a class="dropdown-item" href="/payroll/review.php">Revisar Nóminas</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if ($user && $user['rol'] === 'Admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarConfigDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Configuración
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarConfigDropdown">
                            <li><a class="dropdown-item" href="/departments/index.php">Departamentos</a></li>
                            <li><a class="dropdown-item" href="/positions/index.php">Posiciones</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/projects/index.php">Proyectos</a></li>
                            <li><a class="dropdown-item" href="/reporting_periods/index.php">Períodos de Reporte</a></li>
                            <li><a class="dropdown-item" href="/zones/index.php">Zonas de Transporte</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="/logout.php">Cerrar Sesión (<?php echo htmlspecialchars($user['nombre_usuario'] ?? ''); ?>)</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-4">
