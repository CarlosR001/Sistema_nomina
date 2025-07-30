<?php
// employees/create.php

// Requerir el header
require_once '../config/init.php';
require_once '../includes/header.php';
?>

<h1 class="mb-4">Añadir Nuevo Empleado</h1>

<form action="store.php" method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="nombres" class="form-label">Nombres</label>
            <input type="text" class="form-control" id="nombres" name="nombres" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="primer_apellido" class="form-label">Primer Apellido</label>
            <input type="text" class="form-control" id="primer_apellido" name="primer_apellido" required>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="cedula" class="form-label">Cédula</label>
            <input type="text" class="form-control" id="cedula" name="cedula" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="email_personal" class="form-label">Email Personal</label>
            <input type="email" class="form-control" id="email_personal" name="email_personal" required>
        </div>
    </div>
    <button type="submit" class="btn btn-success">Guardar Empleado</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
</form>

<?php
// Requerir el footer
require_once '../includes/footer.php';
?>