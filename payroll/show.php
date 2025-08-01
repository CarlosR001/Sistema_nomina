<?php
// payroll/show.php - v1.1
// Añade los botones de acción "Recalcular Nómina" y "Finalizar Nómina"

require_once '../auth.php';
require_login();
require_role('Admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=ID%20de%20n%C3%B3mina%20no%20v%C3%A1lido.');
    exit();
}
$id_nomina = $_GET['id'];

$stmt_nomina = $pdo->prepare("
    SELECT np.*, pr.id as periodo_id 
    FROM NominasProcesadas np
    LEFT JOIN PeriodosDeReporte pr ON np.periodo_inicio = pr.fecha_inicio_periodo AND np.periodo_fin = pr.fecha_fin_periodo
    WHERE np.id = ?
");
$stmt_nomina->execute([$id_nomina]);
$nomina = $stmt_nomina->fetch();

if (!$nomina) {
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=N%C3%B3mina%20no%20encontrada.');
    exit();
}

$stmt_detalles = $pdo->prepare("
    SELECT c.id as id_contrato, e.cedula, e.nombres, e.primer_apellido, 
    (SELECT SUM(monto_resultado) FROM NominaDetalle WHERE id_nomina_procesada = ? AND id_contrato = c.id AND tipo_concepto = 'Ingreso') as total_ingresos,
    (SELECT SUM(monto_resultado) FROM NominaDetalle WHERE id_nomina_procesada = ? AND id_contrato = c.id AND tipo_concepto = 'Deducción') as total_deducciones
    FROM NominaDetalle nd
    JOIN Contratos c ON nd.id_contrato = c.id
    JOIN Empleados e ON c.id_empleado = e.id
    WHERE nd.id_nomina_procesada = ?
    GROUP BY c.id, e.cedula, e.nombres, e.primer_apellido
    ORDER BY e.nombres, e.primer_apellido");
$stmt_detalles->execute([$id_nomina, $id_nomina, $id_nomina]);
$detalles = $stmt_detalles->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Resultados de la Nómina</h1>
    <div>
        <a href="<?php echo BASE_URL; ?>payroll/review.php" class="btn btn-secondary">Volver a Revisión</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Resumen General</h5>
        <div>
            <span class="badge bg-warning text-dark fs-6"><?php echo htmlspecialchars($nomina['estado_nomina']); ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p><strong>ID de Nómina:</strong> <?php echo htmlspecialchars($nomina['id']); ?></p>
                <p><strong>Tipo:</strong> <?php echo htmlspecialchars($nomina['tipo_nomina_procesada']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Período:</strong> <?php echo htmlspecialchars($nomina['periodo_inicio']) . " al " . htmlspecialchars($nomina['periodo_fin']); ?></p>
                <p><strong>Procesada el:</strong> <?php echo htmlspecialchars($nomina['fecha_ejecucion']); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <!-- ACCIONES -->
                <?php if ($nomina['estado_nomina'] !== 'Pagada' && $nomina['estado_nomina'] !== 'Aprobada y Finalizada'): ?>
                    <form action="<?php echo BASE_URL; ?>payroll/process.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres recalcular esta nómina? Los datos actuales se borrarán y se volverán a generar.');">
                        <input type="hidden" name="periodo_id" value="<?php echo htmlspecialchars($nomina['periodo_id']); ?>">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-clockwise"></i> Recalcular Nómina</button>
                    </form>
                    
                    <form action="<?php echo BASE_URL; ?>payroll/finalize.php" method="POST" class="d-inline" onsubmit="return confirm('Este proceso es irreversible. ¿Estás seguro de que quieres finalizar y aprobar esta nómina?');">
                        <input type="hidden" name="nomina_id" value="<?php echo htmlspecialchars($id_nomina); ?>">
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Finalizar y Aprobar</button>
                    </form>
                <?php else: ?>
                    <p class="text-muted">La nómina ya ha sido finalizada y no admite más acciones.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<h3 class="mt-5">Detalle por Empleado</h3>
<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>Cédula</th>
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
            <td><?php echo htmlspecialchars($detalle['cedula']); ?></td>
            <td><?php echo htmlspecialchars($detalle['nombres'] . ' ' . $detalle['primer_apellido']); ?></td>
            <td class="text-end">$<?php echo number_format($ingresos_empleado, 2); ?></td>
            <td class="text-end text-danger">-$<?php echo number_format($deducciones_empleado, 2); ?></td>
            <td class="text-end fw-bold">$<?php echo number_format($neto_pagar, 2); ?></td>
            <td>
                <a href="payslip.php?nomina_id=<?php echo htmlspecialchars($id_nomina); ?>&contrato_id=<?php echo htmlspecialchars($detalle['id_contrato']); ?>" class="btn btn-sm btn-info" target="_blank">Ver Desglose</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot class="table-group-divider">
        <tr class="table-secondary">
            <td colspan="2" class="text-end"><strong>TOTALES GENERALES:</strong></td>
            <td class="text-end"><strong>$<?php echo number_format($total_general_ingresos, 2); ?></strong></td>
            <td class="text-end"><strong>-$<?php echo number_format($total_general_deducciones, 2); ?></strong></td>
            <td class="text-end"><strong>$<?php echo number_format($total_general_neto, 2); ?></strong></td>
            <td></td>
        </tr>
    </tfoot>
</table>

<?php require_once '../includes/footer.php'; ?>
