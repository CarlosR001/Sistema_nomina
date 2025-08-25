<?php
// reports/generate_full_orders_report.php - v2.1 (Orden de columnas en Excel corregido)
require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

// --- Recopilación y Validación de Filtros ---
$id_cliente = $_POST['id_cliente'] ?? '';
$estado_orden = $_POST['estado_orden'] ?? '';
$fecha_inicio = $_POST['fecha_inicio'] ?? '';
$fecha_fin = $_POST['fecha_fin'] ?? '';
$formato = $_POST['formato'] ?? 'html';

// --- Construcción de la Consulta Dinámica ---
$sql_base = "
    SELECT 
        o.id, o.codigo_orden, o.numero_orden_compra, o.estado_orden,
        o.fecha_creacion, o.fecha_finalizacion, o.observaciones,
        c.nombre_cliente, c.rnc_cliente,
        p.nombre_producto,
        op.nombre_operacion,
        d.nombre_division,
        CONCAT(sup.nombres, ' ', sup.primer_apellido) as supervisor_nombre,
        GROUP_CONCAT(DISTINCT CONCAT(con.Contact_Name, ' (', con.Position, ') | Tel: ', con.Phone_Number, ' | Email: ', con.Email) SEPARATOR '; ') as contactos
    FROM ordenes o
    LEFT JOIN clientes c ON o.id_cliente = c.id
    LEFT JOIN productos p ON o.id_producto = p.id
    LEFT JOIN operaciones op ON o.id_operacion = op.id
    LEFT JOIN divisiones d ON o.id_division = d.id
    LEFT JOIN empleados sup ON o.id_supervisor = sup.id
    LEFT JOIN TB_Contact con ON con.ID_Custumer = c.id
";

$where_clauses = [];
$params = [];

if (!empty($id_cliente)) { $where_clauses[] = "o.id_cliente = ?"; $params[] = $id_cliente; }
if (!empty($estado_orden)) { $where_clauses[] = "o.estado_orden = ?"; $params[] = $estado_orden; }
if (!empty($fecha_inicio)) { $where_clauses[] = "o.fecha_creacion >= ?"; $params[] = $fecha_inicio; }
if (!empty($fecha_fin)) { $where_clauses[] = "o.fecha_creacion <= ?"; $params[] = $fecha_fin; }

if (!empty($where_clauses)) { $sql_base .= " WHERE " . implode(' AND ', $where_clauses); }
$sql_base .= " GROUP BY o.id ORDER BY o.fecha_creacion DESC";

$stmt = $pdo->prepare($sql_base);
$stmt->execute($params);
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Función de Limpieza ---
function safe_htmlspecialchars($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

// --- Generación de Salida ---

// 1. Formato EXCEL (Hoja de Cálculo XML)
if ($formato === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_ordenes_' . date('Y-m-d') . '.xls"');

    $xml = '<?xml version="1.0"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    $xml .= '<Worksheet ss:Name="Ordenes"><Table>';
    
    // Encabezados
    $headers = ['ID Orden', 'Codigo Orden', 'Nro Compra Cliente', 'Estado', 'Fecha Creacion', 'Fecha Fin', 'Cliente', 'RNC Cliente', 'Contactos', 'Producto', 'Operacion/Buque', 'Division', 'Supervisor', 'Observaciones'];
    $xml .= '<Row>';
    foreach($headers as $header) { $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>'; }
    $xml .= '</Row>';
    
    // Filas (con orden explícito para coincidir con los encabezados)
    foreach ($ordenes as $orden) {
        $xml .= '<Row>';
        $xml .= '<Cell><Data ss:Type="Number">' . safe_htmlspecialchars($orden['id']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['codigo_orden']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['numero_orden_compra']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['estado_orden']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['fecha_creacion']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['fecha_finalizacion']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['nombre_cliente']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['rnc_cliente']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['contactos']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['nombre_producto']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['nombre_operacion']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['nombre_division']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['supervisor_nombre']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . safe_htmlspecialchars($orden['observaciones']) . '</Data></Cell>';
        $xml .= '</Row>';
    }

    $xml .= '</Table></Worksheet></Workbook>';
    echo $xml;
    exit();
}

// 2. Formato HTML (Ver en Pantalla o Imprimir/PDF)
$is_print_view = ($formato === 'print');
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">Reporte General de Órdenes</h1>
    
    <?php if (!$is_print_view): ?>
    <div class="alert alert-info">Mostrando <?php echo count($ordenes); ?> resultados.</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover">
                 <thead class="table-dark">
                    <tr>
                        <th>ID</th><th>Código</th><th>Cliente</th><th>Contacto(s)</th><th>Producto</th>
                        <th>Operación</th><th>Supervisor</th><th>Estado</th><th>Fechas</th><th>Obs.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ordenes)): ?>
                        <tr><td colspan="10" class="text-center">No se encontraron órdenes con los filtros aplicados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ordenes as $orden): ?>
                            <tr>
                                <td><?php echo safe_htmlspecialchars($orden['id']); ?></td>
                                <td>
                                    <?php echo safe_htmlspecialchars($orden['codigo_orden']); ?>
                                    <br><small class="text-muted">Nro. Compra: <?php echo safe_htmlspecialchars($orden['numero_orden_compra']); ?></small>
                                </td>
                                <td><?php echo safe_htmlspecialchars($orden['nombre_cliente']); ?></td>
                                <td style="min-width: 250px;"><?php echo safe_htmlspecialchars($orden['contactos']); ?></td>
                                <td><?php echo safe_htmlspecialchars($orden['nombre_producto']); ?></td>
                                <td><?php echo safe_htmlspecialchars($orden['nombre_operacion']); ?></td>
                                <td><?php echo safe_htmlspecialchars($orden['supervisor_nombre']); ?></td>
                                <td><?php echo safe_htmlspecialchars($orden['estado_orden']); ?></td>
                                <td>
                                    <small>Creación: <?php echo date('d/m/Y', strtotime($orden['fecha_creacion'])); ?></small>
                                    <?php if($orden['fecha_finalizacion']): ?>
                                        <br><small>Fin: <?php echo date('d/m/Y', strtotime($orden['fecha_finalizacion'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="min-width: 200px;"><?php echo safe_htmlspecialchars($orden['observaciones']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($is_print_view): ?>
<script>
    window.onload = function() { window.print(); };
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
