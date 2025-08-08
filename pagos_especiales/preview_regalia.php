<?php
// pagos_especiales/preview_regalia.php - v1.0 (Motor de Cálculo de Regalía)
require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['year'])) {
    header('Location: regalia.php'); exit();
}
$year = (int)$_POST['year'];

try {
    $sql = "
        SELECT 
            e.id as empleado_id, c.id as contrato_id, e.nombres, e.primer_apellido,
            SUM(CASE WHEN cn.afecta_tss = 1 AND nd.tipo_concepto = 'Ingreso' THEN nd.monto_resultado ELSE 0 END) as total_salario_cotizable_anual
        FROM Empleados e
        JOIN Contratos c ON e.id = c.id_empleado
        LEFT JOIN NominaDetalle nd ON c.id = nd.id_contrato
        LEFT JOIN NominasProcesadas np ON nd.id_nomina_procesada = np.id AND YEAR(np.periodo_fin) = ?
        LEFT JOIN ConceptosNomina cn ON nd.codigo_concepto = cn.codigo_concepto
        WHERE c.estado_contrato = 'Vigente'
        GROUP BY e.id, c.id
        ORDER BY e.nombres, e.primer_apellido
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$year]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $regalia_data = [];
    foreach ($resultados as $row) {
        $regalia_calculada = ($row['total_salario_cotizable_anual'] ?? 0) / 12;
        if ($regalia_calculada > 0) {
            $regalia_data[] = [
                'contrato_id' => $row['contrato_id'],
                'nombre_completo' => $row['nombres'] . ' ' . $row['primer_apellido'],
                'salario_acumulado' => $row['total_salario_cotizable_anual'],
                'monto_regalia' => round($regalia_calculada, 2)
            ];
        }
    }
    $_SESSION['regalia_data'] = $regalia_data;
    $_SESSION['regalia_year'] = $year;

} catch (Exception $e) {
    header('Location: regalia.php?status=error&message=' . urlencode('Error al calcular: ' . $e->getMessage())); exit();
}
require_once '../includes/header.php';
?>

<h1 class="mb-4">Previsualización de Regalía (Año <?php echo $year; ?>)</h1>

<?php if (empty($regalia_data)): ?>
    <div class="alert alert-warning">No se encontraron salarios acumulados para el año seleccionado.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Empleado</th>
                    <th class="text-end">Salario Cotizable Acumulado</th>
                    <th class="text-end">Monto Regalía a Pagar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($regalia_data as $data): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($data['nombre_completo']); ?></td>
                        <td class="text-end">$<?php echo number_format($data['salario_acumulado'], 2); ?></td>
                        <td class="text-end fw-bold">$<?php echo number_format($data['monto_regalia'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card mt-4">
        <div class="card-body text-center">
            <h5 class="card-title">Confirmar y Procesar Pago</h5>
            <form action="procesar_pago.php" method="POST" onsubmit="return confirm('¿Generar la nómina para este pago de regalía?');">
                <input type="hidden" name="payment_type" value="regalia">
                <button type="submit" class="btn btn-success btn-lg">Generar Nómina de Regalía</button>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
