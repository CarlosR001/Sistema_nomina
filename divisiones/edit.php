<?php
// divisiones/edit.php

require_once '../auth.php';
require_login();
require_role(['Admin']);

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM divisiones WHERE id = ?");
$stmt->execute([$id]);
$division = $stmt->fetch();

if (!$division) {
    header('Location: index.php?status=error&message=' . urlencode('División no encontrada.'));
    exit;
}

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Editar División</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Divisiones</a></li>
        <li class="breadcrumb-item active">Editar División</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-edit me-1"></i>Datos de la División</div>
        <div class="card-body">
            <form action="update.php" method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($division['id']); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre_division" class="form-label">Nombre de la División</label>
                        <input type="text" class="form-control" id="nombre_division" name="nombre_division" value="<?php echo htmlspecialchars($division['nombre_division']); ?>" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Actualizar División</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
