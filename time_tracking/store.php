<?php
// time_tracking/store.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Inspector'); // Solo Inspectores pueden registrar horas.

// La conexión $pdo ya está disponible a través de auth.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recoger datos del formulario
    $id_contrato_post = $_POST['id_contrato'] ?? null;
    $fecha_trabajada = $_POST['fecha_trabajada'] ?? null;
    $hora_inicio = $_POST['hora_inicio'] ?? null;
    $hora_fin = $_POST['hora_fin'] ?? null;
    $id_proyecto = $_POST['id_proyecto'] ?? null;
    $id_zona_trabajo = $_POST['id_zona_trabajo'] ?? null;

    // Obtener el ID de contrato del inspector de la sesión
    $id_contrato_sesion = $_SESSION['contrato_inspector_id'] ?? null;

    // Validar que el id_contrato del POST coincida con el de la sesión
    if ($id_contrato_post != $id_contrato_sesion || !is_numeric($id_contrato_sesion)) {
        header("Location: index.php?status=error&message=Acceso%20no%20autorizado%20para%20este%20contrato.");
        exit();
    }
    $id_contrato = $id_contrato_sesion; // Usar el ID de la sesión para mayor seguridad

    // Validaciones básicas de campos
    if (empty($fecha_trabajada) || empty($hora_inicio) || empty($hora_fin) || empty($id_proyecto) || empty($id_zona_trabajo)) {
        header("Location: index.php?status=error&message=Faltan%20campos%20requeridos.");
        exit();
    }

    // Validaciones de tiempo
    if (strtotime($hora_fin) <= strtotime($hora_inicio)) {
        header("Location: index.php?status=error&message=La%20hora%20fin%20debe%20ser%20posterior%20a%20la%20hora%20de%20inicio.");
        exit();
    }

    // Validar que la fecha esté en un período abierto para Inspectores
    $stmt_periodo = $pdo->prepare("SELECT id FROM PeriodosDeReporte WHERE tipo_nomina = 'Inspectores' AND estado_periodo = 'Abierto' AND :fecha_trabajada BETWEEN fecha_inicio_periodo AND fecha_fin_periodo");
    $stmt_periodo->execute([':fecha_trabajada' => $fecha_trabajada]);
    if (!$stmt_periodo->fetch()) {
        header("Location: index.php?status=error&message=La%20fecha%20seleccionada%20no%20est%C3%A1%20dentro%20de%20un%20per%C3%ADodo%20de%20reporte%20abierto.");
        exit();
    }

    // Validación Crítica: Evitar choque de horarios para el mismo contrato y día
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
        header("Location: index.php?status=error&message=Conflicto%20de%20horario:%20Ya%20existe%20un%20registro%20para%20ese%20d%C3%ADa%20y%20hora.");
        exit();
    }

    // Si todas las validaciones pasan, proceder a insertar
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
        header("Location: index.php?status=error&message=" . urlencode("Error al guardar el registro de horas: " . $e->getMessage()));
        exit();
    }
}
// Si no es una solicitud POST, redirigir al índice de time_tracking
header("Location: index.php");
exit();