<?php
// calendario/edit.php
// Formulario para editar un día feriado.

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if (!isset($_GET['fecha'])) {
    header('Location: index.php?status=error&message=Fecha no proporcionada.');
    exit();
}
$fecha = $_GET['fecha'];

$stmt = $pdo->prepare("SELECT * FROM CalendarioLaboralRD WHERE fecha = ?");
$stmt->execute([$fecha]);
$feriado = $stmt->fetch();

if (!$feriado) {
    header('Location: index.php?status=error&message=Día feriado no encontrado.');
    exit();
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Día Feriado</h1>

<div class="card">
    <div class="card-header">
        Modificar Feriado
    </div>
    <div class="card-body">
        <form action="update.php" method="POST">
            <div class="mb-3">
                <label for="fecha" class="form-label">Fecha</label>
                <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo htmlspecialchars($feriado['fecha']); ?>" readonly>
                <div class="form-text">La fecha no se puede modificar. Para cambiar la fecha, elimina este registro y crea uno nuevo.</div>
            </div>
            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción del Feriado</label>
                <input type="text" class="form-control" id="descripcion" name="descripcion" value="<?php echo htmlspecialchars($feriado['descripcion']); ?>" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
