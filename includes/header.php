<?php
// includes/header.php
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
    <a class="navbar-brand" href="<?php echo BASE_URL; ?>index.php">NóminaSYS</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="<?php echo BASE_URL; ?>employees/index.php">Empleados</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo BASE_URL; ?>time_tracking/index.php">Registrar Horas</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo BASE_URL; ?>approvals/index.php">Aprobar Horas</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo BASE_URL; ?>novedades/index.php">Novedades</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo BASE_URL; ?>payroll/index.php">Procesar Nómina</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo BASE_URL; ?>payroll/review.php">Revisar Nóminas</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">Configuración</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>departments/index.php">Departamentos</a></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>positions/index.php">Posiciones</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>projects/index.php">Proyectos</a></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>reporting_periods/index.php">Períodos de Reporte</a></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>zones/index.php">Zonas de Transporte</a></li>
          </ul>
        </li>
      </ul>
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="<?php echo BASE_URL; ?>logout.php">Cerrar Sesión</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4">