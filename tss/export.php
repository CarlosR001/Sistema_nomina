<?php
// tss/export.php - v3.0 (Nuevo Formato de Ancho Fijo)

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

// --- FUNCIONES DE AYUDA PARA ANCHO FIJO ---
function pad_str($value, $length) {
    return str_pad(substr(trim($value), 0, $length), $length, ' ', STR_PAD_RIGHT);
}
function pad_num($value, $length) {
    $formatted = number_format((float)$value, 2, '', '');
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
        
        $tipo_ingreso_tss = ($emp['tipo_nomina'] === 'Inspectores') ? '05' : '01';
        $sexo_tss = ($emp['sexo'] === 'Masculino') ? 'M' : 'F';
        $fecha_nac_tss = !empty($emp['fecha_nacimiento']) ? date('Ymd', strtotime($emp['fecha_nacimiento'])) : str_repeat(' ', 8);
        
        $line = '';
        $line .= 'D';                                              // Tipo de Registro (1)
        $line .= str_pad($detalle_count, 3, '0', STR_PAD_LEFT);      // Secuencia (3)
        $line .= 'C';                                              // Tipo de Documento (1)
        $line .= str_pad(str_replace('-', '', $emp['cedula']), 11, ' '); // Cédula (11)
        $line .= pad_str($emp['nombres'], 45);                     // Nombres (45)
        $line .= pad_str($emp['primer_apellido'], 35);            // Apellido 1 (35)
        $line .= pad_str($emp['segundo_apellido'], 35);           // Apellido 2 (35)
        $line .= $sexo_tss;                                        // Sexo (1)
        $line .= $fecha_nac_tss;                                   // Fecha Nacimiento (8)
        $line .= pad_num($emp['salario_cotizable_tss'], 15);        // Salario Cotizable TSS (15)
        $line .= pad_num(0, 15);                                   // Aporte Voluntario (15)
        $line .= pad_num($emp['base_isr'], 15);                     // Salario para ISR (15)
        $line .= pad_str($tipo_ingreso_tss, 11);                   // Tipo de Ingreso (11) - Se alarga para coincidir
        $line .= pad_num($emp['otras_remuneraciones'], 15);         // Otras Remuneraciones (15)
        $line .= str_repeat(' ', 11);                              // RNC Agente de Retención (11)
        $line .= pad_num(0, 15);                                   // Remuneraciones Otros Agentes (15)
        $line .= pad_num(0, 15);                                   // Saldo a Favor (15)
        $line .= pad_num(0, 15);                                   // Regalía Pascual (15)
        $line .= pad_num(0, 15);                                   // Preaviso y Cesantía (15)
        $line .= pad_num(0, 15);                                   // Pensión Alimenticia (15)
        $line .= pad_num($emp['salario_cotizable_tss'], 15);        // Salario INFOTEP (15)
        $line .= '0001';                                           // Tipo de Pago (4)

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
