<?php
// reports/generate_orders_report.php - v2.1 (con Filtro de Orden)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// --- 1. Recepción y Validación de Datos ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Acceso no permitido.");
$id_cliente = $_POST['id_cliente'] ?? 'all';
$id_orden = $_POST['id_orden'] ?? 'all'; // <-- NUEVO
$estado_orden = $_POST['estado_orden'] ?? 'all';
$fecha_desde = $_POST['fecha_desde'] ?? null;
$fecha_hasta = $_POST['fecha_hasta'] ?? null;

try {
    $configs_db = $pdo->query("SELECT clave, valor FROM configuracionglobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $company_name = $configs_db['COMPANY_NAME'] ?? 'Empresa no Configurada';

    // --- 2. Consulta Principal para Obtener las Órdenes ---
    $sql_ordenes = "
        SELECT o.id, o.codigo_orden, o.estado_orden, c.nombre_cliente
        FROM ordenes o
        JOIN clientes c ON o.id_cliente = c.id
    ";
    $where_clauses = [];
    $params = [];
    if ($id_cliente !== 'all') { $where_clauses[] = "o.id_cliente = ?"; $params[] = $id_cliente; }
    if ($id_orden !== 'all') { $where_clauses[] = "o.id = ?"; $params[] = $id_orden; } // <-- NUEVO
    if ($estado_orden !== 'all') { $where_clauses[] = "o.estado_orden = ?"; $params[] = $estado_orden; }
    if (!empty($fecha_desde)) { $where_clauses[] = "o.fecha_creacion >= ?"; $params[] = $fecha_desde; }
    if (!empty($fecha_hasta)) { $where_clauses[] = "o.fecha_creacion <= ?"; $params[] = $fecha_hasta; }
    if (!empty($where_clauses)) { $sql_ordenes .= " WHERE " . implode(' AND ', $where_clauses); }
    $sql_ordenes .= " ORDER BY c.nombre_cliente, o.codigo_orden";
    
    $stmt_ordenes = $pdo->prepare($sql_ordenes);
    $stmt_ordenes->execute($params);
    $ordenes = $stmt_ordenes->fetchAll();

    // --- 3. Procesamiento de cada orden para calcular costos ---
    $report_data = [];
    $stmt_horas = $pdo->prepare("
        SELECT rh.*, c.tarifa_por_hora, l.monto_transporte_completo
        FROM registrohoras rh
        JOIN contratos c ON rh.id_contrato = c.id
        LEFT JOIN lugares l ON rh.id_zona_trabajo = l.id
        WHERE rh.id_orden = ? AND rh.estado_registro = 'Aprobado'
    ");

    foreach ($ordenes as $orden) {
        $stmt_horas->execute([$orden['id']]);
        $registros = $stmt_horas->fetchAll();
        
        $total_horas = 0;
        $costo_mano_obra = 0;
        $costo_transporte = 0;

        foreach ($registros as $reg) {
            $inicio = new DateTime($reg['hora_inicio']);
            $fin = new DateTime($reg['hora_fin']);
            if ($fin <= $inicio) $fin->modify('+1 day');
            $duracion = round(($fin->getTimestamp() - $inicio->getTimestamp()) / 3600, 2);
            
            $total_horas += $duracion;
            $costo_mano_obra += $duracion * (float)$reg['tarifa_por_hora'];
            
            if ($reg['transporte_aprobado']) {
                $monto_transp = (float)$reg['monto_transporte_completo'];
                $costo_transporte += $reg['transporte_mitad'] ? ($monto_transp * 0.5) : $monto_transp;
            }
        }
        
        $report_data[] = [
            'codigo_orden' => $orden['codigo_orden'],
            'nombre_cliente' => $orden['nombre_cliente'],
            'estado_orden' => $orden['estado_orden'],
            'total_horas' => $total_horas,
            'costo_mano_obra' => $costo_mano_obra,
            'costo_transporte' => $costo_transporte,
            'costo_total' => $costo_mano_obra + $costo_transporte
        ];
    }

} catch (Exception $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Órdenes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-size: 12px; } .container { max-width: 1100px; }
        .header { text-align: center; margin-bottom: 2rem; }
        .table thead th { background-color: #f2f2f2; }
        tfoot td { font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="header">
            <h1><?php echo htmlspecialchars($company_name); ?></h1>
            <p>Reporte de Órdenes y Costos Asociados</p>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Orden</th><th>Cliente</th><th>Estado</th>
                        <th class="text-end">Total Horas Invertidas</th>
                        <th class="text-end">Costo Mano de Obra</th>
                        <th class="text-end">Costo Transporte</th>
                        <th class="text-end">Costo Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report_data)): ?>
                        <tr><td colspan="7" class="text-center">No se encontraron órdenes con los filtros seleccionados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['codigo_orden']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_cliente']); ?></td>
                            <td><?php echo htmlspecialchars($row['estado_orden']); ?></td>
                            <td class="text-end"><?php echo number_format($row['total_horas'], 2); ?></td>
                            <td class="text-end">$<?php echo number_format($row['costo_mano_obra'], 2); ?></td>
                            <td class="text-end">$<?php echo number_format($row['costo_transporte'], 2); ?></td>
                            <td class="text-end fw-bold">$<?php echo number_format($row['costo_total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="text-center my-4 no-print">
            <button onclick="window.print()" class="btn btn-secondary">Imprimir (PDF)</button>
        </div>
    </div>
</body>
</html>
