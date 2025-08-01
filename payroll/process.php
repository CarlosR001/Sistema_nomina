<?php
// payroll/process.php - v8.4 DEBUG
// Añade un bloque de depuración para volcar los valores del cálculo de ISR y diagnosticar el problema.

require_once '../auth.php';
require_login();
require_role('Admin');

function esUltimaSemanaDelMes($fecha_fin_periodo) {
    $fecha = new DateTime($fecha_fin_periodo);
    $dias_en_mes = (int)$fecha->format('t');
    $dia_fin_semana = (int)$fecha->format('d');
    return ($dias_en_mes - $dia_fin_semana) < 7;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo_id'])) {
    header('Location: ' . BASE_URL . 'payroll/index.php?status=error&message=Solicitud%20inv%C3%A1lida.');
    exit();
}

try {
    $periodo_id = $_POST['periodo_id'];
    $stmt_periodo = $pdo->prepare("SELECT * FROM PeriodosDeReporte WHERE id = ?");
    $stmt_periodo->execute([$periodo_id]);
    $periodo = $stmt_periodo->fetch();

    if (!$periodo) { throw new Exception("Período no encontrado."); }

    $tipo_nomina = $periodo['tipo_nomina'];
    $fecha_inicio = $periodo['fecha_inicio_periodo'];
    $fecha_fin = $periodo['fecha_fin_periodo'];
    $es_ultima_semana = esUltimaSemanaDelMes($fecha_fin);
    $mes_actual = date('m', strtotime($fecha_fin));
    $anio_actual = date('Y', strtotime($fecha_fin));

    $pdo->beginTransaction();

    $configs_db = $pdo->query("SELECT clave, valor FROM ConfiguracionGlobal")->fetchAll(PDO::FETCH_KEY_PAIR);
    $tope_salarial_tss = (float)($configs_db['TSS_TOPE_SALARIAL'] ?? 265840.00);
    $porcentaje_afp = (float)($configs_db['TSS_PORCENTAJE_AFP'] ?? 0.0287);
    $porcentaje_sfs = (float)($configs_db['TSS_PORCENTAJE_SFS'] ?? 0.0304);
    $escala_isr = $pdo->query("SELECT * FROM escalasisr WHERE anio_fiscal = {$anio_actual} ORDER BY desde_monto_anual ASC")->fetchAll(PDO::FETCH_ASSOC);

    $stmt_find_nomina = $pdo->prepare("SELECT id FROM NominasProcesadas WHERE periodo_inicio = ? AND periodo_fin = ?");
    if ($stmt_find_nomina->execute([$fecha_inicio, $fecha_fin]) && $existing_nomina = $stmt_find_nomina->fetch()) {
        $pdo->prepare("DELETE FROM NominaDetalle WHERE id_nomina_procesada = ?")->execute([$existing_nomina['id']]);
        $pdo->prepare("DELETE FROM NominasProcesadas WHERE id = ?")->execute([$existing_nomina['id']]);
        $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Pendiente' WHERE periodo_aplicacion = ?")->execute([$fecha_inicio]);
    }

    $sql_nomina = "INSERT INTO NominasProcesadas (tipo_nomina_procesada, periodo_inicio, periodo_fin, id_usuario_ejecutor, estado_nomina) VALUES (?, ?, ?, ?, 'Pendiente de Aprobación')";
    $stmt_nomina = $pdo->prepare($sql_nomina);
    $stmt_nomina->execute([$tipo_nomina, $fecha_inicio, $fecha_fin, $_SESSION['user_id']]);
    $id_nomina_procesada = $pdo->lastInsertId();

    $sql_contratos = "SELECT DISTINCT c.id, c.id_empleado, c.tarifa_por_hora 
                      FROM Contratos c 
                      JOIN NovedadesPeriodo np ON c.id = np.id_contrato
                      WHERE c.tipo_nomina = 'Inspectores' 
                      AND np.periodo_aplicacion = :fecha_inicio
                      AND c.estado_contrato = 'Vigente'";
    $stmt_contratos = $pdo->prepare($sql_contratos);
    $stmt_contratos->execute([':fecha_inicio' => $fecha_inicio]);
    $contratos = $stmt_contratos->fetchAll();

    foreach ($contratos as $contrato) {
        $id_contrato = $contrato['id'];
        $id_empleado = $contrato['id_empleado'];
        $conceptos = [];

        $stmt_novedades = $pdo->prepare("SELECT n.id as novedad_id, n.monto_valor, c.* FROM NovedadesPeriodo n JOIN ConceptosNomina c ON n.id_concepto = c.id WHERE n.id_contrato = ? AND n.estado_novedad = 'Pendiente' AND n.periodo_aplicacion = ?");
        $stmt_novedades->execute([$id_contrato, $fecha_inicio]);
        
        foreach ($stmt_novedades->fetchAll(PDO::FETCH_ASSOC) as $novedad) {
            $codigo = $novedad['codigo_concepto'];
            if (!isset($conceptos[$codigo])) {
                $conceptos[$codigo] = ['desc' => $novedad['descripcion_publica'], 'monto' => 0, 'aplica_tss' => (bool)$novedad['afecta_tss'], 'aplica_isr' => (bool)$novedad['afecta_isr'], 'tipo' => $novedad['tipo_concepto']];
            }
            $conceptos[$codigo]['monto'] += floatval($novedad['monto_valor']);
            $pdo->prepare("UPDATE NovedadesPeriodo SET estado_novedad = 'Aplicada' WHERE id = ?")->execute([$novedad['novedad_id']]);
        }

        $salario_cotizable_tss = 0;
        foreach ($conceptos as $data) { if ($data['tipo'] === 'Ingreso' && $data['aplica_tss']) { $salario_cotizable_tss += $data['monto']; } }
        
        $tope_salarial_semanal = $tope_salarial_tss / 4.333333;
        $salario_cotizable_final_semanal = min($salario_cotizable_tss, $tope_salarial_semanal);
        
        $deduccion_afp = $salario_cotizable_final_semanal * $porcentaje_afp;
        $deduccion_sfs = $salario_cotizable_final_semanal * $porcentaje_sfs;
        $conceptos['DED-AFP'] = ['desc' => 'Aporte AFP (2.87%)', 'monto' => $deduccion_afp, 'tipo' => 'Deducción'];
        $conceptos['DED-SFS'] = ['desc' => 'Aporte SFS (3.04%)', 'monto' => $deduccion_sfs, 'tipo' => 'Deducción'];

        $base_para_isr_semanal = 0;
        foreach ($conceptos as $data) { if ($data['tipo'] === 'Ingreso' && $data['aplica_isr']) { $base_para_isr_semanal += $data['monto']; } }
        $base_para_isr_semanal -= ($deduccion_afp + $deduccion_sfs);
        
        $deduccion_isr = 0;
        if ($es_ultima_semana) {
            $sql_prev_base = "SELECT SUM(nd.monto_resultado) FROM NominaDetalle nd JOIN NominasProcesadas np ON nd.id_nomina_procesada = np.id WHERE nd.id_contrato = ? AND MONTH(np.periodo_fin) = ? AND YEAR(np.periodo_fin) = ? AND nd.codigo_concepto = 'BASE-ISR-SEMANAL'";
            $stmt_prev_base = $pdo->prepare($sql_prev_base);
            $stmt_prev_base->execute([$id_contrato, $mes_actual, $anio_actual]);
            $base_isr_acumulada_previa = (float)$stmt_prev_base->fetchColumn();
            
            $base_isr_mensual_total = $base_para_isr_semanal + $base_isr_acumulada_previa;
            
            // =================== INICIO DEL BLOQUE DE DEPURACIÓN ===================
            // Vamos a analizar el caso de ARIALDY (id_empleado=2) ya que es el primero en dar error
            if ($id_empleado == 2) {
                echo "<pre style='font-family: monospace; border: 2px solid #f00; padding: 10px; background-color: #fff;'>";
                echo "<strong>--- INICIO DE DEBUG PARA EMPLEADO ID: 2 (ARIALDY PAULINO) ---</strong>

";
                
                echo "Base ISR Semanal (Semana 4): " . $base_para_isr_semanal . "
";
                echo "Base ISR Acumulada Previa (Semanas 1-3): " . $base_isr_acumulada_previa . "
";
                echo "<strong>Base ISR Mensual Total: " . $base_isr_mensual_total . "</strong>

";

                $ingreso_anual_proyectado = $base_isr_mensual_total * 12;
                echo "<strong>Ingreso Anual Proyectado: " . $ingreso_anual_proyectado . "</strong>

";

                echo "--- Escala ISR Leída de la Base de Datos ---
";
                print_r($escala_isr);
                echo "
--- Lógica de Cálculo ---
";

                $isr_anual = 0;
                if (count($escala_isr) === 4) {
                    $tramo1_hasta = (float)$escala_isr[0]['hasta_monto_anual'];
                    $tramo2_hasta = (float)$escala_isr[1]['hasta_monto_anual'];
                    $tramo3_hasta = (float)$escala_isr[2]['hasta_monto_anual'];

                    if ($ingreso_anual_proyectado > $tramo3_hasta) {
                        echo "Ha entrado en TRAMO 4 (25%)
";
                        $excedente = $ingreso_anual_proyectado - $tramo3_hasta;
                        $tasa = (float)$escala_isr[3]['tasa_porcentaje'] / 100;
                        $monto_fijo = (float)$escala_isr[3]['monto_fijo_adicional'];
                        $isr_anual = $monto_fijo + ($excedente * $tasa);
                        echo "Cálculo: $monto_fijo + ($excedente * $tasa) = $isr_anual
";
                    } elseif ($ingreso_anual_proyectado > $tramo2_hasta) {
                        echo "Ha entrado en TRAMO 3 (20%)
";
                        $excedente = $ingreso_anual_proyectado - $tramo2_hasta;
                        $tasa = (float)$escala_isr[2]['tasa_porcentaje'] / 100;
                        $monto_fijo = (float)$escala_isr[2]['monto_fijo_adicional'];
                        $isr_anual = $monto_fijo + ($excedente * $tasa);
                        echo "Cálculo: $monto_fijo + ($excedente * $tasa) = $isr_anual
";
                    } elseif ($ingreso_anual_proyectado > $tramo1_hasta) {
                        echo "Ha entrado en TRAMO 2 (15%)
";
                        $excedente = $ingreso_anual_proyectado - $tramo1_hasta;
                        $tasa = (float)$escala_isr[1]['tasa_porcentaje'] / 100;
                        $monto_fijo = (float)$escala_isr[1]['monto_fijo_adicional'];
                        $isr_anual = $monto_fijo + ($excedente * $tasa);
                        echo "Cálculo: $monto_fijo + ($excedente * $tasa) = $isr_anual
";
                    } else {
                        echo "Ha entrado en TRAMO 1 (Exento)
";
                        $isr_anual = 0;
                    }
                }
                
                $deduccion_isr = max(0, $isr_anual / 12);

                echo "
--- Resultado Final ---
";
                echo "<strong>ISR Anual Calculado: " . $isr_anual . "</strong>
";
                echo "<strong>Deducción ISR Mensual (ISR Anual / 12): " . $deduccion_isr . "</strong>

";
                echo "<strong>--- FIN DE DEBUG ---</strong>";
                echo "</pre>";
                die(); // Detenemos la ejecución para ver solo este output
            }
            // =================== FIN DEL BLOQUE DE DEPURACIÓN ===================
            else {
                 // Cálculo normal para los otros empleados
                if (count($escala_isr) === 4) {
                    $tramo1_hasta = (float)$escala_isr[0]['hasta_monto_anual'];
                    $tramo2_hasta = (float)$escala_isr[1]['hasta_monto_anual'];
                    $tramo3_hasta = (float)$escala_isr[2]['hasta_monto_anual'];
                    if ($ingreso_anual_proyectado > $tramo3_hasta) {
                        $excedente = $ingreso_anual_proyectado - $tramo3_hasta;
                        $tasa = (float)$escala_isr[3]['tasa_porcentaje'] / 100;
                        $monto_fijo = (float)$escala_isr[3]['monto_fijo_adicional'];
                        $isr_anual = $monto_fijo + ($excedente * $tasa);
                    } elseif ($ingreso_anual_proyectado > $tramo2_hasta) {
                        $excedente = $ingreso_anual_proyectado - $tramo2_hasta;
                        $tasa = (float)$escala_isr[2]['tasa_porcentaje'] / 100;
                        $monto_fijo = (float)$escala_isr[2]['monto_fijo_adicional'];
                        $isr_anual = $monto_fijo + ($excedente * $tasa);
                    } elseif ($ingreso_anual_proyectado > $tramo1_hasta) {
                        $excedente = $ingreso_anual_proyectado - $tramo1_hasta;
                        $tasa = (float)$escala_isr[1]['tasa_porcentaje'] / 100;
                        $monto_fijo = (float)$escala_isr[1]['monto_fijo_adicional'];
                        $isr_anual = $monto_fijo + ($excedente * $tasa);
                    } else {
                        $isr_anual = 0;
                    }
                }
                $deduccion_isr = max(0, $isr_anual / 12);
            }
        }
        
        $conceptos['BASE-ISR-SEMANAL'] = ['desc' => 'Base ISR Semanal', 'monto' => $base_para_isr_semanal, 'tipo' => 'Base de Cálculo'];
        if($es_ultima_semana) {
            $conceptos['BASE-ISR-MENSUAL'] = ['desc' => 'Base ISR Mensual Acumulada', 'monto' => $base_isr_mensual_total, 'tipo' => 'Base de Cálculo'];
        }
        $conceptos['DED-ISR'] = ['desc' => 'Impuesto Sobre la Renta (ISR)', 'monto' => $deduccion_isr, 'tipo' => 'Deducción'];
        
        $sql_detalle = "INSERT INTO NominaDetalle (id_nomina_procesada, id_contrato, codigo_concepto, descripcion_concepto, tipo_concepto, monto_resultado) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detalle = $pdo->prepare($sql_detalle);
        foreach ($conceptos as $codigo => $data) {
            if (isset($data['monto']) && abs($data['monto']) > 0.001) {
                $stmt_detalle->execute([$id_nomina_procesada, $id_contrato, $codigo, $data['desc'], $data['tipo'], $data['monto']]);
            }
        }
    }

    $stmt_cerrar = $pdo->prepare("UPDATE PeriodosDeReporte SET estado_periodo = 'Cerrado' WHERE id = ?");
    $stmt_cerrar->execute([$periodo_id]);

    $pdo->commit();
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina_procesada . '&status=processed');
    exit();

} catch (Exception $e) {
    if($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Error al procesar la nómina: " . $e->getMessage());
    header('Location: ' . BASE_URL . 'payroll/index.php?status=error&message=' . urlencode('Error crítico al procesar la nómina: ' . $e->getMessage()));
    exit();
}
?>