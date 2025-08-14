<?php
// approvals/update_record.php - v2.2 (Redirección Robusta)

require_once '../auth.php';
require_login();
require_permission('aprobaciones.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

// --- LÓGICA DE REDIRECCIÓN ROBUSTA ---
$filters_string = $_POST['filters'] ?? '';
parse_str($filters_string, $redirect_params); // Convierte 'view=pendientes&id=1' a un array

function redirect_with_message($params, $status, $message) {
    $params['status'] = $status;
    $params['message'] = urlencode($message);
    header('Location: index.php?' . http_build_query($params));
    exit();
}
// --- FIN LÓGICA DE REDIRECCIÓN ---

$registro_id = $_POST['registro_id'] ?? null;
// ... (resto de la captura de variables)
$fecha_trabajada = $_POST['fecha_trabajada'] ?? null;
$id_orden = $_POST['id_orden'] ?? null;
$id_zona_trabajo = $_POST['id_zona_trabajo'] ?? null;
$hora_inicio_num = $_POST['hora_inicio'] ?? null;
$hora_fin_num = $_POST['hora_fin'] ?? null;
$transporte_aprobado = isset($_POST['transporte_aprobado']) ? 1 : 0;
$transporte_mitad = isset($_POST['transporte_mitad']) ? 1 : 0;
$hora_gracia_antes = isset($_POST['hora_gracia_antes']) ? 1 : 0;
$hora_gracia_despues = isset($_POST['hora_gracia_despues']) ? 1 : 0;


// Validaciones
if (!$registro_id || !$fecha_trabajada || !$id_orden || !$id_zona_trabajo || $hora_inicio_num === null || $hora_fin_num === null) {
    redirect_with_message($redirect_params, 'error', 'Faltan datos para actualizar.');
}
if ($hora_fin_num <= $hora_inicio_num) {
    redirect_with_message($redirect_params, 'error', 'La hora fin debe ser posterior a la hora de inicio.');
}

// Conversión de hora
$hora_inicio = $hora_inicio_num . ":00:00";
$hora_fin = ($hora_fin_num == 24) ? "23:59:59" : $hora_fin_num . ":00:00";

try {
    if ($transporte_aprobado == 0) { $transporte_mitad = 0; }
    
    $sql = "UPDATE registrohoras SET 
                fecha_trabajada = ?, id_orden = ?, id_zona_trabajo = ?, 
                hora_inicio = ?, hora_fin = ?,
                transporte_aprobado = ?, transporte_mitad = ?,
                hora_gracia_antes = ?, hora_gracia_despues = ?
            WHERE id = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $fecha_trabajada, $id_orden, $id_zona_trabajo,
        $hora_inicio, $hora_fin,
        $transporte_aprobado, $transporte_mitad,
        $hora_gracia_antes, $hora_gracia_despues,
        $registro_id
    ]);

    redirect_with_message($redirect_params, 'success', 'Registro actualizado correctamente.');

} catch (PDOException $e) {
    error_log("Error en update_record.php: " . $e->getMessage());
    redirect_with_message($redirect_params, 'error', "Error al actualizar el registro.");
}
?>
