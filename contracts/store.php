<?php
// contracts/store.php

require_once '../config/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recoger los datos del formulario
    $id_empleado = $_POST['id_empleado'];
    $id_posicion = $_POST['id_posicion'];
    $tipo_contrato = $_POST['tipo_contrato'];
    $tipo_nomina = $_POST['tipo_nomina'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $frecuencia_pago = $_POST['frecuencia_pago']; // <-- NUEVA VARIABLE
    $salario_mensual_bruto = !empty($_POST['salario_mensual_bruto']) ? $_POST['salario_mensual_bruto'] : null;
    $tarifa_por_hora = !empty($_POST['tarifa_por_hora']) ? $_POST['tarifa_por_hora'] : null;

    // --- Validación básica ---
    if (empty($id_empleado) || empty($id_posicion) || empty($tipo_contrato) || empty($fecha_inicio) || empty($frecuencia_pago)) {
        die("Error: Faltan campos requeridos.");
    }
    
    // Preparar la consulta SQL
    $sql = "INSERT INTO Contratos 
                (id_empleado, id_posicion, tipo_contrato, tipo_nomina, fecha_inicio, frecuencia_pago, salario_mensual_bruto, tarifa_por_hora, estado_contrato) 
            VALUES 
                (:id_empleado, :id_posicion, :tipo_contrato, :tipo_nomina, :fecha_inicio, :frecuencia_pago, :salario_mensual_bruto, :tarifa_por_hora, 'Vigente')";
    
    $stmt = $pdo->prepare($sql);

    // Vincular parámetros
    $stmt->bindParam(':id_empleado', $id_empleado);
    $stmt->bindParam(':id_posicion', $id_posicion);
    $stmt->bindParam(':tipo_contrato', $tipo_contrato);
    $stmt->bindParam(':tipo_nomina', $tipo_nomina);
    $stmt->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt->bindParam(':frecuencia_pago', $frecuencia_pago); // <-- NUEVO BIND
    $stmt->bindParam(':salario_mensual_bruto', $salario_mensual_bruto);
    $stmt->bindParam(':tarifa_por_hora', $tarifa_por_hora);

    try {
        $stmt->execute();
        // Redirigir a la lista de contratos de ese empleado
        header("Location: index.php?employee_id=" . $id_empleado . "&status=success");
        exit();
    } catch (PDOException $e) {
        // Manejar el error
        header("Location: create.php?employee_id=" . $id_empleado . "&status=error&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../index.php");
    exit();
}