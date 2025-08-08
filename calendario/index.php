<?php
// calendario/index.php - v1.1
// Añade acciones de Editar y Eliminar a la tabla de feriados.

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

$feriados = $pdo->query("SELECT * FROM CalendarioLaboralRD ORDER BY fecha DESC")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Gestión de Calendario Laboral (Días Feriados)</h1>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Añadir Nuevo Día Feriado</div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row align-items-end">
                <div class="col-md-5">
                    <label for="fecha" class="form-label">Fecha</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" required>
                </div>
                <div class="col-md-5">
                    <label for="descripcion" class="form-label">Descripción del Feriado</label>
                    <input type="text" class="form-control" id="descripcion" name="descripcion" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Añadir Feriado</button>
                </div>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped">
    <thead class="table-light">
        <tr>
            <th>Fecha</th>
            <th>Descripción</th>
            <th class="text-center">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($feriados)): ?>
            <tr><td colspan="3" class="text-center">No hay días feriados registrados.</td></tr>
        <?php else: ?>
            <?php foreach($feriados as $feriado): ?>
            <tr>
                <td><?php echo htmlspecialchars($feriado['fecha']); ?></td>
                <td><?php echo htmlspecialchars($feriado['descripcion']); ?></td>
                <td class="text-center">
                    <a href="edit.php?fecha=<?php echo htmlspecialchars($feriado['fecha']); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este día feriado?');">
                        <input type="hidden" name="fecha" value="<?php echo htmlspecialchars($feriado['fecha']); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>
