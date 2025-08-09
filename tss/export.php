<?php
// tss/export.php - v5.0 (Lógica Final y Precisa basada en VBA de TSS)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if (!isset($_SESSION['tss_export_data'], $_SESSION['tss_export_period'])) {
    header('Location: index.php?status=error&message=' . urlencode('No hay datos para exportar. Genere una previsualización primero.'));
    exit();
}

$empleados_data = $_SESSION['tss_export_data'];
$period_info = $_SESSION['tss_export_period'];
$year = $period_info['year'];
$month = $period_info['month'];
$periodo_tss = sprintf('%02d%04d', $month, $year);

// --- FUNCIONES DE AYUDA (Traducción directa de la lógica VBA "Abultar...") ---
function pad_right_space($value, $length) { // Lógica de AbultarParametroCI
    return str_pad(substr(trim($value), 0, $length), $length, ' ', STR_PAD_RIGHT);
}
function pad_left_zero($value, $length) { // Lógica de AbultarParametroN
    $formatted = number_format((float)$value, 2, '.', '');
    return str_pad($formatted, $length, '0', STR_PAD_LEFT);
}
function pad_left_space($value, $length) { // Lógica de AbultarParametroC
    return str_pad(substr(trim($value), 0, $length), $length, ' ', STR_PAD_LEFT);
}
function get_tipo_ingreso_code($tipo_nomina) { // Lógica de AbultarParametroING
    if ($tipo_nomina === 'Inspectores') {
        return '0005'; // Salario prorrateado semanal/bisemanal
    }
    return '0001'; // Normal (Administrativa)
}
// --- FIN DE FUNCIONES ---

try {
    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $rnc_empresa = $configs_db['RNC_EMPRESA'] ?? '';
    if (empty($rnc_empresa)) {
        throw new Exception("El RNC de la empresa no está configurado.");
    }
    
    $file_lines = [];
    
    // 1. CONSTRUIR LÍNEA DE ENCABEZADO
    $header_line = "EAM  " . str_pad($rnc_empresa, 11, ' ', STR_PAD_LEFT) . $periodo_tss;
    $file_lines[] = $header_line;

    // 2. CONSTRUIR LÍNEAS DE DETALLE (mapeando cada campo según función Detalle de VBA)
    $detalle_count = 0;
    foreach ($empleados_data as $emp) {
        $detalle_count++;
        
        $tipo_doc = 'C';
        $num_doc = str_replace('-', '', $emp['cedula']);
        if (!empty($emp['nss'])) {
            $tipo_doc = 'N';
            $num_doc = str_replace('-', '', $emp['nss']);
        }
        $sexo = ($emp['sexo'] === 'Masculino') ? 'M' : 'F';
        $fecha_nac = !empty($emp['fecha_nacimiento']) ? date('dmY', strtotime($emp['fecha_nacimiento'])) : '00000000';
        $tipo_ingreso_code = get_tipo_ingreso_code($emp['tipo_nomina']);
        
        $line = '';
        $line .= 'D';                                                   // 1. Tipo Registro
        $line .= str_pad($detalle_count, 3, '0', STR_PAD_LEFT);           // 2. Secuencia
        $line .= pad_left_space($tipo_doc, 1);                           // 3. Tipo Documento
        $line .= pad_right_space($num_doc, 25);                          // 4. Número Documento (25 Chars)
        $line .= pad_right_space($emp['nombres'], 50);                   // 5. Nombres (50 Chars)
        $line .= pad_right_space($emp['primer_apellido'], 40);          // 6. Primer Apellido (40 Chars)
        $line .= pad_right_space($emp['segundo_apellido'], 40);         // 7. Segundo Apellido (40 Chars)
        $line .= pad_left_space($sexo, 1);                               // 8. Sexo
        $line .= pad_left_space($fecha_nac, 8);                          // 9. Fecha Nacimiento
        $line .= pad_left_zero($emp['salario_cotizable_tss'], 16);       // 10. Salario Cotizable
        $line .= pad_left_zero(0, 16);                                   // 11. Aporte Voluntario
        $line .= pad_left_zero($emp['base_isr'] ?? 0, 16);               // 12. Salario para ISR
        $line .= pad_left_zero($emp['otras_remuneraciones'], 16);         // 13. Otras Remuneraciones
        $line .= pad_left_space('', 11);                                 // 14. RNC Agente Retención
        $line .= pad_left_zero(0, 16);                                   // 15. Remuneraciones Otros Agentes
        $line .= pad_left_zero(0, 16);                                   // 16. Saldo a Favor
        $line .= pad_left_zero(0, 16);                                   // 17. Regalía Pascual
        $line .= pad_left_zero($emp['salario_cotizable_tss'], 16);       // 18. Salario INFOTEP
        $line .= str_pad($tipo_ingreso_code, 4, '0', STR_PAD_LEFT);      // 19. Tipo de Ingreso
        $line .= '';                                                     // 20. Preaviso (se omite si es 0)
        $line .= '';                                                     // 21. Pensión (se omite si es 0)
        $line .= '';                                                     // 22. Accidentes (se omite si es 0)

        $file_lines[] = $line;
    }

    // 3. CONSTRUIR LÍNEA DE SUMARIO
    $footer_line = "S" . str_pad((count($empleados_data) + 2), 6, '0', STR_PAD_LEFT);
    $file_lines[] = $footer_line;

    // 4. GENERAR EL ARCHIVO
    $nombre_archivo = $configs_db['TIPO_ARCHIVO_TSS'] . "_" . $rnc_empresa . "_" . $periodo_tss . ".txt";
    header('Content-Type: text/plain; charset=us-ascii');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

    echo implode("\r\n", $file_lines);
    exit();

} catch (Exception $e) {
    die("Error al generar el archivo: " . $e->getMessage());
}
