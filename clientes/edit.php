<?php
// clientes/edit.php

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

$cliente_id = $_GET['id'] ?? null;
if (!$cliente_id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    header('Location: index.php?status=error&message=' . urlencode('Cliente no encontrado.'));
    exit;
}

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Editar Cliente</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Clientes</a></li>
        <li class="breadcrumb-item active">Editar Cliente</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-edit me-1"></i>
            Datos del Cliente
        </div>
        <div class="card-body">
            <form action="update.php" method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($cliente['id']); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre_cliente" class="form-label">Nombre del Cliente</label>
                        <input type="text" class="form-control" id="nombre_cliente" name="nombre_cliente" value="<?php echo htmlspecialchars($cliente['nombre_cliente']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="rnc_cliente" class="form-label">RNC (Opcional)</label>
                        <input type="text" class="form-control" id="rnc_cliente" name="rnc_cliente" value="<?php echo htmlspecialchars($cliente['rnc_cliente']); ?>">
                    </div>
                    <div class="col-md-12">
                        <label for="Adress" class="form-label">Dirección (Opcional)</label>
                        <input type="text" class="form-control" id="Adress" name="Adress" value="<?php echo htmlspecialchars($cliente['Adress'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="Country" class="form-label">País (Opcional)</label>
                        <input type="text" class="form-control" id="Country" name="Country" value="<?php echo htmlspecialchars($cliente['Country'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="Phone_Number" class="form-label">Teléfono (Opcional)</label>
                        <input type="text" class="form-control" id="Phone_Number" name="Phone_Number" value="<?php echo htmlspecialchars($cliente['Phone_Number'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado" required>
                            <option value="Activo" <?php echo ($cliente['estado'] == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="Inactivo" <?php echo ($cliente['estado'] == 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Actualizar Cliente</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
