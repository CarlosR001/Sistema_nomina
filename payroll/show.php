<?php
// payroll/show.php - v3.0 (Estable, Robusto y Corregido)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=ID de nómina no válido.');
    exit();
}
$id_nomina = $_GET['id'];

// Obtener la información de la cabecera de la nómina
$stmt_nomina = $pdo->prepare("SELECT * FROM nominasProcesadas WHERE id = ?");
$stmt_nomina->execute([$id_nomina]);
$nomina = $stmt_nomina->fetch();

if (!$nomina) {
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=Nómina no encontrada.');
    exit();
}

// Obtener los detalles agrupados por empleado
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
    <a href="<?php echo BASE_URL; ?>payroll/review.php" class="btn btn-secondary">Volver a Revisión</a>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Resumen General</h5>
        <div><span class="badge bg-warning text-dark fs-6"><?php echo htmlspecialchars($nomina['estado_nomina']); ?></span></div>
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
                
            <?php if ($nomina['estado_nomina'] !== 'Aprobada y Finalizada'): ?>
                    <?php
                        // Determinar el script de procesamiento correcto
                        $recalc_script_url = ($nomina['tipo_nomina_procesada'] === 'Administrativa')
                            ? BASE_URL . 'nomina_administrativa/procesar_nomina_admin.php'
                            : BASE_URL . 'payroll/process.php';
                    ?>
                    <!-- Formulario de Recálculo -->
                    <form action="<?php echo $recalc_script_url; ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Recalcular? Los datos actuales se borrarán y se volverán a generar.');">
                        <input type="hidden" name="id_nomina_a_recalcular" value="<?php echo htmlspecialchars($id_nomina); ?>">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-clockwise"></i> Recalcular</button>
                    </form>
                    
                    <!-- Formulario de Finalización -->
                    <form action="<?php echo BASE_URL . 'payroll/finalize.php' ?>" method="POST" class="d-inline" onsubmit="return confirm('Este proceso es irreversible. ¿Finalizar y aprobar esta nómina?');">
                        <input type="hidden" name="nomina_id" value="<?php echo htmlspecialchars($id_nomina); ?>">
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Finalizar</button>
                    </form>
                <?php else: ?>
                    <!-- La nómina está finalizada, mostrar botón de envío -->
                    <form action="<?php echo BASE_URL . 'payroll/send_payslips.php' ?>" method="POST" class="d-inline" onsubmit="return confirm('Se enviarán los volantes de pago por correo a todos los empleados de esta nómina que tengan un email registrado. ¿Continuar?');">
                        <input type="hidden" name="nomina_id" value="<?php echo htmlspecialchars($id_nomina); ?>">
                        <button type="submit" class="btn btn-info"><i class="bi bi-envelope"></i> Enviar Volantes por Correo</button>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- El resto del HTML para mostrar los detalles de los empleados -->
<h3 class="mt-5">Detalle por Empleado</h3>
<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>Cédula</th><th>Empleado</th><th class="text-end">Total Ingresos</th><th class="text-end">Total Deducciones</th><th class="text-end">Neto a Pagar</th><th class="text-center">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($detalles as $detalle): ?>
        <tr>
            <td><?php echo htmlspecialchars($detalle['cedula']); ?></td>
            <td><?php echo htmlspecialchars($detalle['nombres'] . ' ' . $detalle['primer_apellido']); ?></td>
            <td class="text-end">$<?php echo number_format((float)$detalle['total_ingresos'], 2); ?></td>
            <td class="text-end text-danger">-$<?php echo number_format((float)$detalle['total_deducciones'], 2); ?></td>
            <td class="text-end fw-bold">$<?php echo number_format((float)$detalle['total_ingresos'] - (float)$detalle['total_deducciones'], 2); ?></td>
            <td class="text-center">
                <a href="payslip.php?nomina_id=<?php echo $id_nomina; ?>&contrato_id=<?php echo $detalle['id_contrato']; ?>" class="btn btn-sm btn-info" target="_blank">Ver Desglose</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>
