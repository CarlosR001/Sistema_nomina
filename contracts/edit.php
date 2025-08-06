<?php
// contracts/edit.php
// Formulario para editar un contrato existente.

require_once '../auth.php';
require_login();
require_role('Admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . 'employees/index.php?status=error&message=ID de contrato no válido.');
    exit();
}
$id_contrato = $_GET['id'];

// Obtener los datos del contrato a editar
$stmt_contrato = $pdo->prepare("SELECT * FROM Contratos WHERE id = ?");
$stmt_contrato->execute([$id_contrato]);
$contrato = $stmt_contrato->fetch();

if (!$contrato) {
    header('Location: ' . BASE_URL . 'employees/index.php?status=error&message=Contrato no encontrado.');
    exit();
}

// Obtener listas para los dropdowns
$posiciones = $pdo->query("SELECT id, nombre_posicion FROM Posiciones ORDER BY nombre_posicion")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Contrato</h1>

<div class="card">
    <div class="card-header">Modificar los detalles del contrato</div>
    <div class="card-body">
        <form action="update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($contrato['id']); ?>">
            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($contrato['id_empleado']); ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="id_posicion" class="form-label">Posición</label>
                    <select class="form-select" name="id_posicion" required>
                        <?php foreach($posiciones as $posicion): ?>
                            <option value="<?php echo $posicion['id']; ?>" <?php echo $contrato['id_posicion'] == $posicion['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($posicion['nombre_posicion']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="tipo_contrato" class="form-label">Tipo de Contrato</label>
                    <select class="form-select" name="tipo_contrato" required>
                        <option value="Indefinido" <?php echo $contrato['tipo_contrato'] == 'Indefinido' ? 'selected' : ''; ?>>Indefinido</option>
                        <option value="Temporal" <?php echo $contrato['tipo_contrato'] == 'Temporal' ? 'selected' : ''; ?>>Temporal</option>
                        <option value="Por Obra o Servicio" <?php echo $contrato['tipo_contrato'] == 'Por Obra o Servicio' ? 'selected' : ''; ?>>Por Obra o Servicio</option>
                    </select>
                </div>
                <div class="col-md-12 mt-4">
                <h6>Permisos Especiales</h6>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permite_horas_extras" value="1" id="permite_horas_extras" <?php echo !empty($contrato['permite_horas_extras']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="permite_horas_extras">
                        Permitir que este contrato genere Horas Extras (Personal Fijo)
                    </label>
                </div>
            </div>

                <div class="col-md-6">
                    <label for="tipo_nomina" class="form-label">Tipo de Nómina</label>
                    <select class="form-select" name="tipo_nomina" required>
                        <option value="Inspectores" <?php echo $contrato['tipo_nomina'] == 'Inspectores' ? 'selected' : ''; ?>>Inspectores</option>
                        <option value="Administrativa" <?php echo $contrato['tipo_nomina'] == 'Administrativa' ? 'selected' : ''; ?>>Administrativa</option>
                        <option value="Directiva" <?php echo $contrato['tipo_nomina'] == 'Directiva' ? 'selected' : ''; ?>>Directiva</option>
                    </select>
                </div>
                
                 <div class="col-md-6">
                    <label for="frecuencia_pago" class="form-label">Frecuencia de Pago</label>
                    <select class="form-select" name="frecuencia_pago" required>
                        <option value="Semanal" <?php echo $contrato['frecuencia_pago'] == 'Semanal' ? 'selected' : ''; ?>>Semanal</option>
                        <option value="Quincenal" <?php echo $contrato['frecuencia_pago'] == 'Quincenal' ? 'selected' : ''; ?>>Quincenal</option>
                        <option value="Mensual" <?php echo $contrato['frecuencia_pago'] == 'Mensual' ? 'selected' : ''; ?>>Mensual</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($contrato['fecha_inicio']); ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="fecha_fin" class="form-label">Fecha de Fin (Opcional)</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($contrato['fecha_fin']); ?>">
                </div>
                
                <div class="col-md-6">
                    <label for="salario_mensual_bruto" class="form-label">Salario Mensual Bruto (si aplica)</label>
                    <input type="number" step="0.01" class="form-control" name="salario_mensual_bruto" value="<?php echo htmlspecialchars($contrato['salario_mensual_bruto']); ?>">
                </div>

                <div class="col-md-6">
                    <label for="tarifa_por_hora" class="form-label">Tarifa por Hora (si aplica)</label>
                    <input type="number" step="0.01" class="form-control" name="tarifa_por_hora" value="<?php echo htmlspecialchars($contrato['tarifa_por_hora']); ?>">
                </div>

                <div class="col-md-6">

                    <label for="estado_contrato" class="form-label">Estado del Contrato</label>
                     <select class="form-select" name="estado_contrato" required>
                        <option value="Vigente" <?php echo $contrato['estado_contrato'] == 'Vigente' ? 'selected' : ''; ?>>Vigente</option>
                        <option value="Finalizado" <?php echo $contrato['estado_contrato'] == 'Finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                        <option value="Cancelado" <?php echo $contrato['estado_contrato'] == 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
               
            </div>

            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary">Actualizar Contrato</button>
            <a href="index.php?employee_id=<?php echo htmlspecialchars($contrato['id_empleado']); ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
