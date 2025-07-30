<?php
// payroll/process.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Administrador'); // Solo Administradores pueden ejecutar el proceso de nómina.

// La conexión $pdo ya está disponible a través de auth.php

// 1. VERIFICACIÓN INICIAL
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo_id'])) {
    header('Location: ' . BASE_URL . 'payroll/index.php?status=error&message=Solicitud%20inv%C3%A1lida.');
    exit();
}

$periodo_id = $_POST['periodo_id'];

$stmt_periodo = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE id = ?");
$stmt_periodo->execute([$periodo_id]);
$periodo = $stmt_periodo->fetch();
if (!$periodo) {
    header('Location: ' . BASE_URL . 'payroll/index.php?status=error&message=Per%C3%ADodo%20no%20encontrado.');
    exit();
}

$tipo_nomina = $periodo['tipo_nomina'];
$fecha_inicio = $periodo['fecha_inicio_periodo'];
$fecha_fin = $periodo['fecha_fin_periodo'];

try {
    $pdo->beginTransaction();

    // 2. CARGAR CONFIGURACIONES GLOBALES
    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $tope_salarial_tss = (float)($configs_db['TSS_TOPE_SALARIAL'] ?? 0);
    $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0);
    $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0);
    $isr_exento_anual = (float)($configs_db['ISR_EXENTO_ANUAL'] ?? 0);

    // 3. CREAR EL REGISTRO MAESTRO DE LA NÓMINA
    $sql_nomina = "INSERT INTO NominasProcesadas (tipo_nomina_procesada, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES (?, ?, ?, ?, 'Pendiente de Aprobación')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    // Usar $_SESSION['user_id'] en lugar de $_SESSION['usuario_id']
    $stmt_nomina->execute([$tipo_nomina, $fecha_inicio, $fecha_fin, $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();

    // 4. OBTENER CONTRATOS A PROCESAR
    if ($tipo_nomina === 'Inspectores') {
        // Solo procesar contratos que tuvieron horas aprobadas en el período.
        $sql_contratos = "SELECT DISTINCT c.id, c.id_empleado, c.tarifa_por_hora
                          FROM Contratos c
                          JOIN RegistroHoras rh ON c.id = rh.id_contrato
                          WHERE c.tipo_nomina = 'Inspectores' AND c.estado_contrato = 'Vigente'
                          AND rh.estado_registro = 'Aprobado' AND rh.fecha_trabajada BETWEEN ? AND ?";
        $stmt_contratos = $pdo->prepare($sql_contratos);
        $stmt_contratos->execute([$fecha_inicio, $fecha_fin]);
        $contratos = $stmt_contratos->fetchAll();
    } else { // Administrativa
        $sql_contratos = "SELECT id, id_empleado, salario_mensual_bruto FROM Contratos WHERE tipo_nomina = 'Administrativa' AND estado_contrato = 'Vigente'";
        $contratos = $pdo->query($sql_contratos)->fetchAll();
    }

    // 5. BUCLE PRINCIPAL: PROCESAR CADA CONTRATO
    foreach($contratos as $contrato) {
        $id_contrato = $contrato['id'];
        $ingresos = [];

        // --- PASO A: CALCULAR INGRESOS BASE ---
        if ($tipo_nomina === 'Inspectores') {
            $feriados = $pdo->prepare("SELECT fecha FROM CalendarioLaboralRD WHERE fecha BETWEEN ? AND ?");
            $feriados->execute([$fecha_inicio, $fecha_fin]);
            $dias_feriados = $feriados->fetchAll(PDO::FETCH_COLUMN);
            $tarifas_transporte = $pdo->query("SELECT id, monto_transporte_completo FROM ZonasTransporte")->fetchAll(PDO::FETCH_KEY_PAIR);
            $tarifa_hora = (float)$contrato['tarifa_por_hora'];

            $stmt_horas = $pdo->prepare("SELECT fecha_trabajada, hora_inicio, hora_fin, id_zona_trabajo FROM RegistroHoras WHERE id_contrato = ? AND estado_registro = 'Aprobado' AND fecha_trabajada BETWEEN ? AND ?");
            $stmt_horas->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
            $registros_horas = $stmt_horas->fetchAll();

            if (empty($registros_horas)) { continue; } // Saltar si no hay horas para este contrato

            $total_horas_laborales = 0; $total_horas_feriado = 0; $total_horas_nocturnas = 0;
            $zonas_por_dia = [];
            foreach ($registros_horas as $registro) {
                $inicio = new DateTime($registro['hora_inicio']); $fin = new DateTime($registro['hora_fin']);
                $duracion = ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;
                if (in_array($registro['fecha_trabajada'], $dias_feriados)) { $total_horas_feriado += $duracion; } else { $total_horas_laborales += $duracion; }
                // Lógica de horas nocturnas (ej: entre 9 PM y 7 AM)
                if ($inicio->format('H') >= 21 || $fin->format('H') <= 7) { $total_horas_nocturnas += $duracion; }
                $zonas_por_dia[$registro['fecha_trabajada']][$registro['id_zona_trabajo']] = true;
            }

            // Cálculo de horas (simplificado para el ejemplo)
            $horas_normales = min($total_horas_laborales, 44);
            $horas_extra_35 = max(0, min($total_horas_laborales - 44, 24));
            $horas_extra_100 = max(0, $total_horas_laborales - 68);
            $pago_transporte = 0;
            foreach ($zonas_por_dia as $zonas) {
                $ids_zonas = array_keys($zonas);
                if (!empty($ids_zonas)) {
                    $id_primera_zona = $ids_zonas[0];
                    $pago_transporte += $tarifas_transporte[$id_primera_zona] ?? 0;
                    if (count($ids_zonas) > 1) {
                         // Asume que las zonas adicionales pagan un 50%
                        for ($i = 1; $i < count($ids_zonas); $i++) {
                            $id_zona_adicional = $ids_zonas[$i];
                            $pago_transporte += ($tarifas_transporte[$id_zona_adicional] ?? 0) * 0.5;
                        }
                    }
                }
            }
            $ingresos = [['ING-HN', 'Ingreso Horas Normales', $horas_normales * $tarifa_hora], ['ING-HE35', 'Ingreso Horas Extras 35%', $horas_extra_35 * $tarifa_hora * 1.35], ['ING-HE100', 'Ingreso Horas Extras 100%', $horas_extra_100 * $tarifa_hora * 2.00], ['ING-HFER', 'Ingreso Horas Feriados', $total_horas_feriado * $tarifa_hora * 2.00], ['ING-HNOT', 'Bono Horas Nocturnas', $total_horas_nocturnas * $tarifa_hora * 0.15], ['ING-TRANSP', 'Pago de Transporte', $pago_transporte]];

        } else { // Administrativa
            $salario_mensual = (float)$contrato['salario_mensual_bruto'];
            $ingresos = [['ING-SALARIO', 'Salario Base Quincenal', $salario_mensual / 2]];
        }

        // --- PASO B: AÑADIR NOVEDADES ---
        $stmt_novedades = $pdo->prepare("SELECT n.*, c.descripcion_publica, c.codigo_concepto FROM NovedadesPeriodo n JOIN ConceptosNomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND n.estado_novedad = 'Pendiente' AND n.periodo_aplicacion BETWEEN ? AND ?");
        $stmt_novedades->execute([$id_contrato, $fecha_inicio, $fecha_fin]);
        foreach ($stmt_novedades->fetchAll() as $novedad) {
            $ingresos[] = [$novedad['codigo_concepto'], $novedad['descripcion_publica'], (float)$novedad['monto_valor']];
            $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Aplicada' WHERE id = ?")->execute([$novedad['id']]);
        }

        // --- PASO C: CALCULAR TOTAL BRUTO Y DEDUCCIONES ---
        $total_ingresos_bruto = 0; $salario_para_tss = 0;
        foreach ($ingresos as $ingreso) {
            $total_ingresos_bruto += $ingreso[2];
            // Conceptos como transporte o combustible no cotizan para TSS
            if (!in_array($ingreso[0], ['ING-TRANSP', 'ING-COMBUS'])) { $salario_para_tss += $ingreso[2]; }
        }

        if ($total_ingresos_bruto <= 0) continue; // No procesar si no hay ingresos

        // --- Cálculo de Deducciones ---
        $deducciones = [];
        $proyeccion_mensual_tss = ($tipo_nomina === 'Inspectores') ? $salario_para_tss * 4.3333 : $salario_para_tss * 2;
        $salario_cotizable_tss = min($proyeccion_mensual_tss, $tope_salarial_tss);
        $divisor_periodo = ($tipo_nomina === 'Inspectores') ? 4.3333 : 2; // Semanal vs Quincenal
        $deduccion_afp = ($salario_cotizable_tss * $porcentaje_afp) / $divisor_periodo;
        $deduccion_sfs = ($salario_cotizable_tss * $porcentaje_sfs) / $divisor_periodo;
        $deducciones[] = ['DED-AFP', 'Aporte AFP (2.87%)', $deduccion_afp];
        $deducciones[] = ['DED-SFS', 'Aporte SFS (3.04%)', $deduccion_sfs];

        $base_para_isr = $total_ingresos_bruto - $deduccion_afp - $deduccion_sfs;
        $proyeccion_periodos_anual = ($tipo_nomina === 'Inspectores') ? 52 : 24;
        $ingreso_anual_proyectado = $base_para_isr * $proyeccion_periodos_anual;
        $deduccion_isr = 0;
        if ($ingreso_anual_proyectado > $isr_exento_anual) {
            // Escalas ISR (deben estar actualizadas en la BBDD o aquí)
            if ($ingreso_anual_proyectado <= 624329) { $isr_anual = ($ingreso_anual_proyectado - 416220.01) * 0.15; }
            else if ($ingreso_anual_proyectado <= 867123) { $isr_anual = 31216.00 + ($ingreso_anual_proyectado - 624329.01) * 0.20; }
            else { $isr_anual = 79776.00 + ($ingreso_anual_proyectado - 867123.01) * 0.25; }
            $deduccion_isr = max(0, $isr_anual / $proyeccion_periodos_anual);
        }
        $deducciones[] = ['DED-ISR', 'Impuesto Sobre la Renta (ISR)', $deduccion_isr];

        // --- PASO D: GUARDAR TODO ---
        $todos_los_conceptos = array_merge($ingresos, $deducciones);
        $sql_detalle = "INSERT INTO NominaDetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detalle = $pdo->prepare($sql_detalle);
        foreach ($todos_los_conceptos as $item) {
            // Solo insertar si el monto es mayor a 0.01 para evitar registros vacíos
            if ($item[2] > 0.01) {
                $tipo = (strpos($item[0], 'DED-') === 0) ? 'Deducción' : 'Ingreso';
                $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $item[0], $item[1], $tipo, $item[2]]);
            }
        }
    } // Fin del bucle foreach

    // 6. CERRAR EL PERÍODO
    $stmt_cerrar = $pdo->prepare("UPDATE PeriodosDeReporte SET estado_periodo = 'Cerrado' WHERE id = ?");
    $stmt_cerrar->execute([$periodo_id]);

    $pdo->commit();
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina_procesada . '&status=processed');
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    // En producción, esto debería registrar el error en un log y mostrar una página de error amigable.
    header('Location: ' . BASE_URL . 'payroll/index.php?status=error&message=' . urlencode("Error Cr%C3%ADtico al procesar la n%C3%B3mina: " . $e->getMessage()));
    exit();
}