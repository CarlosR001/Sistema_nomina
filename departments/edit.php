<?php
// departments/edit.php
// Formulario para editar un departamento.

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?status=error&message=ID no válido.');
    exit();
}
$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM departamentos WHERE id = ?");
$stmt->execute([$id]);
$departamento = $stmt->fetch();

if (!$departamento) {
    header('Location: index.php?status=error&message=Departamento no encontrado.');
    exit();
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Departamento</h1>

<div class="card">
    <div class="card-header">
        Modificar Departamento
    </div>
    <div class="card-body">
        <form action="update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($departamento['id']); ?>">
            <div class="mb-3">
                <label for="nombre_departamento" class="form-label">Nombre del Departamento</label>
                <input type="text" class="form-control" id="nombre_departamento" name="nombre_departamento" value="<?php echo htmlspecialchars($departamento['nombre_departamento']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="estado" class="form-label">Estado</label>
                <select class="form-select" id="estado" name="estado">
                    <option value="Activo" <?php echo ($departamento['estado'] === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                    <option value="Inactivo" <?php echo ($departamento['estado'] === 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
