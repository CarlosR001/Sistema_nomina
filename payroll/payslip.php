<?php
// payroll/payslip.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado

// La conexión $pdo ya está disponible a través de auth.php

// Validar los parámetros de la URL
if (!isset($_GET['nomina_id']) || !is_numeric($_GET['nomina_id']) || !isset($_GET['contrato_id']) || !is_numeric($_GET['contrato_id'])) {
    header('Location: ' . BASE_URL . 'index.php?status=error&message=Faltan%20par%C3%A1metros%20para%20generar%20el%20desglose%20de%20pago.');
    exit();
}

$id_nomina = $_GET['nomina_id'];
$id_contrato = $_GET['contrato_id'];

// Obtener el id_empleado asociado a este contrato_id
$stmt_get_employee_id = $pdo->prepare("SELECT id_empleado FROM Contratos WHERE id = ?");
$stmt_get_employee_id->execute([$id_contrato]);
$empleado_id_del_contrato = $stmt_get_employee_id->fetchColumn();

// Lógica de autorización: Admin puede ver cualquier recibo, Inspector solo el suyo.
$user_rol = $_SESSION['user_rol'] ?? '';
$user_empleado_id = $_SESSION['user_info']['id_empleado'] ?? null;

if ($user_rol !== 'Admin' && ($user_rol === 'Inspector' && $user_empleado_id !== $empleado_id_del_contrato)) {
    // Si no es administrador y es inspector pero no es su contrato
    header('HTTP/1.0 403 Forbidden');
    die('Acceso denegado. No tienes los permisos necesarios para ver este recibo de nómina.');
}

// Obtener información del empleado y del contrato
$stmt_empleado = $pdo->prepare("
    SELECT e.nombres, e.primer_apellido, e.cedula, p.nombre_posicion
    FROM Contratos c
    JOIN Empleados e ON c.id_empleado = e.id
    JOIN Posiciones p ON c.id_posicion = p.id
    WHERE c.id = ?
");
$stmt_empleado->execute([$id_contrato]);
$empleado = $stmt_empleado->fetch();

// Obtener información de la nómina
$stmt_nomina = $pdo->prepare("SELECT * FROM NominasProcesadas WHERE id = ?");
$stmt_nomina->execute([$id_nomina]);
$nomina = $stmt_nomina->fetch();

// Manejo de errores si no se encuentran datos
if (!$empleado || !$nomina) {
    header('Location: ' . BASE_URL . 'index.php?status=error&message=Datos%20de%20recibo%20no%20encontrados.');
    exit();
}

// Obtener detalles (ingresos y deducciones)
$stmt_detalles = $pdo->prepare("
    SELECT * FROM NominaDetalle
    WHERE id_nomina_procesada = ? AND id_contrato = ?
    ORDER BY tipo_concepto DESC, monto_resultado DESC
");
$stmt_detalles->execute([$id_nomina, $id_contrato]);
$detalles = $stmt_detalles->fetchAll();

$ingresos = array_filter($detalles, function($d) { return $d['tipo_concepto'] === 'Ingreso'; });
$deducciones = array_filter($detalles, function($d) { return $d['tipo_concepto'] === 'Deducción'; });

$total_ingresos = array_sum(array_column($ingresos, 'monto_resultado'));
$total_deducciones = array_sum(array_column($deducciones, 'monto_resultado'));
$neto_pagar = $total_ingresos - $total_deducciones;

require_once '../includes/header.php';
?>

<h1 class="mb-4">Desglose de Pago</h1>

<div class="card">
    <div class="card-header bg-dark text-white">
        Recibo de Nómina
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h4><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></h4>
                <p class="mb-0"><strong>Cédula:</strong> <?php echo htmlspecialchars($empleado['cedula']); ?></p>
                <p class="mb-0"><strong>Posición:</strong> <?php echo htmlspecialchars($empleado['nombre_posicion']); ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-0"><strong>ID de Nómina:</strong> <?php echo htmlspecialchars($nomina['id']); ?></p>
                <p class="mb-0"><strong>Período de Pago:</strong> <?php echo htmlspecialchars($nomina['periodo_inicio'] . ' al ' . $nomina['periodo_fin']); ?></p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h5 class="text-success">Ingresos</h5>
                <table class="table table-sm">
                    <?php foreach ($ingresos as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['descripcion_concepto']); ?></td>
                        <td class="text-end">$<?php echo number_format($item['monto_resultado'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total Ingresos</span>
                    <span>$<?php echo number_format($total_ingresos, 2); ?></span>
                </div>
            </div>

            <div class="col-md-6">
                <h5 class="text-danger">Deducciones</h5>
                <table class="table table-sm">
                     <?php foreach ($deducciones as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['descripcion_concepto']); ?></td>
                        <td class="text-end">-$<?php echo number_format($item['monto_resultado'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total Deducciones</span>
                    <span>-$<?php echo number_format($total_deducciones, 2); ?></span>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <div class="text-end">
            <h3>Neto a Pagar: <span class="text-primary">$<?php echo number_format($neto_pagar, 2); ?></span></h3>
        </div>

    </div>
    <div class="card-footer text-center">
         <?php if ($user_rol === 'Admin'): // Solo el administrador vuelve al resumen general ?>
            <a href="show.php?id=<?php echo htmlspecialchars($id_nomina); ?>" class="btn btn-secondary">Volver al Resumen de Nómina</a>
         <?php else: // Los demás roles (ej. Inspector) vuelven al inicio ?>
            <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary">Volver al Inicio</a>
         <?php endif; ?>
         <button onclick="window.print()" class="btn btn-info">Imprimir</button>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
