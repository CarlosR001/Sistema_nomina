<?php
// he_admin/store.php - Motor de cálculo de HE para personal fijo.

require_once '../auth.php';
require_login();
require_permission('reportes.horas_extras.ver');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

try {
    $id_contrato = filter_input(INPUT_POST, 'id_contrato', FILTER_VALIDATE_INT);
    $fecha_trabajada = $_POST['fecha_trabajada'];
    $hora_inicio_num = $_POST['hora_inicio'];
    $hora_fin_num = $_POST['hora_fin'];

    if (!$id_contrato || !$fecha_trabajada || $hora_inicio_num === null || $hora_fin_num === null) {
        throw new Exception("Todos los campos son obligatorios.");
    }

    // --- CONVERSIÓN DE HORA NUMÉRICA A FORMATO TIME (como los inspectores) ---
    $hora_inicio_str = $hora_inicio_num . ":00:00";
    $hora_fin_str = ($hora_fin_num == 24) ? "23:59:59" : $hora_fin_num . ":00:00";
    // --- FIN DE LA CONVERSIÓN ---


    // 1. Obtener datos del contrato: salario y horario normal.
    $stmt_contrato = $pdo->prepare("SELECT salario_mensual_bruto, horario_entrada, horario_salida FROM Contratos WHERE id = ? AND permite_horas_extras = 1");
    $stmt_contrato->execute([$id_contrato]);
    $contrato = $stmt_contrato->fetch(PDO::FETCH_ASSOC);

    if (!$contrato || !$contrato['salario_mensual_bruto']) {
        throw new Exception("Contrato no válido, no permite horas extras o no tiene salario configurado.");
    }
    
    // 2. Calcular las horas extras reales.
    $horario_entrada_normal = new DateTime($contrato['horario_entrada']);
    $horario_salida_normal = new DateTime($contrato['horario_salida']);
    $hora_inicio_real = new DateTime($hora_inicio_str);
    $hora_fin_real = new DateTime($hora_fin_str);

    $horas_extras = 0;
    // Horas extras antes del inicio de la jornada
    if ($hora_inicio_real < $horario_entrada_normal) {
        $diff = $horario_entrada_normal->getTimestamp() - $hora_inicio_real->getTimestamp();
        $horas_extras += $diff / 3600;
    }
    // Horas extras después del fin de la jornada
    if ($hora_fin_real > $horario_salida_normal) {
        $diff = $hora_fin_real->getTimestamp() - $horario_salida_normal->getTimestamp();
        $horas_extras += $diff / 3600;
    }
    
    if ($horas_extras <= 0) {
        throw new Exception("El horario registrado no genera horas extras según el horario normal del contrato (" . $contrato['horario_entrada'] . " - " . $contrato['horario_salida'] . ").");
    }

    // 3. Calcular el monto a pagar según la fórmula de negocio.
    $id_concepto_he = $pdo->query("SELECT id FROM ConceptosNomina WHERE codigo_concepto = 'ING-HE-ADMIN'")->fetchColumn();
    if (!$id_concepto_he) throw new Exception("El concepto 'ING-HE-ADMIN' no está configurado.");
    
    $salario_por_hora = ($contrato['salario_mensual_bruto'] / 23.83) / 8;
    $valor_hora_extra = $salario_por_hora * 1.35;
    $monto_a_pagar = round($valor_hora_extra * $horas_extras, 2);

    // 4. Guardar como una novedad estándar.
    $sql_insert = "INSERT INTO NovedadesPeriodo (id_contrato, id_concepto, periodo_aplicacion, monto_valor, estado_novedad) VALUES (?, ?, ?, ?, 'Pendiente')";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([$id_contrato, $id_concepto_he, $fecha_trabajada, $monto_a_pagar]);

    $mensaje = "Se han registrado " . number_format($horas_extras, 2) . " horas extras. Monto calculado: $" . number_format($monto_a_pagar, 2);
    header("Location: index.php?status=success&message=" . urlencode($mensaje));
    exit();

} catch (Exception $e) {
    header('Location: index.php?status=error&message=' . urlencode('Error: ' . $e->getMessage()));
    exit();
}
