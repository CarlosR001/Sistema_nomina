<?php
// export_banco/export.php - v1.1 (Con Reporte de Errores)

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
        die("Error: No se encontraron registros de empleados en la nómina seleccionada (ID: {$nomina_id}).");
    }

    $datos_exportacion = [];
    $empleados_sin_cuenta = [];
    $empleados_pago_cero = [];
    $linea_num = 1; // <-- INICIO: Se añade el contador de secuencia

    foreach ($contratos as $contrato_id) {
        // 2. Obtener los datos del empleado
        $stmt_empleado = $pdo->prepare("
            SELECT e.nombres, e.primer_apellido, e.segundo_apellido, e.numero_cuenta_bancaria, e.tipo_cuenta_bancaria
            FROM empleados e
            JOIN contratos c ON e.id = c.id_empleado
            WHERE c.id = ?
        ");
        $stmt_empleado->execute([$contrato_id]);
        $empleado = $stmt_empleado->fetch();
        $nombre_completo = trim($empleado['nombres'] . ' ' . $empleado['primer_apellido'] . ' ' . $empleado['segundo_apellido']);

        // 3. PRIMERA VALIDACIÓN: Verificar si tiene cuenta bancaria
        if (empty($empleado['numero_cuenta_bancaria'])) {
            $empleados_sin_cuenta[] = $nombre_completo;
            continue;
        }

        // 4. Calcular el neto a pagar
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

        // 5. SEGUNDA VALIDACIÓN: Verificar si el pago neto es positivo
        if ($neto_a_pagar <= 0) {
            $empleados_pago_cero[] = $nombre_completo;
            continue;
        }
        
        $tipo_cuenta = ($empleado['tipo_cuenta_bancaria'] == 'Corriente') ? '2' : '1';
        $monto_neto = $neto_a_pagar; // Sin redondear

        // Se añade la secuencia a la línea de exportación
        $datos_exportacion[] = [
            $empleado['numero_cuenta_bancaria'],
            $nombre_completo,
            $linea_num, // <-- CAMBIO: Se inserta el número de secuencia
            $monto_neto,
            $descripcion_pago
        ];
        
        $linea_num++; // <-- FIN: Se incrementa el contador
    }


    // 6. REPORTE FINAL: Si después de todo, no hay datos, mostrar el informe de errores.
    if (empty($datos_exportacion)) {
        $html_error = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error de Exportación</title>';
        $html_error .= '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
        $html_error .= '<body class="bg-light"><div class="container mt-5"><div class="alert alert-danger">';
        $html_error .= '<h4 class="alert-heading">No se generó el archivo de exportación</h4>';
        $html_error .= '<p>No se encontraron empleados que cumplan los requisitos para el pago.</p><hr>';
        
        if (!empty($empleados_sin_cuenta)) {
            $html_error .= '<p class="mb-1"><strong>Empleados omitidos por no tener cuenta bancaria registrada:</strong></p><ul>';
            foreach ($empleados_sin_cuenta as $nombre) { $html_error .= '<li>' . htmlspecialchars($nombre) . '</li>'; }
            $html_error .= '</ul>';
        }
        if (!empty($empleados_pago_cero)) {
            $html_error .= '<p class="mb-1"><strong>Empleados omitidos por tener pago neto cero o negativo:</strong></p><ul>';
            foreach ($empleados_pago_cero as $nombre) { $html_error .= '<li>' . htmlspecialchars($nombre) . '</li>'; }
            $html_error .= '</ul>';
        }
        
        $html_error .= '<hr><p>Por favor, actualice los datos de los empleados o revise la nómina y vuelva a intentarlo.</p>';
        $html_error .= '</div></div></body></html>';
        die($html_error);
    }

     // 7. Si hay datos, generar el archivo con el formato exacto y sin comillas
     $nombre_archivo = "pago_nomina_ID-{$nomina_id}_" . date('Y-m-d') . ".txt";
     header('Content-Type: text/plain; charset=utf-8'); // Se cambia a text/plain para evitar auto-formateo
     header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
 
     $output = fopen('php://output', 'w');
     foreach ($datos_exportacion as $linea_array) {
         // Se construye la línea manualmente para controlar el formato
         $linea_string = implode(';', $linea_array);
         // Se escribe la línea directamente, seguida de un salto de línea de Windows (CRLF)
         fwrite($output, $linea_string . "\r\n");
     }
     fclose($output);
     exit();
 
 } catch (PDOException $e) {
     die("Error de base de datos: " . $e->getMessage());
 }
 
