<?php
// time_tracking/store.php - v2.0
// Mantiene el período seleccionado después de registrar las horas.

require_once '../auth.php';
require_login();
require_role('Inspector');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
            // Recoger datos del formulario
    $id_periodo_reporte = $_POST['id_periodo_reporte'] ?? null;
    $fecha_trabajada = $_POST['fecha_trabajada'] ?? null;
    $hora_inicio_num = $_POST['hora_inicio'] ?? null;
    $hora_fin_num = $_POST['hora_fin'] ?? null;
    $id_proyecto = $_POST['id_proyecto'] ?? null;
    $id_zona_trabajo = $_POST['id_zona_trabajo'] ?? null;

    // Usar directamente el ID de contrato de la sesión. Es la única fuente segura.
    $id_contrato = $_SESSION['contrato_inspector_id'] ?? null;
    
    // Capturar la solicitud de horas de gracia (sin duplicados)
    $hora_gracia_antes = isset($_POST['hora_gracia_antes']) ? 1 : 0;
    $hora_gracia_despues = isset($_POST['hora_gracia_despues']) ? 1 : 0;

    // Construir la URL de redirección base
    $redirect_url = "index.php" . ($id_periodo_reporte ? "?periodo_id=" . urlencode($id_periodo_reporte) : "");
    $separator = $id_periodo_reporte ? "&" : "?";

    // --- Validaciones ---
    // Esta es la validación correcta y segura.
    if (!is_numeric($id_contrato)) {
        $error_message = urlencode("Acceso no autorizado o sesión inválida.");
        header("Location: " . $redirect_url . $separator . "status=error&message=" . $error_message);
        exit();
    }

 
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
    // 1. Definir la consulta SQL completa
    $sql_insert = "INSERT INTO RegistroHoras 
                    (id_contrato, id_proyecto, id_zona_trabajo, id_periodo_reporte, fecha_trabajada, hora_inicio, hora_fin, 
                     estado_registro, transporte_aprobado, hora_gracia_antes, hora_gracia_despues) 
                   VALUES 
                    (:id_contrato, :id_proyecto, :id_zona, :id_periodo, :fecha, :inicio, :fin, 
                     'Pendiente', 1, :gracia_antes, :gracia_despues)";

    // 2. Preparar la consulta
    $stmt_insert = $pdo->prepare($sql_insert);
    
    // 3. Ejecutar la consulta con todos los parámetros
    $stmt_insert->execute([
        ':id_contrato' => $id_contrato, 
        ':id_proyecto' => $id_proyecto, 
        ':id_zona' => $id_zona_trabajo, 
        ':id_periodo' => $id_periodo_reporte,
        ':fecha' => $fecha_trabajada, 
        ':inicio' => $hora_inicio, 
        ':fin' => $hora_fin,
        ':gracia_antes' => $hora_gracia_antes,
        ':gracia_despues' => $hora_gracia_despues
    ]);

    // 4. Redirigir con mensaje de éxito (ESTA LÍNEA ESTÁ CORREGIDA)
    $success_message = urlencode("Horas registradas correctamente.");
    header("Location: " . $redirect_url . $separator . "status=success&message=" . $success_message);
    exit();

} catch (PDOException $e) {
    // Manejo de errores (no necesita cambios)
    error_log("Error en store.php: " . $e->getMessage());
    $error_message = urlencode("Error al guardar el registro. Por favor, contacte a un administrador.");
    header("Location: " . $redirect_url . $separator . "status=error&message=" . $error_message);
    exit();
}


}

// Redirección por defecto si el script es accedido incorrectamente
header("Location: index.php");
exit();
