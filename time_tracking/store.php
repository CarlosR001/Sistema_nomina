<?php
// time_tracking/store.php - v2.3 (Lógica de Sub-Lugar Dinámica)

require_once '../auth.php';
require_login();
require_permission('horas.registrar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

// Recoger datos del formulario
$id_periodo_reporte = $_POST['id_periodo_reporte'] ?? null;
$id_orden = $_POST['id_orden'] ?? null;
$fecha_trabajada = $_POST['fecha_trabajada'] ?? null;
$hora_inicio_num = $_POST['hora_inicio'] ?? null;
$hora_fin_num = $_POST['hora_fin'] ?? null;
$id_contrato = $_SESSION['contrato_inspector_id'] ?? null;
$hora_gracia_antes = isset($_POST['hora_gracia_antes']) ? 1 : 0;
$hora_gracia_despues = isset($_POST['hora_gracia_despues']) ? 1 : 0;
// Si el inspector eligió un sub-lugar, tendrá prioridad.
$id_zona_trabajo_especifica = $_POST['id_zona_trabajo'] ?? null;

$redirect_url = "index.php" . ($id_periodo_reporte ? "?periodo_id=" . urlencode($id_periodo_reporte) : "");
$separator = $id_periodo_reporte ? "&" : "?";

// Validaciones
if (empty($id_orden) || empty($fecha_trabajada) || !is_numeric($hora_inicio_num) || !is_numeric($hora_fin_num)) {
    header("Location: " . $redirect_url . $separator . "status=error&message=Faltan campos requeridos.");
    exit();
}
if ($hora_fin_num <= $hora_inicio_num) {
    header("Location: " . $redirect_url . $separator . "status=error&message=La hora fin debe ser posterior a la hora de inicio.");
    exit();
}

try {
    // --- LÓGICA MEJORADA: Determinar la zona de trabajo final ---
    $id_zona_trabajo_final = $id_zona_trabajo_especifica;
    
    // Si el inspector no especificó una zona (o no pudo), la heredamos de la orden principal.
    if (empty($id_zona_trabajo_final)) {
        $stmt_lugar = $pdo->prepare("SELECT id_lugar FROM ordenes WHERE id = ?");
        $stmt_lugar->execute([$id_orden]);
        $id_zona_trabajo_final = $stmt_lugar->fetchColumn();
    }

    if (!$id_zona_trabajo_final) {
        throw new Exception("No se pudo determinar el lugar de trabajo. Verifique la configuración de la orden.");
    }

    // Conversión de hora
    $hora_inicio = $hora_inicio_num . ":00:00";
    $hora_fin = ($hora_fin_num == 24) ? "23:59:59" : $hora_fin_num . ":00:00";

    // Validación de solapamiento
    $sql_check = "SELECT id FROM registrohoras WHERE id_contrato = ? AND fecha_trabajada = ? AND (? < hora_fin AND ? > hora_inicio)";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id_contrato, $fecha_trabajada, $hora_inicio, $hora_fin]);
    if ($stmt_check->fetch()) {
        header("Location: " . $redirect_url . $separator . "status=error&message=Conflicto de horario: Ya existe un registro que se solapa.");
        exit();
    }
    
    // Inserción en la base de datos
    $sql_insert = "INSERT INTO registrohoras 
                    (id_contrato, id_orden, id_zona_trabajo, id_periodo_reporte, fecha_trabajada, hora_inicio, hora_fin, 
                     estado_registro, hora_gracia_antes, hora_gracia_despues) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?, ?)";
    
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([
        $id_contrato, 
        $id_orden,
        $id_zona_trabajo_final, // <-- Se guarda la zona de trabajo definitiva
        $id_periodo_reporte,
        $fecha_trabajada, 
        $hora_inicio, 
        $hora_fin,
        $hora_gracia_antes,
        $hora_gracia_despues
    ]);

    header("Location: " . $redirect_url . $separator . "status=success&message=Horas registradas correctamente.");
    exit();

} catch (Exception $e) {
    error_log("Error en store.php: " . $e->getMessage());
    $error_message = urlencode("Error al guardar el registro: " . $e->getMessage());
    header("Location: " . $redirect_url . $separator . "status=error&message=" . $error_message);
    exit();
}
