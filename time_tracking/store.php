<?php
// time_tracking/store.php - v1.1
// Convierte las horas numéricas al formato de tiempo de la base de datos.

require_once '../auth.php';
require_login();
require_role('Inspector');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recoger datos
    $id_contrato_post = $_POST['id_contrato'] ?? null;
    $fecha_trabajada = $_POST['fecha_trabajada'] ?? null;
    $hora_inicio_num = $_POST['hora_inicio'] ?? null;
    $hora_fin_num = $_POST['hora_fin'] ?? null;
    $id_proyecto = $_POST['id_proyecto'] ?? null;
    $id_zona_trabajo = $_POST['id_zona_trabajo'] ?? null;
    $id_contrato_sesion = $_SESSION['contrato_inspector_id'] ?? null;

    if ($id_contrato_post != $id_contrato_sesion || !is_numeric($id_contrato_sesion)) {
        header("Location: index.php?status=error&message=Acceso%20no%20autorizado.");
        exit();
    }
    $id_contrato = $id_contrato_sesion;

    if (empty($fecha_trabajada) || !is_numeric($hora_inicio_num) || !is_numeric($hora_fin_num) || empty($id_proyecto) || empty($id_zona_trabajo)) {
        header("Location: index.php?status=error&message=Faltan%20campos%20requeridos.");
        exit();
    }

    if ($hora_fin_num <= $hora_inicio_num) {
        header("Location: index.php?status=error&message=La%20hora%20fin%20debe%20ser%20posterior%20a%20la%20hora%20de%20inicio.");
        exit();
    }

    // --- CONVERSIÓN DE HORA NUMÉRICA A FORMATO TIME ---
    $hora_inicio = $hora_inicio_num . ":00:00";
    $hora_fin = ($hora_fin_num == 24) ? "23:59:59" : $hora_fin_num . ":00:00"; // Manejar el caso especial de la hora 24
    // --- FIN DE LA CONVERSIÓN ---


    $stmt_periodo = $pdo->prepare("SELECT id FROM PeriodosDeReporte WHERE tipo_nomina = 'Inspectores' AND estado_periodo = 'Abierto' AND :fecha_trabajada BETWEEN fecha_inicio_periodo AND fecha_fin_periodo");
    $stmt_periodo->execute([':fecha_trabajada' => $fecha_trabajada]);
    if (!$stmt_periodo->fetch()) {
        header("Location: index.php?status=error&message=La%20fecha%20seleccionada%20no%20est%C3%A1%20dentro%20de%20un%20per%C3%ADodo%20de%20reporte%20abierto.");
        exit();
    }

    $sql_check = "SELECT id FROM RegistroHoras WHERE id_contrato = :id_contrato AND fecha_trabajada = :fecha_trabajada AND (:hora_inicio < hora_fin AND :hora_fin > hora_inicio)";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':id_contrato' => $id_contrato, ':fecha_trabajada' => $fecha_trabajada, ':hora_inicio' => $hora_inicio, ':hora_fin' => $hora_fin]);
    if ($stmt_check->fetch()) {
        header("Location: index.php?status=error&message=Conflicto%20de%20horario:%20Ya%20existe%20un%20registro%20que%20se%20solapa.");
        exit();
    }

    try {
        $sql_insert = "INSERT INTO RegistroHoras (id_contrato, id_proyecto, id_zona_trabajo, fecha_trabajada, hora_inicio, hora_fin, estado_registro) 
                       VALUES (:id_contrato, :id_proyecto, :id_zona, :fecha, :inicio, :fin, 'Pendiente')";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([':id_contrato' => $id_contrato, ':id_proyecto' => $id_proyecto, ':id_zona' => $id_zona_trabajo, ':fecha' => $fecha_trabajada, ':inicio' => $hora_inicio, ':fin' => $hora_fin]);
        header("Location: index.php?status=success&message=" . urlencode("Horas registradas correctamente."));
        exit();

    } catch (PDOException $e) {
        header("Location: index.php?status=error&message=" . urlencode("Error al guardar: " . $e->getMessage()));
        exit();
    }
}
header("Location: index.php");
exit();
