<?php
// bancos/create.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');
require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Añadir Nuevo Banco</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Bancos</a></li>
        <li class="breadcrumb-item active">Añadir Banco</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-plus-circle me-1"></i>
            Datos del Banco
        </div>
        <div class="card-body">
            <form action="store.php" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre_banco" class="form-label">Nombre del Banco</label>
                        <input type="text" class="form-control" id="nombre_banco" name="nombre_banco" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Guardar Banco</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
