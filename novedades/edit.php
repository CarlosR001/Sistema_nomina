<?php
// novedades/edit.php
// Formulario para editar una novedad existente.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?status=error&message=ID de novedad no válido.');
    exit();
}
$id_novedad = $_GET['id'];

// Obtener la novedad a editar
$stmt_novedad = $pdo->prepare("SELECT * FROM NovedadesPeriodo WHERE id = ?");
$stmt_novedad->execute([$id_novedad]);
$novedad = $stmt_novedad->fetch();

if (!$novedad || $novedad['estado_novedad'] !== 'Pendiente') {
    header('Location: index.php?status=error&message=La novedad no se puede editar.');
    exit();
}

// Obtener listas para los dropdowns
$empleados = $pdo->query("SELECT c.id as id_contrato, e.nombres, e.primer_apellido FROM Contratos c JOIN Empleados e ON c.id_empleado = e.id WHERE c.estado_contrato = 'Vigente' ORDER BY e.nombres")->fetchAll();
$conceptos = $pdo->query("SELECT id, descripcion_publica FROM ConceptosNomina WHERE origen_calculo = 'Novedad' ORDER BY descripcion_publica")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Novedad</h1>

<div class="card">
    <div class="card-header">Modificar los detalles de la novedad</div>
    <div class="card-body">
        <form action="update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($novedad['id']); ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="id_contrato" class="form-label">Empleado</label>
                    <select class="form-select" name="id_contrato" required>
                        <?php foreach($empleados as $empleado): ?>
                            <option value="<?php echo htmlspecialchars($empleado['id_contrato']); ?>" <?php echo $novedad['id_contrato'] == $empleado['id_contrato'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="id_concepto" class="form-label">Concepto</label>
                    <select class="form-select" name="id_concepto" required>
                         <?php foreach($conceptos as $concepto): ?>
                            <option value="<?php echo htmlspecialchars($concepto['id']); ?>" <?php echo $novedad['id_concepto'] == $concepto['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($concepto['descripcion_publica']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="monto_valor" class="form-label">Monto</label>
                    <input type="number" step="0.01" class="form-control" name="monto_valor" value="<?php echo htmlspecialchars($novedad['monto_valor']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="periodo_aplicacion" class="form-label">Fecha de Aplicación</label>
                    <input type="date" class="form-control" name="periodo_aplicacion" value="<?php echo htmlspecialchars($novedad['periodo_aplicacion']); ?>" required>
                </div>
                 <div class="col-md-4">
                    <label for="descripcion_adicional" class="form-label">Descripción Adicional</label>
                    <input type="text" class="form-control" name="descripcion_adicional" value="<?php echo htmlspecialchars($novedad['descripcion_adicional']); ?>">
                </div>
            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary">Actualizar Novedad</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
