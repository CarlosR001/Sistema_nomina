<?php
// includes/header.php - v2.0 (Con Menú Dinámico Basado en Permisos)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Nómina J&C</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>">Nómina J&C</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>index.php">Inicio</a>
                    </li>

                    <!-- Menú Entrada de Datos -->
                    <?php if (has_permission('organizacion.gestionar') || has_permission('nomina.procesar')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownEntrada" role="button" data-bs-toggle="dropdown" aria-expanded="false">Entrada de Datos</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownEntrada">
                            <?php if (has_permission('organizacion.gestionar')): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>reporting_periods/index.php">Períodos de Reporte</a></li>
                            <?php endif; ?>
                            <?php if (has_permission('nomina.procesar')): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>payroll/generar_novedades.php">Generar Novedades desde Horas</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>novedades/index.php">Novedades Manuales</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>novedades/ajuste_isr.php">Ajuste Manual de ISR</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- Menú Nómina -->
                    <?php if (has_permission('nomina.procesar') || has_permission('aprobaciones.gestionar') || has_permission('nomina.exportar.banco')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownNomina" role="button" data-bs-toggle="dropdown" aria-expanded="false">Nómina</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownNomina">
                            <?php if (has_permission('aprobaciones.gestionar')): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>approvals/index.php">Aprobaciones</a></li>
                            <?php endif; ?>
                            <?php if (has_permission('nomina.procesar')): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>payroll/index.php">Procesar Nómina</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>payroll/review.php">Revisión de Nóminas</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>nomina_administrativa/index.php">Nómina Administrativa</a></li>

                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>pagos_especiales/index.php">Pagos Especiales</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>liquidaciones/index.php">Liquidaciones</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>tss/index.php">Exportación TSS</a></li>

                                <?php endif; ?>
                            <?php if (has_permission('nomina.exportar.banco')): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>export_banco/index.php">Exportar para Banco</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                                        <!-- Menú Reportes -->
                     <?php if (has_permission('reportes.horas_extras.ver')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>he_admin/index.php">Reporte H.E.</a>
                    </li>
                    <?php endif; ?>


                    <!-- Menú Gestión de Órdenes -->
                    <?php if (has_permission('ordenes.gestionar')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="ordenesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Gestión de Órdenes</a>
                        <ul class="dropdown-menu" aria-labelledby="ordenesDropdown">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>ordenes/index.php">Órdenes de Trabajo</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>clientes/index.php">Clientes</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>productos/index.php">Productos</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>operaciones/index.php">Operaciones</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- Menú Organización -->
                    <?php if (has_permission('empleados.gestionar') || has_permission('organizacion.gestionar') || has_permission('usuarios.gestionar')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownOrganizacion" role="button" data-bs-toggle="dropdown" aria-expanded="false">Organización</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownOrganizacion">
                             <?php if (has_permission('empleados.gestionar')): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>employees/index.php">Empleados y Contratos</a></li>
                            <?php endif; ?>
                            <?php if (has_permission('usuarios.gestionar')): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>users/index.php">Gestión de Usuarios</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>roles/index.php">Gestión de Roles</a></li>
                            <?php endif; ?>
                            <?php if (has_permission('organizacion.gestionar')): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>departments/index.php">Departamentos</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>positions/index.php">Posiciones</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>divisiones/index.php">Divisiones</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>lugares/index.php">Lugares y Sub-Lugares</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>bancos/index.php">Bancos</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>calendario/index.php">Calendario Feriado</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>conceptos/index.php">Conceptos de Nómina</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>configuracion/index.php">Configuración Global</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                <?php endif; ?>
            </ul>
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="navbar-text me-3">
                    Hola, <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a href="<?php echo BASE_URL; ?>auth.php?action=logout" class="btn btn-outline-light">Cerrar Sesión</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="container mt-4">
    <?php if (isset($_GET['status'], $_GET['message'])): ?>
        <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
