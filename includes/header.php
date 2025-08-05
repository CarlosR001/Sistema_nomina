<?php
// includes/header.php
// v2.1 - Unifica el uso de la variable de rol para corregir errores.

// Se unifican los includes y la inicialización de sesión aquí para consistencia.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../auth.php';

// Esta es la única variable que usaremos para el rol en todo el archivo.
$user_rol = $_SESSION['user_rol'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Nómina J&C</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>">Nómina J&C</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (is_logged_in()): ?>
                        
                        <?php if ($user_rol === 'Inspector'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>time_tracking/index.php">Portal de Registro de Horas</a>
                            </li>
                        <?php else: // Para Admin, Supervisor, Contabilidad ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>index.php">Dashboard</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownNomina" role="button" data-bs-toggle="dropdown" aria-expanded="false">Nómina</a>
                                <ul class="dropdown-menu" aria-labelledby="navbarDropdownNomina">
                                    <?php if (in_array($user_rol, ['Admin', 'Supervisor'])): // Variable corregida ?>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>approvals/">Aprobaciones</a></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>payroll/">Procesar Nómina</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>pagos_especiales/">Procesar Pago Especial</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>payroll/review.php">Revisión de Nóminas</a></li>
                                </ul>
                            </li>
                         <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownNovedades" role="button" data-bs-toggle="dropdown" aria-expanded="false">Entrada de Datos</a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownNovedades">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>reporting_periods/index.php">Períodos de Reporte (Inspectores)</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>payroll/generar_novedades.php">Generar Novedades de Horas</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>novedades/index.php">Novedades Manuales (Comisiones, etc.)</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>novedades/horas_extras_admin.php">Horas Extras (Personal Fijo)</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>novedades/ajuste_isr.php">Ajuste Manual de ISR</a></li>
                            </ul>
                        </li>

                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownCatalogos" role="button" data-bs-toggle="dropdown" aria-expanded="false">Catálogos</a>
                                <ul class="dropdown-menu" aria-labelledby="navbarDropdownCatalogos">
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>employees/index.php">Empleados y Contratos</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>departments/index.php">Departamentos</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>positions/index.php">Posiciones</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>projects/index.php">Proyectos</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>zones/index.php">Zonas de Transporte</a></li>
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownConfig" role="button" data-bs-toggle="dropdown" aria-expanded="false">Sistema</a>
                                <ul class="dropdown-menu" aria-labelledby="navbarDropdownConfig">
                                    <?php if ($user_rol === 'Admin'): // Variable corregida ?>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>users/index.php">Gestión de Usuarios</a></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>conceptos/index.php">Conceptos de Nómina</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>calendario/index.php">Calendario Laboral</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>configuracion/index.php">Configuración Global</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                     <?php if (is_logged_in()): ?>
                        <li class="nav-item">
                            <span class="navbar-text">Hola, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </li>
                         <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>auth.php?action=logout">Cerrar Sesión</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>login.php">Iniciar Sesión</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
