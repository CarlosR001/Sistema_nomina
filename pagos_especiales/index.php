<?php
// pagos_especiales/index.php - v2.0 (Multi-línea)

require_once '../auth.php';
require_login();
require_role('Admin');

$empleados = $pdo->query("SELECT e.id, e.nombres, e.primer_apellido, e.cedula FROM Empleados e JOIN Contratos c ON e.id = c.id_empleado WHERE c.estado_contrato = 'Vigente' ORDER BY e.nombres")->fetchAll(PDO::FETCH_ASSOC);
$conceptos_options = $pdo->query("SELECT id, descripcion_publica FROM ConceptosNomina WHERE tipo_concepto = 'Ingreso' AND codigo_concepto LIKE 'ING-%' ORDER BY descripcion_publica")->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<h1 class="mb-4">Procesar Pago Especial</h1>

<div class="card">
    <div class="card-header">
        <h5>Crear Nuevo Pago Fuera de Ciclo (Multi-concepto)</h5>
    </div>
    <div class="card-body">
        <form action="procesar_pago.php" method="POST" id="form-pago-especial" onsubmit="return validarFormulario();">
            <!-- Sección de Datos Generales -->
            <input type="hidden" name="payment_type" value="manual">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="id_empleado" class="form-label">Empleado</label>
                    <select class="form-select" id="id_empleado" name="id_empleado" required>
                        <option value="">Seleccione un empleado...</option>
                        <?php foreach ($empleados as $empleado): ?>
                            <option value="<?php echo $empleado['id']; ?>"><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="fecha_pago" class="form-label">Fecha Efectiva del Pago</label>
                    <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <!-- Sección de Líneas de Pago Dinámicas -->
            <h5 class="mt-4">Conceptos a Pagar</h5>
            <div id="lineas-pago-container">
                <!-- La primera línea de pago se genera aquí -->
                <div class="row g-3 align-items-center mb-2 linea-pago">
                    <div class="col-md-7">
                        <select class="form-select" name="conceptos[id][]" required>
                            <option value="">Seleccione un concepto...</option>
                            <?php foreach ($conceptos_options as $concepto): ?>
                                <option value="<?php echo $concepto['id']; ?>"><?php echo htmlspecialchars($concepto['descripcion_publica']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="number" step="0.01" class="form-control" name="conceptos[monto][]" placeholder="Monto" required>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-danger" onclick="eliminarLinea(this)" disabled>&times;</button>
                    </div>
                </div>
            </div>
            
            <button type="button" id="btn-anadir-linea" class="btn btn-secondary mt-2">Añadir Otro Concepto</button>
            <hr>
            <button type="submit" class="btn btn-primary mt-3">Procesar Pago Especial</button>
        </form>
    </div>
</div>

<script>
document.getElementById('btn-anadir-linea').addEventListener('click', function() {
    const container = document.getElementById('lineas-pago-container');
    const primeraLinea = container.querySelector('.linea-pago');
    const nuevaLinea = primeraLinea.cloneNode(true);
    
    // Limpiar valores y habilitar el botón de eliminar
    nuevaLinea.querySelector('select').selectedIndex = 0;
    nuevaLinea.querySelector('input').value = '';
    const btnEliminar = nuevaLinea.querySelector('button');
    btnEliminar.disabled = false;
    
    container.appendChild(nuevaLinea);
});

function eliminarLinea(btn) {
    btn.closest('.linea-pago').remove();
}

function validarFormulario() {
    const lineas = document.querySelectorAll('.linea-pago');
    if (lineas.length === 0) {
        alert('Debe añadir al menos una línea de pago.');
        return false;
    }
    if (!confirm('¿Está seguro de que desea procesar este pago especial? Esta acción es irreversible.')) {
        return false;
    }
    return true;
}
</script>

<?php require_once '../includes/footer.php'; ?>
