<?php
// contracts/edit_ingreso.php
// Formulario para editar un ingreso recurrente.

require_once '../auth.php';
require_login();
require_permission('empleados.gestionar');

if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['employee_id']) || !is_numeric($_GET['employee_id'])) {
    header('Location: ../employees/index.php?status=error&message=Datos insuficientes para editar el ingreso.');
    exit();
}

$id_ingreso = $_GET['id'];
$employee_id = $_GET['employee_id'];

// Obtener los datos del ingreso a editar
$stmt_ingreso = $pdo->prepare("
    SELECT ir.id, ir.monto_ingreso, ir.quincena_aplicacion, cn.descripcion_publica
    FROM ingresosrecurrentes ir
    JOIN conceptosnomina cn ON ir.id_concepto_ingreso = cn.id
    WHERE ir.id = ?
");
$stmt_ingreso->execute([$id_ingreso]);
$ingreso = $stmt_ingreso->fetch();

if (!$ingreso) {
    header('Location: ' . BASE_URL . 'contracts/index.php?employee_id=' . $employee_id . '&status=error&message=Ingreso no encontrado.');
    exit();
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Editar Ingreso Recurrente</h2>
    <p><strong>Ingreso:</strong> <?php echo htmlspecialchars($ingreso['descripcion_publica']); ?></p>

    <form action="update_ingreso.php" method="POST">
        <input type="hidden" name="id_ingreso" value="<?php echo $ingreso['id']; ?>">
        <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">

        <div class="form-group">
            <label for="monto_ingreso">Monto del Ingreso</label>
            <input type="number" step="0.01" class="form-control" id="monto_ingreso" name="monto_ingreso" value="<?php echo htmlspecialchars($ingreso['monto_ingreso']); ?>" required>
        </div>

        <div class="form-group">
            <label for="quincena_aplicacion">Aplicar en</label>
            <select class="form-control" id="quincena_aplicacion" name="quincena_aplicacion" required>
                <option value="0" <?php echo ($ingreso['quincena_aplicacion'] == 0) ? 'selected' : ''; ?>>Ambas Quincenas</option>
                <option value="1" <?php echo ($ingreso['quincena_aplicacion'] == 1) ? 'selected' : ''; ?>>Primera Quincena</option>
                <option value="2" <?php echo ($ingreso['quincena_aplicacion'] == 2) ? 'selected' : ''; ?>>Segunda Quincena</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Actualizar Ingreso</button>
        <a href="index.php?employee_id=<?php echo $employee_id; ?>" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
