<?php
// clientes/create.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);
require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Añadir Nuevo Cliente</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Clientes</a></li>
        <li class="breadcrumb-item active">Añadir Cliente</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-plus me-1"></i>
            Datos del Cliente
        </div>
        <div class="card-body">
            <form action="store.php" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre_cliente" class="form-label">Nombre del Cliente</label>
                        <input type="text" class="form-control" id="nombre_cliente" name="nombre_cliente" required>
                    </div>
                    <div class="col-md-6">
                        <label for="rnc_cliente" class="form-label">RNC (Opcional)</label>
                        <input type="text" class="form-control" id="rnc_cliente" name="rnc_cliente">
                    </div>
                    <div class="col-md-6">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado" required>
                            <option value="Activo" selected>Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Guardar Cliente</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
