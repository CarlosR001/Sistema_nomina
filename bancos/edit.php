<?php
// bancos/edit.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

$banco_id = $_GET['id'] ?? null;
if (!$banco_id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM bancos WHERE id = ?");
$stmt->execute([$banco_id]);
$banco = $stmt->fetch();

if (!$banco) {
    redirect_with_error('index.php', 'Banco no encontrado.');
    exit;
}

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Editar Banco</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Bancos</a></li>
        <li class="breadcrumb-item active">Editar Banco</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-edit me-1"></i>
            Datos del Banco
        </div>
        <div class="card-body">
            <form action="update.php" method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($banco['id']); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre_banco" class="form-label">Nombre del Banco</label>
                        <input type="text" class="form-control" id="nombre_banco" name="nombre_banco" value="<?php echo htmlspecialchars($banco['nombre_banco']); ?>" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Actualizar Banco</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
