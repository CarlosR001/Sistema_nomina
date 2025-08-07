<?php
// tss/export.php - v1.0 (Motor de Exportación a TSS)

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

// Validar que el mes y año sean correctos
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
    
    // 2. OBTENER DATOS DE EMPLEADOS Y SUS NÓMINAS DEL MES
    // Esta es la consulta principal que une todo.
    $sql = "
        SELECT
            e.cedula,
            e.nss,
            e.nombres,
            e.primer_apellido,
            e.segundo_apellido,
            e.sexo,
            e.fecha_nacimiento,
            c.tipo_nomina,
            SUM(CASE WHEN cn.afecta_tss = 1 THEN nd.monto_resultado ELSE 0 END) as salario_cotizable_tss,
            SUM(CASE WHEN cn.afecta_isr = 1 THEN nd.monto_resultado ELSE 0 END) as base_isr,
            SUM(CASE WHEN cn.afecta_tss = 0 AND cn.tipo_concepto = 'Ingreso' THEN nd.monto_resultado ELSE 0 END) as otras_remuneraciones,
            (SELECT GROUP_CONCAT(DISTINCT cn2.codigo_tss) 
             FROM NominaDetalle nd2 
             JOIN ConceptosNomina cn2 ON nd2.codigo_concepto = cn2.codigo_concepto
             WHERE nd2.id_nomina_procesada = np.id AND cn2.tipo_concepto = 'Ingreso' AND cn2.codigo_tss IS NOT NULL) as tipos_ingreso_tss
        FROM NominasProcesadas np
        JOIN NominaDetalle nd ON np.id = nd.id_nomina_procesada
        JOIN Contratos c ON nd.id_contrato = c.id
        JOIN Empleados e ON c.id_empleado = e.id
        JOIN ConceptosNomina cn ON nd.codigo_concepto = cn.codigo_concepto
        WHERE YEAR(np.periodo_fin) = ? AND MONTH(np.periodo_fin) = ?
        GROUP BY e.id, e.cedula, e.nss, e.nombres, e.primer_apellido, e.segundo_apellido, e.sexo, e.fecha_nacimiento, c.tipo_nomina
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
        
        // Mapeo y formateo de datos
        $tipo_doc = 'C'; // Asumimos Cédula
        $sexo_tss = ($emp['sexo'] === 'Masculino') ? 'M' : 'F';
        $fecha_nac_tss = date('Ymd', strtotime($emp['fecha_nacimiento']));

        // Lógica para Tipo de Ingreso
        $codigos_tss_array = !empty($emp['tipos_ingreso_tss']) ? explode(',', $emp['tipos_ingreso_tss']) : [];
        $tipo_ingreso_tss = '01'; // Valor por defecto: Normal
        if(in_array('04', $codigos_tss_array)) {
            $tipo_ingreso_tss = '04'; // Si hay algún pago por hora, se reporta como tal
        }
        
        // Construir la línea para el archivo
        $linea = [
            $clave_nomina,                                  // Clave Nomina
            $tipo_doc,                                      // tipo Doc ('C')
            str_replace('-', '', $emp['cedula']),           // numero Documento (sin guiones)
            $emp['nombres'],                                // Nombres
            $emp['primer_apellido'],                         // 1er. apellido
            $emp['segundo_apellido'] ?? '',                  // 2do. apellido
            $sexo_tss,                                      // Sexo ('M' o 'F')
            $fecha_nac_tss,                                 // Fecha de Nacimiento (YYYYMMDD)
            number_format($emp['salario_cotizable_tss'], 2, '.', ''), // Salario cotizable
            number_format(0, 2, '.', ''),                   // Aporte Voluntario
            number_format($emp['base_isr'], 2, '.', ''),    // Salario ISR
            $tipo_ingreso_tss,                              // Tipo ingreso (mapeado)
            number_format($emp['otras_remuneraciones'], 2, '.', ''), // Otras Remuneraciones
            '',                                             // RNC/Ced. Agente ret
            number_format(0, 2, '.', ''),                   // Remuneraciones otros agentes
            number_format(0, 2, '.', ''),                   // saldo a favor del periodo
            number_format(0, 2, '.', ''),                   // Regalia Pascual(Saldo 13)
            number_format(0, 2, '.', ''),                   // preaviso, cesantia...
            number_format(0, 2, '.', ''),                   // Retencion pension alimenticia
            number_format($emp['salario_cotizable_tss'], 2, '.', ''), // salario infotep
        ];

        $file_content[] = implode('|', $linea);
    }

    // 4. GENERAR Y ENVIAR EL ARCHIVO
    $file_name = "AUTODETERMINACION_{$periodo_tss}.txt";
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');

    echo implode("\r\n", $file_content);
    exit();

} catch (Exception $e) {
    // Si algo sale mal, redirigimos a la página anterior con un mensaje de error.
    header('Location: index.php?status=error&message=' . urlencode($e->getMessage()));
    exit();
}
?>
