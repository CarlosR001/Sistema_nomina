<?php
// tss/export.php - v4.1 (Lógica Final y Precisa basada en VBA de TSS)

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
$periodo_tss = sprintf('%04d%02d', $year, $month);

// --- FUNCIONES DE AYUDA (Traducción directa de la lógica VBA "Abultar...") ---
function pad_right($value, $length) { // Lógica de AbultarParametroCI
    return str_pad(substr(trim($value), 0, $length), $length, ' ', STR_PAD_RIGHT);
}
function pad_left_zeros($value, $length) { // Lógica de AbultarParametroN
    $formatted = number_format((float)$value, 2, '.', '');
    return str_pad($formatted, $length, '0', STR_PAD_LEFT);
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
    $header_line = "EAM  " . $rnc_empresa . $periodo_tss;
    $file_lines[] = $header_line;

         // 2. CONSTRUIR LÍNEAS DE DETALLE
    $detalle_count = 0;
    foreach ($empleados_data as $emp) {
        $detalle_count++;
        
        $tipo_doc = 'C'; 
        $num_doc = str_replace('-', '', $emp['cedula']);
        
        $sexo = ($emp['sexo'] === 'Masculino') ? 'M' : 'F';
        $fecha_nac = !empty($emp['fecha_nacimiento']) ? date('dmY', strtotime($emp['fecha_nacimiento'])) : '00000000';
        
        $tipo_ingreso_code = '0001';
        if ($emp['tipo_nomina'] === 'Inspectores') {
            $tipo_ingreso_code = '0005';
        }
        
        // --- Construcción de la línea de detalle (CORREGIDA) ---
        $line = '';
        $line .= 'D';                                                    
        $line .= str_pad($detalle_count, 3, '0', STR_PAD_LEFT);            
        $line .= $tipo_doc;                                              
        // CORRECCIÓN: El número de documento también se rellena con espacios
        $line .= pad_right($num_doc, 11);                                
        $line .= pad_right($emp['nombres'], 45);                         
        $line .= pad_right($emp['primer_apellido'], 35);                
        $line .= pad_right($emp['segundo_apellido'], 35);               
        $line .= $sexo;                                                  
        $line .= $fecha_nac;                                             
        $line .= pad_left_zeros($emp['salario_cotizable_tss'], 15);      
        $line .= pad_left_zeros(0, 15);                                  
        $line .= pad_left_zeros($emp['base_isr'] ?? 0, 15);               
        $line .= pad_right($tipo_ingreso_code, 11);                      
        $line .= pad_left_zeros($emp['otras_remuneraciones'], 15);         
        $line .= pad_right('', 11);                                      
        $line .= pad_left_zeros(0, 15);                                  
        $line .= pad_left_zeros(0, 15);                                  
        $line .= pad_left_zeros(0, 15);                                  
        $line .= pad_left_zeros(0, 15);                                  
        $line .= pad_left_zeros(0, 15);                                  
        $line .= pad_left_zeros($emp['salario_cotizable_tss'], 15);        
        // CORRECCIÓN: El tipo de pago se rellena a 4 caracteres
        $line .= '0001';                                                 

        $file_lines[] = $line;
    }


    // 3. CONSTRUIR LÍNEA DE SUMARIO
    $footer_line = "S" . str_pad(count($empleados_data), 6, '0', STR_PAD_LEFT);
    $file_lines[] = $footer_line;

    // 4. GENERAR EL ARCHIVO
    $nombre_archivo = "AUTODET_" . $periodo_tss . ".txt";
    header('Content-Type: text/plain; charset=us-ascii');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

    echo implode("\r\n", $file_lines);
    exit();

} catch (Exception $e) {
    die("Error al generar el archivo: " . $e->getMessage());
}
