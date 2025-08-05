<?php
// novedades/store_horas_extras_admin.php

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: horas_extras_admin.php');
    exit();
}

try {
    // 1. Recoger los datos
    $id_contrato = filter_input(INPUT_POST, 'id_contrato', FILTER_VALIDATE_INT);
    $fecha_novedad = $_POST['fecha_novedad'];
    $cantidad_horas = filter_input(INPUT_POST, 'cantidad_horas', FILTER_VALIDATE_FLOAT);

    if (!$id_contrato || !$fecha_novedad || $cantidad_horas === false || $cantidad_horas <= 0) {
        throw new Exception("Datos inválidos o incompletos. La cantidad de horas debe ser mayor a cero.");
    }

    // 2. Obtener el salario mensual del contrato y el ID del concepto
    $stmt_contrato = $pdo->prepare("SELECT salario_mensual_bruto FROM Contratos WHERE id = ?");
    $stmt_contrato->execute([$id_contrato]);
    $salario_mensual = $stmt_contrato->fetchColumn();

    if (!$salario_mensual || $salario_mensual <= 0) {
        throw new Exception("El empleado no tiene un salario mensual configurado para calcular las horas extras.");
    }

    $id_concepto_he = $pdo->query("SELECT id FROM ConceptosNomina WHERE codigo_concepto = 'ING-HE-ADMIN'")->fetchColumn();
    if (!$id_concepto_he) {
        throw new Exception("El concepto de nómina 'ING-HE-ADMIN' no está configurado en el sistema. Por favor, créelo desde la Gestión de Conceptos.");
    }
    
    // 3. Aplicar la lógica de negocio para calcular el monto
    $salario_por_dia = $salario_mensual / 23.83;
    $salario_por_hora = $salario_por_dia / 8;
    $valor_hora_extra = $salario_por_hora * 1.35; // 100% base + 35% de recargo
    $monto_a_pagar = round($valor_hora_extra * $cantidad_horas, 2);

    // 4. Guardar el resultado como una novedad de monto normal
    $sql_insert = "INSERT INTO NovedadesPeriodo (id_contrato, id_concepto, periodo_aplicacion, monto_valor, estado_novedad) VALUES (?, ?, ?, ?, 'Pendiente')";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([$id_contrato, $id_concepto_he, $fecha_novedad, $monto_a_pagar]);

    $mensaje_exito = "Horas extras guardadas correctamente para el período del " . date("d/m/Y", strtotime($fecha_novedad)) . ". Monto calculado: $" . number_format($monto_a_pagar, 2);
    header("Location: horas_extras_admin.php?status=success&message=" . urlencode($mensaje_exito));
    exit();

} catch (Exception $e) {
    header('Location: horas_extras_admin.php?status=error&message=' . urlencode('Error: ' . $e->getMessage()));
    exit();
}
?>
