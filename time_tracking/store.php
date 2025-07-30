<?php
// time_tracking/store.php
require_once '../config/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recoger datos
    $id_contrato = $_POST['id_contrato'];
    $fecha_trabajada = $_POST['fecha_trabajada'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $id_proyecto = $_POST['id_proyecto'];
    $id_zona_trabajo = $_POST['id_zona_trabajo'];

    // --- NUEVA VALIDACIÓN: HORA DE FIN DEBE SER MAYOR A HORA DE INICIO ---
    if ($hora_fin <= $hora_inicio) {
        header("Location: index.php?status=error&message=invalid_time_range");
        exit();
    }

    // --- VALIDACIÓN DE PERÍODO ABIERTO (ya existente) ---
    $stmt_periodo = $pdo->prepare("SELECT id FROM PeriodosDeReporte WHERE tipo_nomina = 'Inspectores' AND estado_periodo = 'Abierto' AND :fecha_trabajada BETWEEN fecha_inicio_periodo AND fecha_fin_periodo");
    $stmt_periodo->execute([':fecha_trabajada' => $fecha_trabajada]);
    
    if (!$stmt_periodo->fetch()) {
        header("Location: index.php?status=error&message=period_closed_for_date");
        exit();
    }

    // --- NUEVA VALIDACIÓN: VERIFICAR SI LA FECHA ESTÁ EN UN PERÍODO ABIERTO ---
    $stmt_periodo = $pdo->prepare("SELECT id FROM PeriodosDeReporte WHERE tipo_nomina = 'Inspectores' AND estado_periodo = 'Abierto' AND :fecha_trabajada BETWEEN fecha_inicio_periodo AND fecha_fin_periodo");
    $stmt_periodo->execute([':fecha_trabajada' => $fecha_trabajada]);
        if (!$stmt_periodo->fetch()) {
        // Si no se encuentra un período abierto para esa fecha, redirige con error
        header("Location: index.php?status=error&message=period_closed_for_date");
        exit();
    }
    // --- Validación Crítica: Evitar choque de horarios ---
    $sql_check = "SELECT id FROM RegistroHoras 
                  WHERE id_contrato = :id_contrato 
                  AND fecha_trabajada = :fecha_trabajada 
                  AND (
                      (:hora_inicio < hora_fin AND :hora_fin > hora_inicio)
                  )";
    
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([
        ':id_contrato' => $id_contrato,
        ':fecha_trabajada' => $fecha_trabajada,
        ':hora_inicio' => $hora_inicio,
        ':hora_fin' => $hora_fin
    ]);

    if ($stmt_check->fetch()) {
        // Si encuentra un registro que choca, redirige con error
        header("Location: index.php?status=error&message=time_conflict");
        exit();
    }

    // Si no hay choque, proceder a insertar
    try {
        $sql_insert = "INSERT INTO RegistroHoras 
                        (id_contrato, id_proyecto, id_zona_trabajo, fecha_trabajada, hora_inicio, hora_fin, estado_registro) 
                       VALUES 
                        (:id_contrato, :id_proyecto, :id_zona, :fecha, :inicio, :fin, 'Pendiente')";
        
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            ':id_contrato' => $id_contrato,
            ':id_proyecto' => $id_proyecto,
            ':id_zona' => $id_zona_trabajo,
            ':fecha' => $fecha_trabajada,
            ':inicio' => $hora_inicio,
            ':fin' => $hora_fin
        ]);

        header("Location: index.php?status=success");
        exit();

    } catch (PDOException $e) {
        header("Location: index.php?status=error&message=" . urlencode($e->getMessage()));
        exit();
    }
}
header("Location: index.php");
exit();