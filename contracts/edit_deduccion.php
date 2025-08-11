<?php
// contracts/edit_deduccion.php
// Formulario para editar una deducción recurrente.

require_once '../auth.php';
require_login();
require_permission('empleados.gestionar');

// Validar que se hayan proporcionado los IDs necesarios
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['employee_id']) || !is_numeric($_GET['employee_id'])) {
    header('Location: ../employees/index.php?status=error&message=Datos insuficientes para editar la deducción.');
    exit();
}

$id_deduccion = $_GET['id'];
$employee_id = $_GET['employee_id'];

// Obtener los datos de la deducción a editar
$stmt_deduccion = $pdo->prepare("
    SELECT dr.id, dr.monto_deduccion, dr.quincena_aplicacion, cn.descripcion_publica
    FROM deduccionesrecurrentes dr
    JOIN conceptosnomina cn ON dr.id_concepto_deduccion = cn.id
    WHERE dr.id = ?
");
$stmt_deduccion->execute([$id_deduccion]);
$deduccion = $stmt_deduccion->fetch();

if (!$deduccion) {
    header('Location: ' . BASE_URL . 'contracts/index.php?employee_id=' . $employee_id . '&status=error&message=Deducción no encontrada.');
    exit();
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Deducción Recurrente</h1>

<div class="card">
    <div class="card-header">
        Modificar deducción: <strong><?php echo htmlspecialchars($deduccion['descripcion_publica']); ?></strong>
    </div>
    <div class="card-body">
        <form action="update_deduccion.php" method="POST">
            <input type="hidden" name="id_deduccion" value="<?php echo htmlspecialchars($deduccion['id']); ?>">
            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee_id); ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="monto_deduccion" class="form-label">Monto de la Deducción</label>
                    <input type="number" step="0.01" class="form-control" name="monto_deduccion" value="<?php echo htmlspecialchars($deduccion['monto_deduccion']); ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="quincena_aplicacion" class="form-label">Frecuencia de Aplicación</label>
                    <select class="form-select" name="quincena_aplicacion" required>
                        <option value="0" <?php echo $deduccion['quincena_aplicacion'] == 0 ? 'selected' : ''; ?>>Siempre</option>
                        <option value="1" <?php echo $deduccion['quincena_aplicacion'] == 1 ? 'selected' : ''; ?>>Solo 1ra Quincena</option>
                        <option value="2" <?php echo $deduccion['quincena_aplicacion'] == 2 ? 'selected' : ''; ?>>Solo 2da Quincena</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary">Actualizar Deducción</button>
            <a href="index.php?employee_id=<?php echo htmlspecialchars($employee_id); ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
