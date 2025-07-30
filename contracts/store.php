<?php
// contracts/store.php

require_once '../auth.php'; // Carga el sistema de autenticación
require_login(); // Asegura que el usuario esté logueado
require_role('Administrador'); // Solo Administradores pueden acceder a esta sección

// La conexión $pdo ya está disponible a través de auth.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recoger los datos del formulario
    $id_empleado = $_POST['id_empleado'];
    $id_posicion = $_POST['id_posicion'];
    $tipo_contrato = $_POST['tipo_contrato'];
    $tipo_nomina = $_POST['tipo_nomina'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $frecuencia_pago = $_POST['frecuencia_pago'];
    $salario_mensual_bruto = !empty($_POST['salario_mensual_bruto']) ? $_POST['salario_mensual_bruto'] : null;
    $tarifa_por_hora = !empty($_POST['tarifa_por_hora']) ? $_POST['tarifa_por_hora'] : null;

    // --- Validación básica ---
    if (empty($id_empleado) || empty($id_posicion) || empty($tipo_contrato) || empty($fecha_inicio) || empty($frecuencia_pago)) {
        // Mejorar el manejo de errores para el usuario
        header("Location: create.php?employee_id=" . $id_empleado . "&status=error&message=Faltan%20campos%20requeridos.");
        exit();
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
    $stmt->bindParam(':frecuencia_pago', $frecuencia_pago);
    $stmt->bindParam(':salario_mensual_bruto', $salario_mensual_bruto);
    $stmt->bindParam(':tarifa_por_hora', $tarifa_por_hora);

    try {
        $stmt->execute();
        // Redirigir a la lista de contratos de ese empleado con mensaje de éxito
        header("Location: index.php?employee_id=" . $id_empleado . "&status=success&message=Contrato%20guardado%20exitosamente.");
        exit();
    } catch (PDOException $e) {
        // Manejar el error y redirigir con mensaje de error
        header("Location: create.php?employee_id=" . $id_empleado . "&status=error&message=" . urlencode("Error al guardar el contrato: " . $e->getMessage()));
        exit();
    }
} else {
    // Si no es una solicitud POST, redirigir al inicio o a una página de error
    header("Location: " . BASE_URL . "index.php");
    exit();
}