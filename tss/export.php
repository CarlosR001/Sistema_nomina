<?php
// tss/export.php - v7.2 (Corrección de formato numérico y longitud)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if (!isset($_SESSION['tss_export_data'], $_SESSION['tss_export_period'])) {
    header('Location: index.php?status=error&message=' . urlencode('No hay datos para exportar. Genere una previsualización primero.'));
    exit();
}

// --- INICIALIZACIÓN Y CONFIGURACIÓN ---
mb_internal_encoding('UTF-8');
$empleados_data = $_SESSION['tss_export_data'];
$period_info = $_SESSION['tss_export_period'];
$year = $period_info['year'];
$month = $period_info['month'];
$periodo_tss_header = sprintf('%02d%04d', $month, $year);
$periodo_tss_filename = sprintf('%02d%04d', $month, $year);

// --- FUNCIONES DE AYUDA MEJORADAS ---
function clean_string($value) {
    $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
    return preg_replace('/[^a-zA-Z0-9\s-]/', '', $value);
}

function pad_right_space($value, $length) {
    $cleaned_value = clean_string($value);
    return str_pad(mb_substr($cleaned_value, 0, $length), $length, ' ', STR_PAD_RIGHT);
}

function pad_left_zero($value, $length) {
    // CORREGIDO: Restaurado al formato original con punto decimal, que es el esperado por TSS.
    // Ejemplo: 15000 -> "0000000015000.00"
    $formatted = number_format((float)$value, 2, '.', '');
    return str_pad($formatted, $length, '0', STR_PAD_LEFT);
}

function pad_left_space($value, $length) {
    $cleaned_value = clean_string($value);
    return str_pad(mb_substr($cleaned_value, 0, $length), $length, ' ', STR_PAD_LEFT);
}

function get_tipo_ingreso_code($tipo_nomina) {
    $code = ($tipo_nomina === 'Inspectores') ? '5' : '1';
    return str_pad($code, 4, '0', STR_PAD_LEFT);
}
// --- FIN DE FUNCIONES ---

try {
    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $rnc_empresa = $configs_db['RNC_EMPRESA'] ?? '';
    if (empty($rnc_empresa)) {
        throw new Exception("El RNC de la empresa no está configurado.");
    }
    
    $file_lines = [];
    
    // 1. CONSTRUIR LÍNEA DE ENCABEZADO (Longitud 19)
    $header_line = "EAM" . pad_left_space($rnc_empresa, 11) . $periodo_tss_header;
    $file_lines[] = $header_line;

    // 2. CONSTRUIR LÍNEAS DE DETALLE (Longitud total debe ser 312)
    $detalle_count = 0;
    foreach ($empleados_data as $emp) {
        $detalle_count++;
        
        $tipo_doc = 'C';
        $num_doc_raw = str_replace('-', '', trim($emp['cedula']));
        $num_doc = (strlen($num_doc_raw) > 0 && strlen($num_doc_raw) < 11) 
                 ? str_pad($num_doc_raw, 11, '0', STR_PAD_LEFT) 
                 : $num_doc_raw;

        $sexo = ($emp['sexo'] === 'Masculino') ? 'M' : 'F';
        $fecha_nac = !empty($emp['fecha_nacimiento']) ? date('dmY', strtotime($emp['fecha_nacimiento'])) : str_repeat(' ', 8);
        $tipo_ingreso_code = get_tipo_ingreso_code($emp['tipo_nomina']);
        
        $line = 'D';                                                       // 1
        $line .= str_pad($detalle_count, 3, '0', STR_PAD_LEFT);             // 3
        $line .= pad_left_space($tipo_doc, 1);                              // 1
        $line .= pad_right_space($num_doc, 25);                             // 25
        $line .= pad_right_space($emp['nombres'], 50);                      // 50
        $line .= pad_right_space($emp['primer_apellido'], 40);             // 40
        $line .= pad_right_space($emp['segundo_apellido'], 40);            // 40
        $line .= pad_left_space($sexo, 1);                                  // 1
        $line .= pad_left_space($fecha_nac, 8);                             // 8
        $line .= pad_left_zero($emp['salario_cotizable_tss'], 16);          // 16 - Salario SS
        $line .= pad_left_zero(0, 16);                                      // 16 - Aporte Voluntario
        $line .= pad_left_zero($emp['base_isr'] ?? 0, 16);                  // 16 - Salario para ISR
        $line .= pad_left_zero($emp['otras_remuneraciones'], 16);            // 16 - Otras Remuneraciones
        $line .= pad_left_space('', 11);                                    // 11 - RNC Agente Retención
        $line .= pad_left_zero(0, 16);                                      // 16 - Impuesto Sobre la Renta Retenido
        $line .= pad_left_zero(0, 16);                                      // 16 - Saldo a Favor
        $line .= pad_left_zero(0, 16);                                      // 16 - Salario Regalía Pascual
        $line .= pad_left_zero($emp['salario_cotizable_tss'], 16);          // 16 - Salario Infotep
        $line .= $tipo_ingreso_code;                                        // 4
        // La suma de las longitudes es exactamente 312, no se necesita relleno adicional.

        $file_lines[] = $line;
    }

    // 3. CONSTRUIR LÍNEA DE SUMARIO (Longitud 7)
    $footer_line = "S" . str_pad((count($empleados_data) + 2), 6, '0', STR_PAD_LEFT);
    $file_lines[] = $footer_line;

    // 4. GENERAR EL ARCHIVO
    $nombre_archivo = "AM_" . clean_string($rnc_empresa) . "_" . $periodo_tss_filename . ".txt";
    header('Content-Type: text/plain; charset=us-ascii');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

    echo implode("\r\n", $file_lines);
    exit();

} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    die("Error al generar el archivo TSS: " . $e->getMessage());
}
