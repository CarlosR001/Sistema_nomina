<?php
// tss/export.php - v1.1 (Lógica de Empleados Corregida)

require_once '../auth.php';
require_login();
require_role(['Admin', 'Contabilidad']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['year'], $_POST['month'])) {
    header('Location: index.php?status=error&message=' . urlencode('Solicitud no válida.'));
    exit();
}

$year = (int)$_POST['year'];
$month = (int)$_POST['month'];
$periodo_tss = sprintf('%04d%02d', $year, $month);

if (!checkdate($month, 1, $year)) {
    header('Location: index.php?status=error&message=' . urlencode('Fecha inválida.'));
    exit();
}

try {
    // 1. OBTENER DATOS DE CONFIGURACIÓN
    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $rnc_empresa = $configs_db['RNC_EMPRESA'] ?? '';

    if (empty($rnc_empresa) || $rnc_empresa === 'XXXXXXXXX') {
        throw new Exception("El RNC de la empresa no está configurado. Por favor, actualícelo en la Configuración Global.");
    }
    
    // 2. OBTENER DATOS DE EMPLEADOS Y SUS TOTALES DEL MES
    // La consulta ahora se asegura de traer el tipo_nomina para la lógica de negocio.
    $sql = "
        SELECT
            e.cedula,
            e.nss,
            e.nombres,
            e.primer_apellido,
            e.segundo_apellido,
            e.sexo,
            e.fecha_nacimiento,
            c.tipo_nomina, -- CAMPO CLAVE PARA LA LÓGICA
            SUM(CASE WHEN cn.afecta_tss = 1 AND nd.tipo_concepto = 'Ingreso' THEN nd.monto_resultado ELSE 0 END) as salario_cotizable_tss,
            SUM(CASE WHEN cn.afecta_isr = 1 AND nd.tipo_concepto = 'Ingreso' THEN nd.monto_resultado ELSE 0 END) as base_isr,
            SUM(CASE WHEN cn.afecta_tss = 0 AND cn.tipo_concepto = 'Ingreso' THEN nd.monto_resultado ELSE 0 END) as otras_remuneraciones
        FROM NominasProcesadas np
        JOIN NominaDetalle nd ON np.id = nd.id_nomina_procesada
        JOIN Contratos c ON nd.id_contrato = c.id
        JOIN Empleados e ON c.id_empleado = e.id
        JOIN ConceptosNomina cn ON nd.codigo_concepto = cn.codigo_concepto
        WHERE YEAR(np.periodo_fin) = ? AND MONTH(np.periodo_fin) = ?
        GROUP BY e.id, c.tipo_nomina -- Agrupamos por empleado y su tipo de nómina
        ORDER BY e.nombres, e.primer_apellido
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$year, $month]);
    $empleados_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($empleados_data)) {
        throw new Exception("No se encontraron datos de nómina procesados para el período seleccionado ($month/$year).");
    }

    // 3. CONSTRUIR EL CONTENIDO DEL ARCHIVO
    $file_content = [];
    $clave_nomina = $rnc_empresa . $periodo_tss;

    foreach ($empleados_data as $emp) {
        
        // --- INICIO DE LA LÓGICA CORREGIDA ---
        // Asignamos el "Tipo de Ingreso" correcto según el tipo de nómina del empleado.
        $tipo_ingreso_tss = '01'; // Por defecto, 'Normal' para empleados fijos.
        if ($emp['tipo_nomina'] === 'Inspectores') {
            $tipo_ingreso_tss = '05'; // Código para "Salario prorrateado semanal/bisemanal"
        }
        // --- FIN DE LA LÓGICA CORREGIDA ---

        $tipo_doc = 'C';
        $sexo_tss = ($emp['sexo'] === 'Masculino') ? 'M' : 'F';
        $fecha_nac_tss = date('Ymd', strtotime($emp['fecha_nacimiento']));
        
        $linea = [
            $clave_nomina,
            $tipo_doc,
            str_replace('-', '', $emp['cedula']),
            $emp['nombres'],
            $emp['primer_apellido'],
            $emp['segundo_apellido'] ?? '',
            $sexo_tss,
            $fecha_nac_tss,
            number_format($emp['salario_cotizable_tss'], 2, '.', ''),
            number_format(0, 2, '.', ''), // Aporte Voluntario
            number_format($emp['base_isr'], 2, '.', ''),
            $tipo_ingreso_tss, // Variable corregida
            number_format($emp['otras_remuneraciones'], 2, '.', ''),
            '', // RNC/Ced. Agente ret
            number_format(0, 2, '.', ''), // Remuneraciones otros agentes
            number_format(0, 2, '.', ''), // saldo a favor del periodo
            number_format(0, 2, '.', ''), // Regalia Pascual(Saldo 13)
            number_format(0, 2, '.', ''), // preaviso, cesantia...
            number_format(0, 2, '.', ''), // Retencion pension alimenticia
            number_format($emp['salario_cotizable_tss'], 2, '.', ''), // salario infotep
        ];

        $file_content[] = implode('|', $linea);
    }

    // 4. GENERAR Y ENVIAR EL ARCHIVO
    $file_name = "AUTODETERMINACION_{$periodo_tss}.txt";
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');

    echo implode("\r\n", $file_content);
    exit();

} catch (Exception $e) {
    header('Location: index.php?status=error&message=' . urlencode('Error al generar el archivo: ' . $e->getMessage()));
    exit();
}
?>
