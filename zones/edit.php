<?php
// zones/edit.php
// Formulario para editar una zona de transporte.

require_once '../auth.php';
require_login();
require_role('Admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?status=error&message=ID no válido.');
    exit();
}
$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM ZonasTransporte WHERE id = ?");
$stmt->execute([$id]);
$zona = $stmt->fetch();

if (!$zona) {
    header('Location: index.php?status=error&message=Zona no encontrada.');
    exit();
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Zona de Transporte</h1>

<div class="card">
    <div class="card-header">
        Modificar Zona
    </div>
    <div class="card-body">
        <form action="update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($zona['id']); ?>">
            <div class="mb-3">
                <label for="nombre_zona" class="form-label">Nombre de la Zona o Muelle</label>
                <input type="text" class="form-control" id="nombre_zona" name="nombre_zona" value="<?php echo htmlspecialchars($zona['nombre_zona_o_muelle']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="monto" class="form-label">Monto Transporte Completo</label>
                <input type="number" step="0.01" class="form-control" id="monto" name="monto" value="<?php echo htmlspecialchars($zona['monto_transporte_completo']); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
