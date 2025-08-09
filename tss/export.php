<?php
// tss/export.php - v2.0 (Exportador Final desde Sesión)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// 1. VERIFICAR SI HAY DATOS EN LA SESIÓN
// Si el usuario llega aquí sin pasar por la vista previa, no habrá nada que exportar.
if (!isset($_SESSION['tss_export_data'], $_SESSION['tss_export_period'])) {
    header('Location: index.php?status=error&message=' . urlencode('No hay datos para exportar. Por favor, genere una previsualización primero.'));
    exit();
}

// 2. RESCATAR LOS DATOS DE LA SESIÓN
// Estos datos fueron calculados y preparados por preview.php
$empleados_data = $_SESSION['tss_export_data'];
$period_info = $_SESSION['tss_export_period'];
$year = $period_info['year'];
$month = $period_info['month'];
$periodo_tss = sprintf('%04d%02d', $year, $month);

      // NO BORRAR LA SESIÓN AQUÍ para permitir refrescar la página.
      
      try {
        $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
        $rnc_empresa = $configs_db['RNC_EMPRESA'] ?? '';
        if (empty($rnc_empresa)) {
            throw new Exception("El RNC de la empresa no está configurado.");
        }
        
        $file_content = [];
        $clave_nomina = $rnc_empresa . $periodo_tss;

        foreach ($empleados_data as $emp) {
            $tipo_ingreso_tss = ($emp['tipo_nomina'] === 'Inspectores') ? '05' : '01';
            $sexo_tss = ($emp['sexo'] === 'Masculino') ? 'M' : 'F';
            
            // --- INICIO DE LA CORRECCIÓN ---
            // Si la fecha de nacimiento es nula o vacía, dejarla en blanco para la TSS.
            $fecha_nac_tss = !empty($emp['fecha_nacimiento']) ? date('Ymd', strtotime($emp['fecha_nacimiento'])) : '';
            // --- FIN DE LA CORRECCIÓN ---

            $linea = [
                $clave_nomina,
                'C', // Tipo de Documento (Cédula)
                str_replace('-', '', $emp['cedula']),
                $emp['nombres'],
                $emp['primer_apellido'],
                $emp['segundo_apellido'] ?? '',
                $sexo_tss,
                $fecha_nac_tss,
                number_format($emp['salario_cotizable_tss'], 2, '.', ''),
                number_format(0, 2, '.', ''), // Aporte Voluntario
                number_format($emp['base_isr'], 2, '.', ''),
                $tipo_ingreso_tss,
                number_format($emp['otras_remuneraciones'], 2, '.', ''),
                '', // RNC Agente retención
            number_format(0, 2, '.', ''), // Remuneraciones otros agentes
            number_format(0, 2, '.', ''), // saldo a favor del periodo
            number_format(0, 2, '.', ''), // Regalia Pascual(Saldo 13)
            number_format(0, 2, '.', ''), // preaviso, cesantia...
            number_format(0, 2, '.', ''), // Retencion pension alimenticia
            number_format($emp['salario_cotizable_tss'], 2, '.', ''), // salario infotep
        ];

        $file_content[] = implode('|', $linea);
    }

    // 5. GENERAR Y ENVIAR EL ARCHIVO
    $file_name = "AUTODETERMINACION_{$periodo_tss}.txt";
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');

    echo implode("\r\n", $file_content);
    exit();

} catch (Exception $e) {
    header('Location: index.php?status=error&message=' . urlencode('Error al generar el archivo: ' . $e->getMessage()));
    exit();
}
