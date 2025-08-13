<?php
// approvals/update_record.php - v2.1 (Redirección Inteligente)

require_once '../auth.php';
require_login();
require_permission('aprobaciones.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

// --- CAPTURAR FILTROS ---
$filters = $_POST['filters'] ?? '';

// Recoger los datos del formulario del modal
$registro_id = $_POST['registro_id'] ?? null;
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
    header('Location: index.php?' . $filters . '&status=error&message=' . urlencode('Faltan datos para actualizar.'));
    exit;
}
if ($hora_fin_num <= $hora_inicio_num) {
    header("Location: index.php?" . $filters . "&status=error&message=La hora fin debe ser posterior a la hora de inicio.");
    exit();
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

    header('Location: index.php?' . $filters . '&status=success&message=' . urlencode('Registro actualizado.'));
    exit;

} catch (PDOException $e) {
    error_log("Error en update_record.php: " . $e->getMessage());
    $message = urlencode("Error al actualizar el registro.");
    header('Location: index.php?' . $filters . '&status=error&message=' . $message);
    exit;
}
?>
