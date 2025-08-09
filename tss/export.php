<?php
// tss/export.php - v6.0 (Lógica Final 1:1 con VBA de TSS)

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
$periodo_tss_header = sprintf('%02d%04d', $month, $year);
$periodo_tss_filename = sprintf('%02d%04d', $month, $year);

// --- FUNCIONES DE AYUDA (Traducción directa de la lógica VBA "Abultar...") ---
function pad_right_space($value, $length) {
    return str_pad(substr(trim($value), 0, $length), $length, ' ', STR_PAD_RIGHT);
}
function pad_left_zero($value, $length) {
    $formatted = number_format((float)$value, 2, '.', '');
    return str_pad($formatted, $length, '0', STR_PAD_LEFT);
}
function pad_left_space($value, $length) {
    return str_pad(substr(trim($value), 0, $length), $length, ' ', STR_PAD_LEFT);
}
function get_tipo_ingreso_code($tipo_nomina) {
    $code = ($tipo_nomina === 'Inspectores') ? '5' : '1';
    return str_pad($code, 4, '0', STR_PAD_LEFT);
}
function pad_ingreso_ex($value, $code) {
    if (empty($value) || (float)$value == 0) {
        return ''; // Si el valor es cero, devolver cadena vacía (lógica clave del VBA)
    }
    return $code . pad_left_zero($value, 16);
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
    $header_line = "EAM" . pad_left_space($rnc_empresa, 11) . $periodo_tss_header;
    $file_lines[] = $header_line;

    // 2. CONSTRUIR LÍNEAS DE DETALLE
    $detalle_count = 0;
    foreach ($empleados_data as $emp) {
        $detalle_count++;
        
        // --- Lógica de campos según VBA ---
        $tipo_doc = 'C';
        $num_doc = str_replace('-', '', trim($emp['cedula']));
        if (strlen($num_doc) > 0 && strlen($num_doc) < 11) {
            $num_doc = str_pad($num_doc, 11, '0', STR_PAD_LEFT); // Lógica de Valida_Cedula
        }

        $sexo = ($emp['sexo'] === 'Masculino') ? 'M' : 'F';
        $fecha_nac = !empty($emp['fecha_nacimiento']) ? date('dmY', strtotime($emp['fecha_nacimiento'])) : str_repeat(' ', 8); // 8 espacios
        $tipo_ingreso_code = get_tipo_ingreso_code($emp['tipo_nomina']);
        
        $line = '';
        $line .= 'D';                                                    
        $line .= str_pad($detalle_count, 3, '0', STR_PAD_LEFT);            
        $line .= pad_left_space($tipo_doc, 1);                           
        $line .= pad_right_space($num_doc, 25);                          
        $line .= pad_right_space($emp['nombres'], 50);                   
        $line .= pad_right_space($emp['primer_apellido'], 40);          
        $line .= pad_right_space($emp['segundo_apellido'], 40);         
        $line .= pad_left_space($sexo, 1);                               
        $line .= pad_left_space($fecha_nac, 8);                          
        $line .= pad_left_zero($emp['salario_cotizable_tss'], 16);       
        $line .= pad_left_zero(0, 16);                                   
        $line .= pad_left_zero($emp['base_isr'] ?? 0, 16);               
        $line .= pad_left_zero($emp['otras_remuneraciones'], 16);         
        $line .= pad_left_space('', 11);                                 
        $line .= pad_left_zero(0, 16);                                   
        $line .= pad_left_zero(0, 16);                                   
        $line .= pad_left_zero(0, 16); // Regalía (campo 17 en VBA)
        $line .= pad_left_zero($emp['salario_cotizable_tss'], 16);       
        $line .= $tipo_ingreso_code;                                     
        $line .= pad_ingreso_ex(0, '01'); // Preaviso (campo 18)
        $line .= pad_ingreso_ex(0, '02'); // Pensión (campo 19)
        $line .= pad_ingreso_ex(0, '03'); // Accidentes (campo 20)

        $file_lines[] = $line;
    }

    // 3. CONSTRUIR LÍNEA DE SUMARIO
    $footer_line = "S" . str_pad((count($empleados_data) + 2), 6, '0', STR_PAD_LEFT);
    $file_lines[] = $footer_line;

    // 4. GENERAR EL ARCHIVO
    $nombre_archivo = "AM_" . $rnc_empresa . "_" . $periodo_tss_filename . ".txt";
    header('Content-Type: text/plain; charset=us-ascii');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

    echo implode("\r\n", $file_lines);
    exit();

} catch (Exception $e) {
    die("Error al generar el archivo: " . $e->getMessage());
}
