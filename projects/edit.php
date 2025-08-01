<?php
// projects/edit.php
// Formulario para editar un proyecto.

require_once '../auth.php';
require_login();
require_role('Admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?status=error&message=ID no válido.');
    exit();
}
$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM Proyectos WHERE id = ?");
$stmt->execute([$id]);
$proyecto = $stmt->fetch();

if (!$proyecto) {
    header('Location: index.php?status=error&message=Proyecto no encontrado.');
    exit();
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Proyecto</h1>

<div class="card">
    <div class="card-header">
        Modificar Proyecto
    </div>
    <div class="card-body">
        <form action="update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($proyecto['id']); ?>">
            <div class="mb-3">
                <label for="nombre_proyecto" class="form-label">Nombre del Proyecto</label>
                <input type="text" class="form-control" id="nombre_proyecto" name="nombre_proyecto" value="<?php echo htmlspecialchars($proyecto['nombre_proyecto']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="codigo_proyecto" class="form-label">Código del Proyecto</label>
                <input type="text" class="form-control" id="codigo_proyecto" name="codigo_proyecto" value="<?php echo htmlspecialchars($proyecto['codigo_proyecto']); ?>">
            </div>
            <div class="mb-3">
                <label for="estado_proyecto" class="form-label">Estado</label>
                <select class="form-select" id="estado_proyecto" name="estado_proyecto">
                    <option value="Activo" <?php echo ($proyecto['estado_proyecto'] === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                    <option value="Cerrado" <?php echo ($proyecto['estado_proyecto'] === 'Cerrado') ? 'selected' : ''; ?>>Cerrado</option>
                    <option value="Pausado" <?php echo ($proyecto['estado_proyecto'] === 'Pausado') ? 'selected' : ''; ?>>Pausado</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
