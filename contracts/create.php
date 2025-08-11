<?php
// contracts/create.php - v2.0 con Lógica de Inspector

require_once '../auth.php';
require_login();
require_permission('empleados.gestionar');

if (!isset($_GET['employee_id'])) {
    header('Location: ../employees/index.php');
    exit();
}
$employee_id = $_GET['employee_id'];

// Obtener listas para los dropdowns. Añadimos una lógica para identificar posiciones de inspector.
$posiciones = $pdo->query("SELECT id, nombre_posicion, 
    CASE WHEN LOWER(nombre_posicion) LIKE '%inspector%' THEN 1 ELSE 0 END as es_inspector 
    FROM posiciones ORDER BY nombre_posicion")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Añadir Nuevo Contrato</h1>

<div class="card">
    <div class="card-header">Detalles del nuevo contrato</div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee_id); ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="id_posicion" class="form-label">Posición</label>
                    <select class="form-select" id="id_posicion" name="id_posicion" required>
                        <option value="">Seleccione una posición...</option>
                        <?php foreach($posiciones as $posicion): ?>
                            <option value="<?php echo $posicion['id']; ?>" data-es-inspector="<?php echo $posicion['es_inspector']; ?>">
                                <?php echo htmlspecialchars($posicion['nombre_posicion']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="tipo_nomina" class="form-label">Tipo de Nómina</label>
                    <select class="form-select" id="tipo_nomina" name="tipo_nomina" required>
                        <!-- Las opciones se deshabilitarán dinámicamente -->
                        <option value="Administrativa">Administrativa</option>
                        <option value="Directiva">Directiva</option>
                        <option value="Inspectores">Inspectores</option>
                    </select>
                </div>
                
                <div class="col-md-6" id="salario_mensual_bruto_div">
                    <label for="salario_mensual_bruto" class="form-label">Salario Mensual Bruto</label>
                    <input type="number" step="0.01" class="form-control" name="salario_mensual_bruto" id="salario_mensual_bruto">
                </div>

                <div class="col-md-6" id="tarifa_por_hora_div" style="display: none;">
                    <label for="tarifa_por_hora" class="form-label">Tarifa por Hora</label>
                    <input type="number" step="0.01" class="form-control" name="tarifa_por_hora" id="tarifa_por_hora">
                </div>
                
                <!-- Otros campos del formulario... -->
                <div class="col-md-6">
                    <label for="tipo_contrato" class="form-label">Tipo de Contrato</label>
                    <select class="form-select" name="tipo_contrato" required>
                        <option value="Indefinido">Indefinido</option>
                        <option value="Temporal">Temporal</option>
                        <option value="Por Obra o Servicio">Por Obra o Servicio</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="frecuencia_pago" class="form-label">Frecuencia de Pago</label>
                    <select class="form-select" name="frecuencia_pago" required>
                        <option value="Quincenal">Quincenal</option>
                        <option value="Semanal">Semanal</option>
                        <option value="Mensual">Mensual</option>
                    </select>
                </div>
                <div class="col-md-6">
                            <label for="horario_entrada" class="form-label">Horario de Entrada</label>
                            <input type="time" class="form-control" id="horario_entrada" name="horario_entrada" value="08:00">
                        </div>
                        <div class="col-md-6">
                            <label for="horario_salida" class="form-label">Horario de Salida</label>
                            <input type="time" class="form-control" id="horario_salida" name="horario_salida" value="17:00">
                        </div>


                <div class="col-md-6">
                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" required>
                </div>

                <div class="col-md-6">
                    <label for="fecha_fin" class="form-label">Fecha de Fin (Opcional)</label>
                    <input type="date" class="form-control" name="fecha_fin">
                </div>
                
                 <div class="col-md-12 mt-4">
                    <h6>Permisos Especiales</h6>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="permite_horas_extras" value="1" id="permite_horas_extras">
                        <label class="form-check-label" for="permite_horas_extras">
                            Permitir que este contrato genere Horas Extras (Personal Fijo)
                        </label>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary">Guardar Contrato</button>
            <a href="index.php?employee_id=<?php echo htmlspecialchars($employee_id); ?>" class="btn btn-secondary">Cancelar</a>
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
            tipoNominaSelect.disabled = true; // No se puede cambiar
            
            salarioDiv.style.display = 'none';
            salarioInput.required = false;
            salarioInput.value = '';
            
            tarifaDiv.style.display = 'block';
            tarifaInput.required = true;

        } else {
            // No es Inspector (Admin/Directiva)
            tipoNominaSelect.disabled = false; // Se puede elegir
            if (tipoNominaSelect.value === 'Inspectores') {
                tipoNominaSelect.value = 'Administrativa'; // Valor por defecto
            }
            
            salarioDiv.style.display = 'block';
            salarioInput.required = true;
            
            tarifaDiv.style.display = 'none';
            tarifaInput.required = false;
            tarifaInput.value = '';
        }
        
        // Habilitar/deshabilitar opciones en el select de nómina
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

    // Estado inicial por si se vuelve a la página y hay algo seleccionado
    const initialOption = posicionSelect.options[posicionSelect.selectedIndex];
    if (initialOption && initialOption.value) {
        const esInspector = initialOption.getAttribute('data-es-inspector') === '1';
        toggleFields(esInspector);
    }
});
</script>


<?php require_once '../includes/footer.php'; ?>
