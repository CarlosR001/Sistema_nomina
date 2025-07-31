<?php
// payroll/show.php

require_once '../auth.php';
require_login();
require_role('Admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=ID%20de%20n%C3%B3mina%20no%20v%C3%A1lido.');
    exit();
}
$id_nomina = $_GET['id'];

$stmt_nomina = $pdo->prepare("SELECT * FROM NominasProcesadas WHERE id = ?");
$stmt_nomina->execute([$id_nomina]);
$nomina = $stmt_nomina->fetch();

if (!$nomina) {
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=N%C3%B3mina%20no%20encontrada.');
    exit();
}

$stmt_detalles = $pdo->prepare("
    SELECT c.id as id_contrato, e.nombres, e.primer_apellido, 
    (SELECT SUM(monto_resultado) FROM NominaDetalle WHERE id_nomina_procesada = ? AND id_contrato = c.id AND tipo_concepto = 'Ingreso') as total_ingresos,
    (SELECT SUM(monto_resultado) FROM NominaDetalle WHERE id_nomina_procesada = ? AND id_contrato = c.id AND tipo_concepto = 'Deducción') as total_deducciones
    FROM NominaDetalle nd
    JOIN Contratos c ON nd.id_contrato = c.id
    JOIN Empleados e ON c.id_empleado = e.id
    WHERE nd.id_nomina_procesada = ?
    GROUP BY c.id, e.nombres, e.primer_apellido
    ORDER BY e.nombres, e.primer_apellido");
$stmt_detalles->execute([$id_nomina, $id_nomina, $id_nomina]);
$detalles = $stmt_detalles->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Resultados de la Nómina Procesada</h1>

<div class="card mb-4">
    <div class="card-header">Resumen General</div>
    <div class="card-body">
        <p><strong>ID de Nómina:</strong> <?php echo htmlspecialchars($nomina['id']); ?></p>
        <p><strong>Tipo:</strong> <?php echo htmlspecialchars($nomina['tipo_nomina_procesada']); ?></p>
        <p><strong>Período:</strong> <?php echo htmlspecialchars($nomina['periodo_inicio']) . " al " . htmlspecialchars($nomina['periodo_fin']); ?></p>
        <p><strong>Estado:</strong> <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($nomina['estado_nomina']); ?></span></p>
    </div>
</div>

<h3 class="mt-5">Detalle por Empleado</h3>
<table class="table table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Empleado</th>
            <th class="text-end">Total Ingresos</th>
            <th class="text-end">Total Deducciones</th>
            <th class="text-end">Neto a Pagar</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $total_general_ingresos = 0;
        $total_general_deducciones = 0;
        $total_general_neto = 0;
        foreach ($detalles as $detalle): 
            $ingresos_empleado = (float)($detalle['total_ingresos'] ?? 0);
            $deducciones_empleado = (float)($detalle['total_deducciones'] ?? 0);
            $neto_pagar = $ingresos_empleado - $deducciones_empleado;
            
            $total_general_ingresos += $ingresos_empleado;
            $total_general_deducciones += $deducciones_empleado;
            $total_general_neto += $neto_pagar;
        ?>
        <tr>
            <td><?php echo htmlspecialchars($detalle['nombres'] . ' ' . $detalle['primer_apellido']); ?></td>
            <td class="text-end">$<?php echo number_format($ingresos_empleado, 2); ?></td>
            <td class="text-end">-$<?php echo number_format($deducciones_empleado, 2); ?></td>
            <td class="text-end fw-bold">$<?php echo number_format($neto_pagar, 2); ?></td>
            <td>
                <a href="payslip.php?nomina_id=<?php echo htmlspecialchars($id_nomina); ?>&contrato_id=<?php echo htmlspecialchars($detalle['id_contrato']); ?>" class="btn btn-sm btn-info">Ver Desglose</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot class="table-group-divider">
        <tr class="table-secondary">
            <td class="text-end"><strong>TOTAIS GERAIS:</strong></td>
            <td class="text-end"><strong>$<?php echo number_format($total_general_ingresos, 2); ?></strong></td>
            <td class="text-end"><strong>-$<?php echo number_format($total_general_deducciones, 2); ?></strong></td>
            <td class="text-end"><strong>$<?php echo number_format($total_general_neto, 2); ?></strong></td>
            <td></td>
        </tr>
    </tfoot>
</table>

<a href="<?php echo BASE_URL; ?>payroll/review.php" class="btn btn-primary mt-3">Volver a Revisión</a>

<?php require_once '../includes/footer.php'; ?>
