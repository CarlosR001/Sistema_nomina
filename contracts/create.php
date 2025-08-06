<?php
// contracts/create.php - v2.0 (con Permite Horas Extras y Horarios)

require_once '../auth.php';
require_login();
require_role('Admin');

if (!isset($_GET['employee_id']) || !is_numeric($_GET['employee_id'])) {
    die("Error: ID de empleado no válido.");
}
$employee_id = $_GET['employee_id'];

$posiciones = $pdo->query("SELECT id, nombre_posicion FROM Posiciones ORDER BY nombre_posicion")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Añadir Nuevo Contrato</h1>

<div class="card">
    <div class="card-header">Configurar los detalles del contrato</div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <input type="hidden" name="id_empleado" value="<?php echo $employee_id; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="id_posicion" class="form-label">Posición</label>
                    <select class="form-select" id="id_posicion" name="id_posicion" required>
                        <option value="">Seleccione una posición...</option>
                        <?php foreach ($posiciones as $posicion): ?>
                            <option value="<?php echo htmlspecialchars($posicion['id']); ?>"><?php echo htmlspecialchars($posicion['nombre_posicion']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="tipo_contrato" class="form-label">Tipo de Contrato</label>
                    <select class="form-select" id="tipo_contrato" name="tipo_contrato" required>
                        <option value="Indefinido">Indefinido</option>
                        <option value="Temporal">Temporal</option>
                        <option value="Por Obra o Servicio">Por Obra o Servicio</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                </div>
                <div class="col-md-6">
                    <label for="fecha_fin" class="form-label">Fecha de Fin (Opcional)</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin">
                </div>

                <div class="col-md-6">
                    <label for="tipo_nomina" class="form-label">Tipo de Nómina</label>
                    <select class="form-select" id="tipo_nomina" name="tipo_nomina" required>
                        <option value="Administrativa">Administrativa</option>
                        <option value="Inspectores">Inspectores</option>
                        <option value="Directiva">Directiva</option>
                    </select>
                </div>
                 <div class="col-md-6">
                    <label for="frecuencia_pago" class="form-label">Frecuencia de Pago</label>
                    <select class="form-select" id="frecuencia_pago" name="frecuencia_pago" required>
                        <option value="Quincenal">Quincenal</option>
                        <option value="Mensual">Mensual</option>
                        <option value="Semanal">Semanal</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="salario_mensual_bruto" class="form-label">Salario Mensual Bruto (si aplica)</label>
                    <input type="number" step="0.01" class="form-control" id="salario_mensual_bruto" name="salario_mensual_bruto">
                </div>

                <div class="col-md-6">
                    <label for="tarifa_por_hora" class="form-label">Tarifa por Hora (si aplica)</label>
                    <input type="number" step="0.01" class="form-control" id="tarifa_por_hora" name="tarifa_por_hora">
                </div>

                <div class="col-md-6">
                    <label for="horario_entrada" class="form-label">Horario Normal: Entrada</label>
                    <input type="time" class="form-control" id="horario_entrada" name="horario_entrada" value="08:00">
                </div>
                 <div class="col-md-6">
                    <label for="horario_salida" class="form-label">Horario Normal: Salida</label>
                    <input type="time" class="form-control" id="horario_salida" name="horario_salida" value="17:00">
                </div>

                <div class="col-md-6">
                    <label for="estado_contrato" class="form-label">Estado del Contrato</label>
                     <select class="form-select" name="estado_contrato" required>
                        <option value="Vigente">Vigente</option>
                        <option value="Finalizado">Finalizado</option>
                        <option value="Cancelado">Cancelado</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <h6>Permisos Especiales</h6>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="permite_horas_extras" value="1" id="permite_horas_extras">
                <label class="form-check-label" for="permite_horas_extras">
                    Permitir que este contrato genere Horas Extras (Personal Fijo)
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Guardar Contrato</button>
            <a href="index.php?employee_id=<?php echo $employee_id; ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
