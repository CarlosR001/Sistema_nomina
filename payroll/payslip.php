<?php
// payroll/payslip.php

require_once '../auth.php';
require_login();

// ... (toda la lógica de autorización y obtención de datos que no cambia)
if (!isset($_GET['nomina_id']) || !is_numeric($_GET['nomina_id']) || !isset($_GET['contrato_id']) || !is_numeric($_GET['contrato_id'])) { header('Location: ' . BASE_URL . 'index.php?status=error&message=Faltan%20par%C3%A1metros.'); exit(); }
$id_nomina = $_GET['nomina_id'];
$id_contrato = $_GET['contrato_id'];
$stmt_get_employee_id = $pdo->prepare("SELECT id_empleado FROM contratos WHERE id = ?");
$stmt_get_employee_id->execute([$id_contrato]);
$empleado_id_del_contrato = $stmt_get_employee_id->fetchColumn();

// Seguridad: solo el propio empleado o un usuario con permiso de 'nomina.procesar' puede ver el volante.
$user_empleado_id = $_SESSION['user_id_empleado'] ?? null;

// Si el usuario NO tiene permiso para procesar nóminas Y el volante que intenta ver NO es el suyo, se le deniega el acceso.
if (!has_permission('nomina.procesar') && $user_empleado_id != $empleado_id_del_contrato) {
    header('HTTP/1.0 403 Forbidden');
    die('Acceso denegado. No tienes permiso para ver este volante de pago.');
}

$stmt_empleado = $pdo->prepare("SELECT e.nombres, e.primer_apellido, e.cedula, p.nombre_posicion FROM contratos c JOIN empleados e ON c.id_empleado = e.id JOIN posiciones p ON c.id_posicion = p.id WHERE c.id = ?");
$stmt_empleado->execute([$id_contrato]);
$empleado = $stmt_empleado->fetch();
$stmt_nomina = $pdo->prepare("SELECT * FROM nominasprocesadas WHERE id = ?");
$stmt_nomina->execute([$id_nomina]);
$nomina = $stmt_nomina->fetch();
if (!$empleado || !$nomina) { header('Location: ' . BASE_URL . 'index.php?status=error&message=Datos%20no%20encontrados.'); exit(); }

// Nueva lógica para obtener todos los detalles
$stmt_detalles = $pdo->prepare("SELECT * FROM nominadetalle WHERE id_nomina_procesada = ? AND id_contrato = ? ORDER BY tipo_concepto, codigo_concepto");
$stmt_detalles->execute([$id_nomina, $id_contrato]);
$detalles = $stmt_detalles->fetchAll();

$ingresos = array_filter($detalles, function($d) { return $d['tipo_concepto'] === 'Ingreso'; });
$deducciones = array_filter($detalles, function($d) { return $d['tipo_concepto'] === 'Deducción'; });
$bases_calculo = array_filter($detalles, function($d) { return $d['tipo_concepto'] === 'Base de Cálculo'; });

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
            <!-- Columna de Resumen de Horas -->
            <div class="col-md-4">
                <h5 class="text-info">Resumen de Horas</h5>
                <table class="table table-sm">
                    <?php foreach ($bases_calculo as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['descripcion_concepto']); ?></td>
                        <td class="text-end"><strong><?php echo number_format($item['monto_resultado'], 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- Columna de Ingresos -->
            <div class="col-md-4">
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

            <!-- Columna de Deducciones -->
            <div class="col-md-4">
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
     <!-- Botones de Acción (Solo para usuarios con permiso de nómina) -->
          <?php if (has_permission('nomina.procesar')): ?>
                         <div class="col-12 mt-4 text-center">
                             <a href="#" class="btn btn-primary"><i class="bi bi-envelope"></i> Enviar por Email</a>
                             <a href="#" class="btn btn-secondary"><i class="bi bi-printer"></i> Imprimir</a>
                         </div>
         <?php endif; ?>

         <button onclick="window.print()" class="btn btn-info">Imprimir</button>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
