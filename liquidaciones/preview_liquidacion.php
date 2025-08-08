<?php
// liquidaciones/preview_liquidacion.php - v1.0 (Motor de Cálculo de Prestaciones)

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

// 1. Recepción y validación de datos del formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_empleado'], $_POST['fecha_salida'], $_POST['motivo_salida'])) {
    header('Location: index.php');
    exit();
}
$id_empleado = (int)$_POST['id_empleado'];
$fecha_salida_str = $_POST['fecha_salida'];
$motivo_salida = $_POST['motivo_salida'];
$fecha_salida = new DateTime($fecha_salida_str);

try {
    // 2. Obtener datos del contrato y empleado
    $sql_contrato = "SELECT c.id, c.fecha_inicio, c.salario_mensual_bruto, c.tarifa_por_hora, e.nombres, e.primer_apellido
                     FROM Contratos c 
                     JOIN Empleados e ON c.id_empleado = e.id 
                     WHERE c.id_empleado = ? AND c.estado_contrato = 'Vigente'";
    $stmt_contrato = $pdo->prepare($sql_contrato);
    $stmt_contrato->execute([$id_empleado]);
    $contrato = $stmt_contrato->fetch(PDO::FETCH_ASSOC);

    if (!$contrato) throw new Exception("No se encontró un contrato vigente para el empleado seleccionado.");

    // 3. Calcular antigüedad del empleado
    $fecha_inicio = new DateTime($contrato['fecha_inicio']);
    $antiguedad = $fecha_inicio->diff($fecha_salida);
    $anios_servicio = $antiguedad->y;
    $meses_servicio = $antiguedad->m;
    $dias_servicio = $antiguedad->d;

    // 4. Calcular el Salario Promedio Diario de los últimos 12 meses (base para todos los cálculos)
    $hace_un_anio = (clone $fecha_salida)->modify('-1 year')->format('Y-m-d');
    $sql_salarios = "SELECT SUM(nd.monto_resultado) as total
                     FROM NominaDetalle nd
                     JOIN NominasProcesadas np ON nd.id_nomina_procesada = np.id
                     WHERE nd.id_contrato = ? AND np.periodo_fin BETWEEN ? AND ? AND nd.tipo_concepto = 'Ingreso' AND nd.codigo_concepto != 'ING-TRANSP'";
    $stmt_salarios = $pdo->prepare($sql_salarios);
    $stmt_salarios->execute([$contrato['id'], $hace_un_anio, $fecha_salida_str]);
    $total_salarios_ult_anio = $stmt_salarios->fetchColumn() ?: 0;
    
    // El salario diario se basa en el promedio de lo ganado, dividido entre 23.83 (factor legal) o 11.91 si es quincenal
    $salario_promedio_diario = $total_salarios_ult_anio > 0 ? ($total_salarios_ult_anio / 12) / 23.83 : ($contrato['salario_mensual_bruto'] / 23.83);
    
    // 5. Aplicar Reglas de Negocio para cada prestación
    $calculos = [];
    $total_liquidacion = 0;

    // a) PREAVISO
    if ($motivo_salida === 'desahucio') {
        if ($anios_servicio >= 1) $dias_a_pagar = 28;
        elseif ($meses_servicio >= 6) $dias_a_pagar = 14;
        elseif ($meses_servicio >= 3) $dias_a_pagar = 7;
        else $dias_a_pagar = 0;
        
        $calculos['Preaviso'] = $dias_a_pagar * $salario_promedio_diario;
    }

    // b) CESANTÍA
    if ($motivo_salida === 'desahucio' || $motivo_salida === 'renuncia') {
        if ($anios_servicio >= 5) $dias_a_pagar = 23 * $anios_servicio;
        elseif ($anios_servicio >= 1) $dias_a_pagar = 21 * $anios_servicio;
        elseif ($meses_servicio >= 6) $dias_a_pagar = 13;
        elseif ($meses_servicio >= 3) $dias_a_pagar = 6;
        else $dias_a_pagar = 0;

        $calculos['Cesantía'] = $dias_a_pagar * $salario_promedio_diario;
    }

    // c) VACACIONES
    if ($anios_servicio >= 5) $dias_a_pagar = 18;
    elseif ($anios_servicio >= 1) $dias_a_pagar = 14;
    elseif ($meses_servicio >= 5) $dias_a_pagar = round(14 * ($meses_servicio / 12));
    else $dias_a_pagar = 0;
    $calculos['Vacaciones'] = $dias_a_pagar * $salario_promedio_diario;


    // d) REGALÍA PROPORCIONAL
    $meses_trabajados_en_anio_salida = $fecha_salida->format('n');
    $salario_acumulado_anio_salida = $total_salarios_ult_anio; // Simplificación, se puede mejorar
    $calculos['Regalía Proporcional'] = ($salario_acumulado_anio_salida / 12);

    foreach($calculos as $monto) $total_liquidacion += $monto;

    // Guardar en sesión para el paso final
    $_SESSION['liquidacion_data'] = [
        'id_empleado' => $id_empleado,
        'id_contrato' => $contrato['id'],
        'fecha_salida' => $fecha_salida_str,
        'motivo_salida' => $motivo_salida,
        'calculos' => $calculos,
        'total' => $total_liquidacion
    ];

} catch (Exception $e) {
    header('Location: index.php?status=error&message=' . urlencode($e->getMessage()));
    exit();
}

require_once '../includes/header.php';
?>

<h1 class="mb-4">Previsualización de Liquidación</h1>

<div class="card">
    <div class="card-header">
        <h5>Resumen para: <?php echo htmlspecialchars($contrato['nombres'] . ' ' . $contrato['primer_apellido']); ?></h5>
    </div>
    <div class="card-body">
        <p><strong>Fecha de Salida:</strong> <?php echo $fecha_salida_str; ?></p>
        <p><strong>Antigüedad:</strong> <?php echo "$anios_servicio años, $meses_servicio meses y $dias_servicio días"; ?></p>
        <p><strong>Salario Promedio Diario (Cálculo):</strong> $<?php echo number_format($salario_promedio_diario, 2); ?></p>
        <hr>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Concepto</th><th class="text-end">Monto Calculado</th></tr>
                </thead>
                <tbody>
                    <?php foreach($calculos as $concepto => $monto): ?>
                    <tr>
                        <td><?php echo $concepto; ?></td>
                        <td class="text-end">$<?php echo number_format($monto, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <td><strong>TOTAL A PAGAR</strong></td>
                        <td class="text-end"><strong>$<?php echo number_format($total_liquidacion, 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="text-center mt-4">
            <form action="procesar_liquidacion.php" method="POST" onsubmit="return confirm('¿Está seguro de que desea procesar esta liquidación? Esta acción generará la nómina final y cambiará el estado del contrato a Finalizado.');">
                <button type="submit" class="btn btn-success btn-lg">Confirmar y Procesar Liquidación</button>
                <a href="index.php" class="btn btn-secondary btn-lg">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
