<?php
// reports/journal_entry.php
require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// Obtenemos todas las nóminas procesadas para el selector
$stmt = $pdo->query("SELECT id, tipo_nomina_procesada, tipo_calculo_nomina, periodo_inicio, periodo_fin FROM nominasprocesadas ORDER BY fecha_ejecucion DESC");
$payrolls = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">Generar Reporte de Entrada de Diario</h1>
    <p class="lead">
        Seleccione un período de nómina procesado para generar el asiento contable correspondiente.
    </p>

    <div class="card">
        <div class="card-body">
            <form action="generate_journal_entry.php" method="POST" target="_blank">
                <div class="mb-3">
                    <label for="payroll_id" class="form-label">Período de Nómina:</label>
                    <select name="payroll_id" id="payroll_id" class="form-control" required>
                        <option value="">-- Seleccione un período --</option>
                        <?php foreach ($payrolls as $payroll): ?>
                            <option value="<?php echo $payroll['id']; ?>">
                                ID <?php echo $payroll['id']; ?>: 
                                <?php echo htmlspecialchars($payroll['tipo_nomina_procesada']); ?> 
                                (<?php echo htmlspecialchars($payroll['tipo_calculo_nomina']); ?>)
                                del <?php echo date('d/m/Y', strtotime($payroll['periodo_inicio'])); ?> al <?php echo date('d/m/Y', strtotime($payroll['periodo_fin'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-journal-check"></i> Generar Reporte</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
