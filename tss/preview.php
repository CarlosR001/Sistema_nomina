<?php
// tss/preview.php - v1.3 (con Búfer de Salida para Redirecciones Seguras)

ob_start(); // <-- LÍNEA 1 AÑADIDA: Inicia el búfer de salida.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['year'], $_POST['month'])) {
    header('Location: index.php');
    exit();
}

$year = (int)$_POST['year'];
$month = (int)$_POST['month'];

try {
    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $rnc_empresa = $configs_db['RNC_EMPRESA'] ?? '';

    if (empty($rnc_empresa) || $rnc_empresa === 'XXXXXXXXX') {
        throw new Exception("El RNC de la empresa no está configurado.");
    }

    $sql = "
        SELECT
            e.cedula, e.nss, e.nombres, e.primer_apellido, e.segundo_apellido, e.sexo, e.fecha_nacimiento, c.tipo_nomina,
            SUM(CASE WHEN cn.afecta_tss = 1 AND nd.tipo_concepto = 'Ingreso' THEN nd.monto_resultado ELSE 0 END) as salario_cotizable_tss,
            SUM(CASE WHEN cn.afecta_isr = 1 AND nd.tipo_concepto = 'Ingreso' THEN nd.monto_resultado ELSE 0 END) as base_isr,
            SUM(CASE WHEN cn.afecta_tss = 0 AND nd.tipo_concepto = 'Ingreso' THEN nd.monto_resultado ELSE 0 END) as otras_remuneraciones
        FROM NominasProcesadas np
        JOIN NominaDetalle nd ON np.id = nd.id_nomina_procesada
        JOIN Contratos c ON nd.id_contrato = c.id
        JOIN Empleados e ON c.id_empleado = e.id
        JOIN ConceptosNomina cn ON nd.codigo_concepto = cn.codigo_concepto
        WHERE YEAR(np.periodo_fin) = ? AND MONTH(np.periodo_fin) = ?
        GROUP BY e.id, c.tipo_nomina
        ORDER BY e.nombres, e.primer_apellido
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$year, $month]);
    $empleados_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($empleados_data)) {
        throw new Exception("No se encontraron datos de nómina procesados para el período seleccionado ($month/$year). Verifique que las nóminas del mes estén finalizadas.");
    }

    $_SESSION['tss_export_data'] = $empleados_data;
    $_SESSION['tss_export_period'] = ['year' => $year, 'month' => $month];

} catch (Exception $e) {
    ob_end_clean(); // <-- LÍNEA 2 AÑADIDA: Limpia el búfer antes de redirigir.
    header('Location: index.php?status=error&message=' . urlencode($e->getMessage()));
    exit();
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Previsualización Detallada para TSS (<?php echo "$month/$year"; ?>)</h1>
<p>Verifica que los datos sean correctos. El archivo final `.txt` contendrá todos los campos requeridos por la TSS, incluyendo los que aquí aparecen vacíos o con valor 0.</p>

<div class="table-responsive">
    <table class="table table-bordered table-sm table-hover" style="font-size: 0.8rem;">
        <thead class="table-dark">
            <tr>
                <th>Cédula</th>
                <th>NSS</th>
                <th>Nombres y Apellidos</th>
                <th>Sexo</th>
                <th>Fecha Nac.</th>
                <th class="text-end">Salario Cotizable</th>
                <th class="text-end">Salario ISR</th>
                <th class="text-end">Otras Remun.</th>
                <th>Tipo Ingreso</th>
                <th class="text-end">Aporte Vol.</th>
                <th class="text-end">Salario INFOTEP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($empleados_data as $emp): ?>
                <?php
                    $tipo_ingreso_tss = ($emp['tipo_nomina'] === 'Inspectores') ? '05' : '01';
                    $sexo_tss = ($emp['sexo'] === 'Masculino') ? 'M' : 'F';
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($emp['cedula']); ?></td>
                    <td><?php echo htmlspecialchars($emp['nss']); ?></td>
                    <td><?php echo htmlspecialchars($emp['nombres'] . ' ' . $emp['primer_apellido']); ?></td>
                    <td class="text-center"><?php echo $sexo_tss; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($emp['fecha_nacimiento'])); ?></td>
                    <td class="text-end fw-bold"><?php echo number_format($emp['salario_cotizable_tss'], 2); ?></td>
                    <td class="text-end"><?php echo number_format($emp['base_isr'], 2); ?></td>
                    <td class="text-end"><?php echo number_format($emp['otras_remuneraciones'], 2); ?></td>
                    <td class="text-center"><span class="badge bg-secondary"><?php echo $tipo_ingreso_tss; ?></span></td>
                    <td class="text-end"><?php echo number_format(0, 2); ?></td>
                    <td class="text-end"><?php echo number_format($emp['salario_cotizable_tss'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card mt-4">
    <div class="card-body text-center">
        <h5 class="card-title">Confirmación Final</h5>
        <p class="card-text">Si los datos mostrados en la tabla son correctos, puedes proceder a generar el archivo de texto.</p>
        <form action="export.php" method="POST" class="d-inline">
             <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-download"></i> Confirmar y Descargar Archivo .txt
            </button>
        </form>
        <a href="index.php" class="btn btn-secondary btn-lg">Cancelar</a>
    </div>
</div>

<?php 
require_once '../includes/footer.php'; 
ob_end_flush(); // <-- LÍNEA 3 AÑADIDA: Envía la salida del búfer al navegador.
?>
