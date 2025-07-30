<?php
// contracts/create.php

require_once '../auth.php'; // Carga el sistema de autenticación
require_login(); // Asegura que el usuario esté logueado
require_role('Administrador'); // Solo Administradores pueden acceder a esta sección

// La conexión $pdo ya está disponible a través de auth.php

// Validar que se reciba un ID de empleado
if (!isset($_GET['employee_id']) || !is_numeric($_GET['employee_id'])) {
    die("Error: ID de empleado no válido.");
}
$employee_id = $_GET['employee_id'];

// Obtener todas las posiciones para el dropdown
$posiciones = $pdo->query("SELECT id, nombre_posicion FROM Posiciones ORDER BY nombre_posicion")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Añadir Nuevo Contrato</h1>

<form action="store.php" method="POST">
    <input type="hidden" name="id_empleado" value="<?php echo $employee_id; ?>">

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="id_posicion" class="form-label">Posición</label>
            <select class="form-select" id="id_posicion" name="id_posicion" required>
                <option value="">Seleccione una posición...</option>
                <?php foreach ($posiciones as $posicion): ?>
                    <option value="<?php echo $posicion['id']; ?>"><?php echo htmlspecialchars($posicion['nombre_posicion']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label for="tipo_contrato" class="form-label">Tipo de Contrato</label>
            <select class="form-select" id="tipo_contrato" name="tipo_contrato" required>
                <option value="Indefinido">Indefinido</option>
                <option value="Temporal">Temporal</option>
                <option value="Por Obra o Servicio">Por Obra o Servicio</option>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="tipo_nomina" class="form-label">Tipo de Nómina</label>
            <select class="form-select" id="tipo_nomina" name="tipo_nomina" required>
                <option value="Administrativa">Administrativa</option>
                <option value="Inspectores">Inspectores</option>
                <option value="Directiva">Directiva</option>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label for="salario_mensual_bruto" class="form-label">Salario Mensual Bruto (si aplica)</label>
            <input type="number" step="0.01" class="form-control" id="salario_mensual_bruto" name="salario_mensual_bruto">
        </div>
        <div class="col-md-4 mb-3">
            <label for="tarifa_por_hora" class="form-label">Tarifa por Hora (si aplica)</label>
            <input type="number" step="0.01" class="form-control" id="tarifa_por_hora" name="tarifa_por_hora">
        </div>
        <div class="col-md-4 mb-3">
            <label for="frecuencia_pago" class="form-label">Frecuencia de Pago</label>
            <select class="form-select" id="frecuencia_pago" name="frecuencia_pago" required>
                <option value="Quincenal">Quincenal</option>
                <option value="Mensual">Mensual</option>
                <option value="Semanal">Semanal</option>
            </select>
        </div>
    </div>
    
    <button type="submit" class="btn btn-success">Guardar Contrato</button>
    <a href="index.php?employee_id=<?php echo $employee_id; ?>" class="btn btn-secondary">Cancelar</a>
</form>

<?php
require_once '../includes/footer.php';
?>