<?php
// time_tracking/store.php - v2.0
// Mantiene el período seleccionado después de registrar las horas.

require_once '../auth.php';
require_login();
require_role('Inspector');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
         // Recoger y validar datos
         $id_contrato_post = $_POST['id_contrato'] ?? null;
         $id_periodo_reporte = $_POST['id_periodo_reporte'] ?? null;
         $fecha_trabajada = $_POST['fecha_trabajada'] ?? null;
         $hora_inicio_num = $_POST['hora_inicio'] ?? null;
         $hora_fin_num = $_POST['hora_fin'] ?? null;
         $id_proyecto = $_POST['id_proyecto'] ?? null;
         $id_zona_trabajo = $_POST['id_zona_trabajo'] ?? null;
         $id_contrato_sesion = $_SESSION['contrato_inspector_id'] ?? null;
         
         // --- INICIO: Recoger los nuevos datos de Horas de Gracia ---
         $hora_gracia_antes = isset($_POST['hora_gracia_antes']) ? 1 : 0;
         $hora_gracia_despues = isset($_POST['hora_gracia_despues']) ? 1 : 0;
         // --- FIN: Recoger los nuevos datos ---
 
         // Construir la URL de redirección base
         $redirect_url = "index.php" . ($id_periodo_reporte ? "?periodo_id=" . urlencode($id_periodo_reporte) : "");
         $separator = $id_periodo_reporte ? "&" : "?";
 
         // --- Validaciones ---
         if ($id_contrato_post != $id_contrato_sesion || !is_numeric($id_contrato_sesion)) {
             header("Location: " . $redirect_url . $separator . "status=error&message=Acceso%20no%20autorizado.");
             exit();
         }
         $id_contrato = $id_contrato_sesion;
 
         if (empty($fecha_trabajada) || !is_numeric($hora_inicio_num) || !is_numeric($hora_fin_num) || empty($id_proyecto) || empty($id_zona_trabajo) || empty($id_periodo_reporte)) {
             header("Location: " . $redirect_url . $separator . "status=error&message=Faltan%20campos%20requeridos.");
             exit();
         }
 
         if ($hora_fin_num <= $hora_inicio_num) {
             header("Location: " . $redirect_url . $separator . "status=error&message=La%20hora%20fin%20debe%20ser%20posterior%20a%20la%20hora%20de%20inicio.");
             exit();
         }
 
    
    // --- Conversión de hora ---
    $hora_inicio_h = floor($hora_inicio_num);
    $hora_inicio_m = ($hora_inicio_num - $hora_inicio_h) * 60;
    $hora_inicio = sprintf('%02d:%02d:00', $hora_inicio_h, $hora_inicio_m);

    $hora_fin_h = floor($hora_fin_num);
    $hora_fin_m = ($hora_fin_num - $hora_fin_h) * 60;
    if ($hora_fin_num == 24) {
        $hora_fin = "23:59:59";
    } else {
        $hora_fin = sprintf('%02d:%02d:00', $hora_fin_h, $hora_fin_m);
    }

    // --- Validación de período ---
    $stmt_periodo = $pdo->prepare("SELECT id FROM PeriodosDeReporte WHERE id = :id_periodo AND tipo_nomina = 'Inspectores' AND estado_periodo = 'Abierto' AND :fecha_trabajada BETWEEN fecha_inicio_periodo AND fecha_fin_periodo");
    $stmt_periodo->execute([':id_periodo' => $id_periodo_reporte, ':fecha_trabajada' => $fecha_trabajada]);
    if (!$stmt_periodo->fetch()) {
        header("Location: " . $redirect_url . $separator . "status=error&message=La%20fecha%20o%20per%C3%ADodo%20seleccionado%20no%20es%20v%C3%A1lido.");
        exit();
    }

    // --- Validación de solapamiento de horario ---
    $sql_check = "SELECT id FROM RegistroHoras WHERE id_contrato = :id_contrato AND fecha_trabajada = :fecha_trabajada AND (:hora_inicio < hora_fin AND :hora_fin > hora_inicio)";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':id_contrato' => $id_contrato, ':fecha_trabajada' => $fecha_trabajada, ':hora_inicio' => $hora_inicio, ':hora_fin' => $hora_fin]);
    if ($stmt_check->fetch()) {
        header("Location: " . $redirect_url . $separator . "status=error&message=Conflicto%20de%20horario:%20Ya%20existe%20un%20registro%20que%20se%20solapa.");
        exit();
    }
    
       // --- Inserción en la base de datos ---
       try {
        // Consulta SQL actualizada para incluir las horas de gracia
        $sql_insert = "INSERT INTO RegistroHoras 
                           (id_contrato, id_proyecto, id_zona_trabajo, fecha_trabajada, hora_inicio, hora_fin, estado_registro, id_periodo_reporte, hora_gracia_antes, hora_gracia_despues) 
                       VALUES (:id_contrato, :id_proyecto, :id_zona, :fecha, :inicio, :fin, 'Pendiente', :id_periodo, :gracia_antes, :gracia_despues)";
        
        $stmt_insert = $pdo->prepare($sql_insert);
        
        // Ejecución con los nuevos parámetros de horas de gracia
        $stmt_insert->execute([
            ':id_contrato' => $id_contrato, 
            ':id_proyecto' => $id_proyecto, 
            ':id_zona' => $id_zona_trabajo, 
            ':fecha' => $fecha_trabajada, 
            ':inicio' => $hora_inicio, 
            ':fin' => $hora_fin,
            ':id_periodo' => $id_periodo_reporte,
            ':gracia_antes' => $hora_gracia_antes, // Nuevo
            ':gracia_despues' => $hora_gracia_despues // Nuevo
        ]);

        header("Location: " . $redirect_url . $separator . "status=success&message=" . urlencode("Horas registradas correctamente."));
        exit();

    } catch (PDOException $e) {
        // En caso de error, también mantenemos el contexto del período
        header("Location: " . $redirect_url . $separator . "status=error&message=" . urlencode("Error al guardar: " . $e->getMessage()));
        exit();
    }

}

// Redirección por defecto si el script es accedido incorrectamente
header("Location: index.php");
exit();
