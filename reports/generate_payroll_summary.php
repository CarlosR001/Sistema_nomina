<?php
// reports/generate_payroll_summary.php

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_nomina'])) {
    die("Acceso no permitido o ID de nómina no proporcionado.");
}
$id_nomina = (int)$_POST['id_nomina'];

try {
    $configs_db = $pdo->query("SELECT clave, valor FROM configuracionglobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $company_name = $configs_db['COMPANY_NAME'] ?? 'Empresa no Configurada';

    $stmt_nomina = $pdo->prepare("SELECT * FROM nominasprocesadas WHERE id = ?");
    $stmt_nomina->execute([$id_nomina]);
    $nomina = $stmt_nomina->fetch();
    if (!$nomina) die("Nómina no encontrada.");

    // Consulta principal para agrupar y sumar los datos
    $sql = "
        SELECT
            e.cedula,
            CONCAT(e.nombres, ' ', e.primer_apellido) as nombre_completo,
            SUM(CASE WHEN nd.tipo_concepto = 'Ingreso' THEN nd.monto_resultado ELSE 0 END) as total_ingresos,
            SUM(CASE WHEN nd.codigo_concepto IN ('DED-AFP', 'DED-SFS') THEN nd.monto_resultado ELSE 0 END) as total_tss,
            SUM(CASE WHEN nd.codigo_concepto = 'DED-ISR' THEN nd.monto_resultado ELSE 0 END) as total_isr,
            SUM(CASE WHEN nd.tipo_concepto = 'Deducción' AND nd.codigo_concepto NOT IN ('DED-AFP', 'DED-SFS', 'DED-ISR') THEN nd.monto_resultado ELSE 0 END) as otras_deducciones
        FROM nominadetalle nd
        JOIN contratos c ON nd.id_contrato = c.id
        JOIN empleados e ON c.id_empleado = e.id
        WHERE nd.id_nomina_procesada = ?
        GROUP BY e.id, nombre_completo
        ORDER BY nombre_completo
    ";
    $stmt_reporte = $pdo->prepare($sql);
    $stmt_reporte->execute([$id_nomina]);
    $report_data = $stmt_reporte->fetchAll();

} catch (Exception $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}

// Generar contenido del CSV en memoria
$csv_output = fopen('php://temp', 'w');
fputcsv($csv_output, ['Cedula', 'Nombre Completo', 'Salario Bruto', 'Descuentos TSS', 'Descuento ISR', 'Otras Deducciones', 'Neto a Pagar']);
foreach ($report_data as $row) {
    $neto = $row['total_ingresos'] - ($row['total_tss'] + $row['total_isr'] + $row['otras_deducciones']);
    fputcsv($csv_output, [
        $row['cedula'],
        $row['nombre_completo'],
        number_format($row['total_ingresos'], 2, '.', ''),
        number_format($row['total_tss'], 2, '.', ''),
        number_format($row['total_isr'], 2, '.', ''),
        number_format($row['otras_deducciones'], 2, '.', ''),
        number_format($neto, 2, '.', '')
    ]);
}
rewind($csv_output);
$csv_content = stream_get_contents($csv_output);
fclose($csv_output);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen de Nómina #<?php echo $id_nomina; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .container { max-width: 1100px; }
        .header { text-align: center; margin-bottom: 2rem; }
        .header h1, .header p { margin: 0; }
        .table thead th { background-color: #f2f2f2; }
        tfoot td { font-weight: bold; border-top-width: 2px; }
        @media print { .no-print { display: none; } .container { max-width: 100%; } }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="header">
            <h1><?php echo htmlspecialchars($company_name); ?></h1>
            <p>Resumen General de Nómina</p>
            <p class="lead">Nómina ID: <?php echo $id_nomina; ?> | Período: <?php echo htmlspecialchars($nomina['periodo_inicio'] . ' al ' . $nomina['periodo_fin']); ?></p>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Cédula</th><th>Nombre del Empleado</th><th class="text-end">Salario Bruto</th>
                        <th class="text-end">Desc. TSS</th><th class="text-end">Desc. ISR</th>
                        <th class="text-end">Otras Deduc.</th><th class="text-end">Neto a Pagar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_bruto = 0; $total_tss = 0; $total_isr = 0; $total_otras_ded = 0; $total_neto = 0;
                    foreach ($report_data as $row): 
                        $neto_a_pagar = $row['total_ingresos'] - ($row['total_tss'] + $row['total_isr'] + $row['otras_deducciones']);
                        $total_bruto += $row['total_ingresos'];
                        $total_tss += $row['total_tss'];
                        $total_isr += $row['total_isr'];
                        $total_otras_ded += $row['otras_deducciones'];
                        $total_neto += $neto_a_pagar;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['cedula']); ?></td>
                        <td><?php echo htmlspecialchars($row['nombre_completo']); ?></td>
                        <td class="text-end"><?php echo number_format($row['total_ingresos'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($row['total_tss'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($row['total_isr'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($row['otras_deducciones'], 2); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($neto_a_pagar, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-dark">
                        <td colspan="2">TOTAL GENERAL</td>
                        <td class="text-end">$<?php echo number_format($total_bruto, 2); ?></td>
                        <td class="text-end">$<?php echo number_format($total_tss, 2); ?></td>
                        <td class="text-end">$<?php echo number_format($total_isr, 2); ?></td>
                        <td class="text-end">$<?php echo number_format($total_otras_ded, 2); ?></td>
                        <td class="text-end">$<?php echo number_format($total_neto, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="text-center my-4 no-print">
            <button onclick="window.print()" class="btn btn-secondary">Imprimir (PDF)</button>
            <button id="export-csv" class="btn btn-success">Exportar a Excel (CSV)</button>
        </div>
    </div>
    <script>
        document.getElementById('export-csv').addEventListener('click', function() {
            const csvContent = <?php echo json_encode($csv_content); ?>;
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'resumen_nomina_<?php echo $id_nomina; ?>.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    </script>
</body>
</html>
