<?php
// contracts/edit.php - v2.0 con Lógica de Inspector

require_once '../auth.php';
require_login();
require_permission('empleados.gestionar');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . 'employees/index.php?status=error&message=ID de contrato no válido.');
    exit();
}
$id_contrato = $_GET['id'];

// Obtener los datos del contrato a editar
$stmt_contrato = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
$stmt_contrato->execute([$id_contrato]);
$contrato = $stmt_contrato->fetch();

if (!$contrato) {
    header('Location: ' . BASE_URL . 'employees/index.php?status=error&message=Contrato no encontrado.');
    exit();
}

// Obtener listas para los dropdowns
$posiciones = $pdo->query("SELECT id, nombre_posicion,
    CASE WHEN LOWER(nombre_posicion) LIKE '%inspector%' THEN 1 ELSE 0 END as es_inspector
    FROM posiciones ORDER BY nombre_posicion")->fetchAll();

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
                    <select class="form-select" id="id_posicion" name="id_posicion" required>
                        <?php foreach($posiciones as $posicion): ?>
                            <option value="<?php echo $posicion['id']; ?>" 
                                    data-es-inspector="<?php echo $posicion['es_inspector']; ?>"
                                    <?php echo $contrato['id_posicion'] == $posicion['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($posicion['nombre_posicion']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="tipo_nomina" class="form-label">Tipo de Nómina</label>
                    <select class="form-select" id="tipo_nomina" name="tipo_nomina" required>
                        <option value="Administrativa" <?php echo $contrato['tipo_nomina'] == 'Administrativa' ? 'selected' : ''; ?>>Administrativa</option>
                        <option value="Directiva" <?php echo $contrato['tipo_nomina'] == 'Directiva' ? 'selected' : ''; ?>>Directiva</option>
                        <option value="Inspectores" <?php echo $contrato['tipo_nomina'] == 'Inspectores' ? 'selected' : ''; ?>>Inspectores</option>
                    </select>
                </div>

                <div class="col-md-6" id="salario_mensual_bruto_div">
                    <label for="salario_mensual_bruto" class="form-label">Salario Mensual Bruto</label>
                    <input type="number" step="0.01" class="form-control" name="salario_mensual_bruto" id="salario_mensual_bruto" value="<?php echo htmlspecialchars($contrato['salario_mensual_bruto']); ?>">
                </div>

                <div class="col-md-6" id="tarifa_por_hora_div">
                    <label for="tarifa_por_hora" class="form-label">Tarifa por Hora</label>
                    <input type="number" step="0.01" class="form-control" name="tarifa_por_hora" id="tarifa_por_hora" value="<?php echo htmlspecialchars($contrato['tarifa_por_hora']); ?>">
                </div>

                <!-- Otros campos del formulario... -->
                <div class="col-md-6">
                    <label for="tipo_contrato" class="form-label">Tipo de Contrato</label>
                    <select class="form-select" name="tipo_contrato" required>
                        <option value="Indefinido" <?php echo $contrato['tipo_contrato'] == 'Indefinido' ? 'selected' : ''; ?>>Indefinido</option>
                        <option value="Temporal" <?php echo $contrato['tipo_contrato'] == 'Temporal' ? 'selected' : ''; ?>>Temporal</option>
                        <option value="Por Obra o Servicio" <?php echo $contrato['tipo_contrato'] == 'Por Obra o Servicio' ? 'selected' : ''; ?>>Por Obra o Servicio</option>
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
                            <label for="horario_entrada" class="form-label">Horario de Entrada</label>
                            <input type="time" class="form-control" id="horario_entrada" name="horario_entrada" value="<?php echo htmlspecialchars($contrato['horario_entrada']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="horario_salida" class="form-label">Horario de Salida</label>
                            <input type="time" class="form-control" id="horario_salida" name="horario_salida" value="<?php echo htmlspecialchars($contrato['horario_salida']); ?>">
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
                    <label for="estado_contrato" class="form-label">Estado del Contrato</label>
                     <select class="form-select" name="estado_contrato" required>
                        <option value="Vigente" <?php echo $contrato['estado_contrato'] == 'Vigente' ? 'selected' : ''; ?>>Vigente</option>
                        <option value="Finalizado" <?php echo $contrato['estado_contrato'] == 'Finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                        <option value="Cancelado" <?php echo $contrato['estado_contrato'] == 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
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
            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary">Actualizar Contrato</button>
            <a href="index.php?employee_id=<?php echo htmlspecialchars($contrato['id_empleado']); ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const posicionSelect = document.getElementById('id_posicion');
    const tipoNominaSelect = document.getElementById('tipo_nomina');
    const salarioDiv = document.getElementById('salario_mensual_bruto_div');
    const tarifaDiv = document.getElementById('tarifa_por_hora_div');
    const salarioInput = document.getElementById('salario_mensual_bruto');
    const tarifaInput = document.getElementById('tarifa_por_hora');

    function toggleFields(isInspector) {
        if (isInspector) {
            // Es Inspector
            tipoNominaSelect.value = 'Inspectores';
            tipoNominaSelect.disabled = true;
            
            salarioDiv.style.display = 'none';
            salarioInput.required = false;
            
            tarifaDiv.style.display = 'block';
            tarifaInput.required = true;

        } else {
            // No es Inspector (Admin/Directiva)
            tipoNominaSelect.disabled = false;
            if (tipoNominaSelect.value === 'Inspectores') {
                tipoNominaSelect.value = 'Administrativa';
            }
            
            salarioDiv.style.display = 'block';
            salarioInput.required = true;
            
            tarifaDiv.style.display = 'none';
            tarifaInput.required = false;
        }

        for (let option of tipoNominaSelect.options) {
            if (option.value === 'Inspectores') {
                option.disabled = !isInspector;
            } else {
                option.disabled = isInspector;
            }
        }
    }

    posicionSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const esInspector = selectedOption.getAttribute('data-es-inspector') === '1';
        toggleFields(esInspector);
    });

    // --- Lógica para la carga inicial en la página de edición ---
    const initialOption = posicionSelect.options[posicionSelect.selectedIndex];
    if (initialOption && initialOption.value) {
        const esInspector = initialOption.getAttribute('data-es-inspector') === '1';
        toggleFields(esInspector);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
