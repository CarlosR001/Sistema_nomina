<?php
// includes/header.php
// v1.5 - Corrige el enlace de "Cerrar Sesión" para que apunte a la lógica correcta en auth.php

// La ruta se construye desde la ubicación de este archivo (includes) para encontrar auth.php en la raíz.
require_once __DIR__ . '/../auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Nómina J&C</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">
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
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>index.php">Dashboard</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownCatalogos" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Catálogos
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownCatalogos">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>employees/index.php">Empleados</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>contracts/index.php">Contratos</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>departments/index.php">Departamentos</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>positions/index.php">Posiciones</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>projects/index.php">Proyectos</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>zones/index.php">Zonas de Transporte</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                             <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownNovedades" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Novedades
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownNovedades">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>novedades/index.php">Gestión de Novedades</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>novedades/ajuste_isr.php">Ajuste Manual de ISR</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownNomina" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Nómina
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownNomina">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>reporting_periods/index.php">Períodos de Reporte</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>payroll/index.php">Procesar Nómina</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>payroll/review.php">Revisión de Nóminas</a></li>
                            </ul>
                        </li>
                         <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>configuracion/index.php">Configuración</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                     <?php if (is_logged_in()): ?>
                        <li class="nav-item">
                            <a class="nav-link">Hola, <?php echo htmlspecialchars($_SESSION['username']); ?></a>
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
