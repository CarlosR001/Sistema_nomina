<?php
// contracts/index.php
require_once '../config/init.php';

// Lógica de seguridad
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

require_once '../includes/header.php';

// Validar que se reciba un ID de empleado
if (!isset($_GET['employee_id']) || !is_numeric($_GET['employee_id'])) {
    echo "<div class='alert alert-danger'>ID de empleado no válido.</div>";
    require_once '../includes/footer.php';
    exit();
}

$employee_id = $_GET['employee_id'];

// Obtener nombre del empleado para el título
$stmt_employee = $pdo->prepare("SELECT nombres, primer_apellido FROM Empleados WHERE id = ?");
$stmt_employee->execute([$employee_id]);
$employee = $stmt_employee->fetch();

if (!$employee) {
    echo "<div class='alert alert-danger'>Empleado no encontrado.</div>";
    require_once '../includes/footer.php';
    exit();
}

// Consulta para obtener los contratos del empleado
$sql = 'SELECT c.id, c.tipo_contrato, c.fecha_inicio, c.fecha_fin, c.salario_mensual_bruto, c.tarifa_por_hora, p.nombre_posicion
        FROM Contratos c
        JOIN Posiciones p ON c.id_posicion = p.id
        WHERE c.id_empleado = ?
        ORDER BY c.fecha_inicio DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute([$employee_id]);
?>

<h1 class="mb-4">Contratos de: <?php echo htmlspecialchars($employee['nombres'] . ' ' . $employee['primer_apellido']); ?></h1>
<a href="create.php?employee_id=<?php echo $employee_id; ?>" class="btn btn-primary mb-3">Añadir Nuevo Contrato</a>

<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>Posición</th>
            <th>Tipo Contrato</th>
            <th>Fecha Inicio</th>
            <th>Salario / Tarifa por Hora</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $stmt->fetch()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['nombre_posicion']); ?></td>
            <td><?php echo htmlspecialchars($row['tipo_contrato']); ?></td>
            <td><?php echo htmlspecialchars($row['fecha_inicio']); ?></td>
            <td>
                <?php 
                if (!empty($row['salario_mensual_bruto'])) {
                    echo '$' . number_format($row['salario_mensual_bruto'], 2) . ' (Mensual)';
                } else {
                    echo '$' . number_format($row['tarifa_por_hora'], 2) . ' (por Hora)';
                }
                ?>
            </td>
            <td>
                <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<a href="<?php echo BASE_URL; ?>employees/index.php" class="btn btn-secondary">Volver a Empleados</a>

<?php
require_once '../includes/footer.php';
?>