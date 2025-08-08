<?php
// export_banco/export.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acceso no permitido.");
}

$nomina_id = $_POST['nomina_id'] ?? null;
$descripcion_pago = $_POST['descripcion_pago'] ?? 'Pago de Nomina';

if (!$nomina_id) {
    die("Debe seleccionar una nómina.");
}

try {
    // 1. Obtener los contratos procesados en esta nómina
    $stmt_contratos = $pdo->prepare("SELECT DISTINCT id_contrato FROM nominadetalle WHERE id_nomina_procesada = ?");
    $stmt_contratos->execute([$nomina_id]);
    $contratos = $stmt_contratos->fetchAll(PDO::FETCH_COLUMN);

    if (empty($contratos)) {
        die("No se encontraron empleados en la nómina seleccionada.");
    }

    $datos_exportacion = [];
    $linea_num = 1;

    foreach ($contratos as $contrato_id) {
        // 2. Para cada contrato, calcular el neto a pagar
        $stmt_neto = $pdo->prepare("
            SELECT
                SUM(CASE WHEN tipo_concepto = 'Ingreso' THEN monto_resultado ELSE 0 END) as total_ingresos,
                SUM(CASE WHEN tipo_concepto = 'Deducción' THEN monto_resultado ELSE 0 END) as total_deducciones
            FROM nominadetalle
            WHERE id_contrato = ? AND id_nomina_procesada = ?
        ");
        $stmt_neto->execute([$contrato_id, $nomina_id]);
        $montos = $stmt_neto->fetch();
        
        $neto_a_pagar = ($montos['total_ingresos'] ?? 0) - ($montos['total_deducciones'] ?? 0);

        // Si el neto es cero o negativo, no se incluye en el archivo
        if ($neto_a_pagar <= 0) {
            continue;
        }

        // 3. Obtener los datos del empleado y su cuenta bancaria
        $stmt_empleado = $pdo->prepare("
            SELECT e.nombres, e.primer_apellido, e.segundo_apellido, e.numero_cuenta_bancaria, e.tipo_cuenta_bancaria
            FROM empleados e
            JOIN contratos c ON e.id = c.id_empleado
            WHERE c.id = ?
        ");
        $stmt_empleado->execute([$contrato_id]);
        $empleado = $stmt_empleado->fetch();

        // Validar que el empleado tenga cuenta bancaria
        if (empty($empleado['numero_cuenta_bancaria'])) {
            // Opcional: podrías registrar un log de empleados sin cuenta
            continue;
        }
        
        // Formatear los datos según la especificación
        $numero_cuenta = $empleado['numero_cuenta_bancaria'];
        $nombre_completo = trim($empleado['nombres'] . ' ' . $empleado['primer_apellido'] . ' ' . $empleado['segundo_apellido']);
        $tipo_cuenta = ($empleado['tipo_cuenta_bancaria'] == 'Corriente') ? '2' : '1';
        $monto_neto = number_format($neto_a_pagar, 2, '.', ''); // Formato sin comas, con punto decimal

        $datos_exportacion[] = [
            $numero_cuenta,
            $nombre_completo,
            $tipo_cuenta,
            $monto_neto,
            $descripcion_pago
        ];
        $linea_num++;
    }

    // 4. Generar el archivo CSV y forzar la descarga
    $nombre_archivo = "pago_nomina_ID-{$nomina_id}_" . date('Y-m-d') . ".txt";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

    $output = fopen('php://output', 'w');

    foreach ($datos_exportacion as $linea) {
        fputcsv($output, $linea, ';');
    }
    
    fclose($output);
    exit();

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
