<?php
// reports/generate_journal_entry.php
require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if (!isset($_POST['payroll_id']) || !is_numeric($_POST['payroll_id'])) {
    die("ID de nómina no válido.");
}
$payroll_id = $_POST['payroll_id'];

// Obtener datos de la nómina
$stmt_payroll = $pdo->prepare("SELECT * FROM nominasprocesadas WHERE id = ?");
$stmt_payroll->execute([$payroll_id]);
$payroll_header = $stmt_payroll->fetch();
if (!$payroll_header) die("Nómina no encontrada.");

// Obtener detalles de la nómina
$stmt_details = $pdo->prepare("SELECT * FROM nominadetalle WHERE id_nomina_procesada = ?");
$stmt_details->execute([$payroll_id]);
$details = $stmt_details->fetchAll();

// Lógica para agrupar en cuentas contables
$journal_entries = [];
$total_ingresos = 0;
$total_deducciones = 0;

// Cuentas de Gasto (Débitos)
$journal_entries['Sueldos y Salarios'] = 0;
$journal_entries['Bonificaciones e Incentivos'] = 0;
$journal_entries['Vacaciones'] = 0;
$journal_entries['Otros Ingresos (No Cotizables)'] = 0;

// Cuentas de Pasivo (Créditos)
$journal_entries['Retenciones por Pagar - TSS'] = 0;
$journal_entries['Retenciones por Pagar - ISR'] = 0;
$journal_entries['Otras Deducciones por Pagar'] = 0;
$journal_entries['Banco (Neto a Pagar)'] = 0;


foreach ($details as $detail) {
    if ($detail['tipo_concepto'] == 'Ingreso') {
        $total_ingresos += $detail['monto_resultado'];
        // Clasificación de Gastos
        if (strpos(strtolower($detail['codigo_concepto']), 'salario') !== false || strpos(strtolower($detail['codigo_concepto']), 'normal') !== false) {
            $journal_entries['Sueldos y Salarios'] += $detail['monto_resultado'];
        } elseif (strpos(strtolower($detail['codigo_concepto']), 'bono') !== false || strpos(strtolower($detail['codigo_concepto']), 'incentivo') !== false) {
            $journal_entries['Bonificaciones e Incentivos'] += $detail['monto_resultado'];
        } elseif (strpos(strtolower($detail['codigo_concepto']), 'vacaciones') !== false) {
            $journal_entries['Vacaciones'] += $detail['monto_resultado'];
        } else {
            // Todos los demás ingresos van a una cuenta genérica
            $journal_entries['Otros Ingresos (No Cotizables)'] += $detail['monto_resultado'];
        }
    } elseif ($detail['tipo_concepto'] == 'Deducción') {
        $total_deducciones += $detail['monto_resultado'];
        // Clasificación de Pasivos (Retenciones)
        if ($detail['codigo_concepto'] == 'DED-AFP' || $detail['codigo_concepto'] == 'DED-SFS') {
            $journal_entries['Retenciones por Pagar - TSS'] += $detail['monto_resultado'];
        } elseif ($detail['codigo_concepto'] == 'DED-ISR') {
            $journal_entries['Retenciones por Pagar - ISR'] += $detail['monto_resultado'];
        } else {
            $journal_entries['Otras Deducciones por Pagar'] += $detail['monto_resultado'];
        }
    }
}

$neto_a_pagar = $total_ingresos - $total_deducciones;
$journal_entries['Banco (Neto a Pagar)'] = $neto_a_pagar;

// Eliminar cuentas con valor cero para un reporte más limpio
foreach ($journal_entries as $account => $amount) {
    if ($amount == 0) unset($journal_entries[$account]);
}

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="mb-2">Entrada de Diario Contable</h1>
    <p class="text-muted">Nómina ID: <?php echo $payroll_header['id']; ?> | Período: <?php echo date('d/m/Y', strtotime($payroll_header['periodo_inicio'])) . " al " . date('d/m/Y', strtotime($payroll_header['periodo_fin'])); ?></p>

    <div class="card mt-4">
        <div class="card-header">
            Asiento Contable
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Cuenta Contable</th>
                        <th class="text-end">Débito</th>
                        <th class="text-end">Crédito</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_debitos = 0;
                    $total_creditos = 0;
                    foreach ($journal_entries as $account => $amount): 
                        $is_debit = (strpos(strtolower($account), 'retenciones') === false && strpos(strtolower($account), 'otras deducciones') === false && strpos(strtolower($account), 'banco') === false);
                        if ($is_debit) $total_debitos += $amount; else $total_creditos += $amount;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($account); ?></td>
                        <td class="text-end"><?php echo $is_debit ? number_format($amount, 2) : '-'; ?></td>
                        <td class="text-end"><?php echo !$is_debit ? number_format($amount, 2) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th class="text-end">Totales Cuadrados</th>
                        <th class="text-end">$<?php echo number_format($total_debitos, 2); ?></th>
                        <th class="text-end">$<?php echo number_format($total_creditos, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
     <div class="mt-4 text-end">
        <button onclick="window.print();" class="btn btn-secondary"><i class="bi bi-printer"></i> Imprimir</button>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
