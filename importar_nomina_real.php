<?php
// importar_nomina_real.php - v3.2 ROBUSTA
// Lee los 4 archivos CSV y carga el escenario de nómina real completo.

set_time_limit(300);
echo "<pre style='font-family: monospace; background-color: #111; color: #0f0; padding: 15px; border-radius: 5px; font-size: 14px;'>";
echo "=====================================================
";
echo "INICIANDO PROCESO DE IMPORTACIÓN REAL (v3.2)
";
echo "=====================================================

";

require_once __DIR__ . '/auth.php';

function parse_csv_to_array($filepath) {
    $data = [];
    if (!file_exists($filepath)) { return null; }
    if (($handle = fopen($filepath, "r")) !== FALSE) {
        for ($i = 0; $i < 6; $i++) { fgetcsv($handle, 2000, ";"); }
        while (($row = fgetcsv($handle, 2000, ";")) !== FALSE) {
            if (isset($row[1]) && is_numeric($row[1]) && !empty(trim($row[2]))) {
                $data[] = $row;
            }
        }
        fclose($handle);
    }
    return $data;
}

try {
    echo "PASO 1: Limpiando tablas...
";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $tables_to_truncate = ['nominadetalle', 'nominasprocesadas', 'registrohoras', 'novedadesperiodo', 'periodosdereporte', 'calendariolaboralrd', 'contratos', 'proyectos', 'zonastransporte', 'conceptosnomina'];
    foreach ($tables_to_truncate as $table) $pdo->exec("TRUNCATE TABLE `{$table}`");
    $pdo->exec("DELETE FROM `usuarios` WHERE `rol` = 'Inspector';");
    $pdo->exec("DELETE FROM `empleados` WHERE `id` NOT IN (SELECT `id_empleado` FROM `usuarios`);");
    echo "-> Limpieza completada.

";

    echo "PASO 2: Creando catálogos base...
";
    $pdo->exec("INSERT INTO `periodosdereporte` (`id`, `fecha_inicio_periodo`, `fecha_fin_periodo`, `tipo_nomina`) VALUES
        (1, '2025-06-30', '2025-07-06', 'Inspectores'), (2, '2025-07-07', '2025-07-13', 'Inspectores'),
        (3, '2025-07-14', '2025-07-20', 'Inspectores'), (4, '2025-07-21', '2025-07-27', 'Inspectores');");
    $pdo->exec("INSERT INTO `conceptosnomina` (`codigo_concepto`, `descripcion_publica`, `tipo_concepto`, `origen_calculo`, `afecta_tss`, `afecta_isr`) VALUES
        ('ING-INCENTIVO', 'Incentivos', 'Ingreso', 'Novedad', 1, 1),
        ('ING-OTROS', 'Otros Ingresos', 'Ingreso', 'Novedad', 1, 1),
        ('DED-CXC', 'Abono a Cuenta por Cobrar', 'Deducción', 'Novedad', 0, 0);");
    $pdo->exec("INSERT INTO `proyectos` (nombre_proyecto) VALUES ('Proyecto Genérico Simulación')");
    $id_proyecto_generico = $pdo->lastInsertId();
    $pdo->exec("INSERT INTO `zonastransporte` (nombre_zona_o_muelle, monto_transporte_completo) VALUES ('Zona Genérica Simulación', 0.00)");
    $id_zona_generica = $pdo->lastInsertId();
    echo "-> Catálogos creados.

";

    echo "PASO 3: Procesando archivos CSV e insertando datos...
";
    $csv_files = [
        '2025-06-30' => 'Ejemplo-Temporal/DEL 30 DE JUNIO AL 06 DE JULIO 2025.csv',
        '2025-07-07' => 'Ejemplo-Temporal/DEL 07 AL 13 DE JULIO 0225.csv',
        '2025-07-14' => 'Ejemplo-Temporal/DEL 14 AL 20 DE JULIO 0225.csv',
        '2025-07-21' => 'Ejemplo-Temporal/DEL 21 AL 27 DE JULIO 0225.csv'
    ];
    
    $empleados_map = [];
    $id_concepto_incentivo = $pdo->query("SELECT id FROM conceptosnomina WHERE codigo_concepto = 'ING-INCENTIVO'")->fetchColumn();
    $id_concepto_otros_ingresos = $pdo->query("SELECT id FROM conceptosnomina WHERE codigo_concepto = 'ING-OTROS'")->fetchColumn();
    $id_concepto_cxc = $pdo->query("SELECT id FROM conceptosnomina WHERE codigo_concepto = 'DED-CXC'")->fetchColumn();

    $stmt_empleado = $pdo->prepare("INSERT INTO `empleados` (id, nombres, primer_apellido) VALUES (?, ?, ?)");
    $stmt_usuario = $pdo->prepare("INSERT INTO `usuarios` (id_empleado, nombre_usuario, contrasena, rol) VALUES (?, ?, ?, 'Inspector')");
    $stmt_contrato = $pdo->prepare("INSERT INTO `contratos` (id, id_empleado, id_posicion, tipo_nomina, tarifa_por_hora, frecuencia_pago, estado_contrato) VALUES (?, ?, 1, 'Inspectores', 150.75, 'Quincenal', 'Vigente')");
    $stmt_novedad = $pdo->prepare("INSERT INTO `novedadesperiodo` (id_contrato, id_concepto, periodo_aplicacion, monto_valor) VALUES (?, ?, ?, ?)");
    $stmt_horas = $pdo->prepare("INSERT INTO `registrohoras` (id_contrato, id_proyecto, id_zona_trabajo, fecha_trabajada, hora_inicio, hora_fin, estado_registro, id_usuario_aprobador) VALUES (?, ?, ?, ?, '08:00:00', ?, 'Aprobado', 4)");

    foreach ($csv_files as $periodo_fecha_inicio => $filepath) {
        echo "
Procesando archivo: {$filepath}...
";
        $data = parse_csv_to_array($filepath);
        if ($data === null) continue;

        foreach ($data as $row_num => $row) {
            $pdo->beginTransaction();
            try {
                $id = (int)$row[1];
                $nombre_completo = preg_replace('/\s+/', ' ', trim($row[2]));
                $parts = explode(' ', $nombre_completo);
                $apellido = array_pop($parts);
                $nombres = implode(' ', $parts);

                if (!isset($empleados_map[$id])) {
                    $stmt_empleado->execute([$id, $nombres, $apellido]);
                    $user_name = strtolower(substr($nombres, 0, 1) . preg_replace('/\s+/', '', $apellido));
                    $stmt_usuario->execute([$id, $user_name, '$2y$10$I0a/I.Y29.Q9t0p1c5B.CO1sJ9V5o2mC8yU7l9r3F6mQ5o7n5D7sK']);
                    $stmt_contrato->execute([$id, $id]);
                    $empleados_map[$id] = true;
                }
                
                $total_horas = (float)str_replace(',', '.', $row[12] ?? '0');
                if ($total_horas > 0) {
                    $seconds = (int)($total_horas * 3600);
                    $stmt_horas->execute([$id, $id_proyecto_generico, $id_zona_generica, $periodo_fecha_inicio, date("H:i:s", strtotime("08:00:00") + $seconds)]);
                }

                $incentivo = (float)str_replace(',', '.', $row[10] ?? '0');
                $otros_ingresos = (float)str_replace(',', '.', $row[8] ?? '0');
                $cxc = (float)str_replace(',', '.', $row[18] ?? '0');

                if ($incentivo > 0) $stmt_novedad->execute([$id, $id_concepto_incentivo, $periodo_fecha_inicio, $incentivo]);
                if ($otros_ingresos > 0) $stmt_novedad->execute([$id, $id_concepto_otros_ingresos, $periodo_fecha_inicio, $otros_ingresos]);
                if ($cxc > 0) $stmt_novedad->execute([$id, $id_concepto_cxc, $periodo_fecha_inicio, $cxc]);

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                echo "
<strong style='color: #f00;'>[ERROR] Falló al procesar la fila #".($row_num+7)." del archivo {$filepath}.</strong>
";
                echo "  - Empleado: {$nombre_completo}
";
                echo "  - Mensaje: " . $e->getMessage() . "
";
                echo "  - La importación se ha detenido. Por favor, revise el CSV.
";
                exit;
            }
        }
        echo "-> Archivo procesado con éxito.
";
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "
=====================================================
";
    echo "PROCESO DE IMPORTACIÓN COMPLETADO CON ÉXITO
";
    echo "=====================================================
";
    echo ">>> La base de datos está lista para la simulación real. <<<
";
    echo ">>> Por favor, ELIMINA ESTE ARCHIVO de tu servidor AHORA. <<<
";

} catch (Exception $e) {
    echo "
<strong style='color: #f00;'>[ERROR CRÍTICO] " . $e->getMessage() . "</strong>
";
}

echo "</pre>";
?>
