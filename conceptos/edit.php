<?php
// conceptos/edit.php

require_once '../auth.php';
require_login();
require_role('Admin');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php?status=error&message=ID de concepto no proporcionado.');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM ConceptosNomina WHERE id = ?");
$stmt->execute([$id]);
$concepto = $stmt->fetch();

if (!$concepto) {
    header('Location: index.php?status=error&message=Concepto no encontrado.');
    exit();
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Concepto de Nómina</h1>

<div class="card">
    <div class="card-header">Modificando: <strong><?php echo htmlspecialchars($concepto['descripcion_publica']); ?></strong></div>
    <div class="card-body">
        <form action="update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($concepto['id']); ?>">

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="codigo_concepto" class="form-label">Código del Concepto</label>
                    <input type="text" class="form-control" id="codigo_concepto" name="codigo_concepto" value="<?php echo htmlspecialchars($concepto['codigo_concepto']); ?>" required>
                </div>
                <div class="col-md-9 mb-3">
                    <label for="descripcion_publica" class="form-label">Descripción Pública</label>
                    <input type="text" class="form-control" id="descripcion_publica" name="descripcion_publica" value="<?php echo htmlspecialchars($concepto['descripcion_publica']); ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="tipo_concepto" class="form-label">Tipo</label>
                    <select class="form-select" id="tipo_concepto" name="tipo_concepto">
                        <option value="Ingreso" <?php echo $concepto['tipo_concepto'] === 'Ingreso' ? 'selected' : ''; ?>>Ingreso</option>
                        <option value="Deducción" <?php echo $concepto['tipo_concepto'] === 'Deducción' ? 'selected' : ''; ?>>Deducción</option>
                        <option value="Base de Cálculo" <?php echo $concepto['tipo_concepto'] === 'Base de Cálculo' ? 'selected' : ''; ?>>Base de Cálculo</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="afecta_tss" class="form-label">Afecta TSS</label>
                    <select class="form-select" id="afecta_tss" name="afecta_tss">
                        <option value="1" <?php echo $concepto['afecta_tss'] ? 'selected' : ''; ?>>Sí</option>
                        <option value="0" <?php echo !$concepto['afecta_tss'] ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="afecta_isr" class="form-label">Afecta ISR</label>
                    <select class="form-select" id="afecta_isr" name="afecta_isr">
                        <option value="1" <?php echo $concepto['afecta_isr'] ? 'selected' : ''; ?>>Sí</option>
                        <option value="0" <?php echo !$concepto['afecta_isr'] ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-success">Actualizar Concepto</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
