<?php
// approvals/update_record.php - v1.1
// Convierte las horas numéricas del modal al formato de tiempo de la base de datos.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

// Recoger los datos del formulario del modal
$registro_id = $_POST['registro_id'] ?? null;
$fecha_trabajada = $_POST['fecha_trabajada'] ?? null;
$id_orden = $_POST['id_orden'] ?? null; // <-- Clave: Se recibe id_orden
$hora_inicio_num = $_POST['hora_inicio'] ?? null;
$hora_fin_num = $_POST['hora_fin'] ?? null;

// Para los checkboxes, si no están marcados no se envían.
$transporte_aprobado = isset($_POST['transporte_aprobado']) ? 1 : 0;
$hora_gracia_antes = isset($_POST['hora_gracia_antes']) ? 1 : 0;
$hora_gracia_despues = isset($_POST['hora_gracia_despues']) ? 1 : 0;

// Validaciones
if (!$registro_id || !$fecha_trabajada || !$id_orden || $hora_inicio_num === null || $hora_fin_num === null) {
    header('Location: index.php?status=error&message=' . urlencode('Faltan datos para actualizar el registro.'));
    exit;
}


if ($hora_fin_num <= $hora_inicio_num) {
    header("Location: index.php?status=error&message=La hora fin debe ser posterior a la hora de inicio.");
    exit();
}

// --- CONVERSIÓN DE HORA NUMÉRICA A FORMATO TIME ---
$hora_inicio = $hora_inicio_num . ":00:00";
$hora_fin = ($hora_fin_num == 24) ? "23:59:59" : $hora_fin_num . ":00:00";
// --- FIN DE LA CONVERSIÓN ---

try {
    // La consulta UPDATE ahora modifica id_orden y omite los campos de proyecto/zona
    $sql = "UPDATE RegistroHoras SET 
                fecha_trabajada = ?,
                id_orden = ?,
                hora_inicio = ?,
                hora_fin = ?,
                transporte_aprobado = ?,
                hora_gracia_antes = ?,
                hora_gracia_despues = ?
            WHERE id = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $fecha_trabajada,
        $id_orden,
        $hora_inicio,
        $hora_fin,
        $transporte_aprobado,
        $hora_gracia_antes,
        $hora_gracia_despues,
        $registro_id
    ]);

    header('Location: index.php?status=success&message=' . urlencode('Registro actualizado correctamente.'));
    exit;

} catch (PDOException $e) {
    error_log("Error en update_record.php: " . $e->getMessage());
    $message = urlencode("Error al actualizar el registro.");
    header('Location: index.php?status=error&message=' . $message);
    exit;
}


?>
