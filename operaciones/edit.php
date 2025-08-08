<?php
// operaciones/edit.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

$operacion_id = $_GET['id'] ?? null;
if (!$operacion_id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM operaciones WHERE id = ?");
$stmt->execute([$operacion_id]);
$operacion = $stmt->fetch();

if (!$operacion) {
    header('Location: index.php?status=error&message=' . urlencode('Operación no encontrada.'));
    exit;
}

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Editar Operación</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Operaciones</a></li>
        <li class="breadcrumb-item active">Editar Operación</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-edit me-1"></i>
            Datos de la Operación
        </div>
        <div class="card-body">
            <form action="update.php" method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($operacion['id']); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre_operacion" class="form-label">Nombre de la Operación</label>
                        <input type="text" class="form-control" id="nombre_operacion" name="nombre_operacion" value="<?php echo htmlspecialchars($operacion['nombre_operacion']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="descripcion" class="form-label">Descripción (Opcional)</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($operacion['descripcion']); ?></textarea>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Actualizar Operación</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
