<?php
// positions/edit.php
// Formulario para editar una posición.

require_once '../auth.php';
require_login();
require_role('Admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?status=error&message=ID no válido.');
    exit();
}
$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM Posiciones WHERE id = ?");
$stmt->execute([$id]);
$posicion = $stmt->fetch();

if (!$posicion) {
    header('Location: index.php?status=error&message=Posición no encontrada.');
    exit();
}

// Obtener departamentos para el dropdown
$departamentos = $pdo->query("SELECT id, nombre_departamento FROM Departamentos WHERE estado = 'Activo' ORDER BY nombre_departamento")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Posición</h1>

<div class="card">
    <div class="card-header">
        Modificar Posición
    </div>
    <div class="card-body">
        <form action="update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($posicion['id']); ?>">
            <div class="mb-3">
                <label for="nombre_posicion" class="form-label">Nombre de la Posición</label>
                <input type="text" class="form-control" id="nombre_posicion" name="nombre_posicion" value="<?php echo htmlspecialchars($posicion['nombre_posicion']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="id_departamento" class="form-label">Departamento</label>
                <select class="form-select" id="id_departamento" name="id_departamento" required>
                    <?php foreach ($departamentos as $departamento): ?>
                        <option value="<?php echo htmlspecialchars($departamento['id']); ?>" <?php echo ($posicion['id_departamento'] == $departamento['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($departamento['nombre_departamento']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
