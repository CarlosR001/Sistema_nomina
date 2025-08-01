<?php
// approvals/update_record.php
// Procesa la actualización de un único registro de horas desde el modal de edición.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

// Recoger y validar los datos del formulario
$registro_id = $_POST['registro_id'] ?? null;
$fecha_trabajada = $_POST['fecha_trabajada'] ?? null;
$hora_inicio = $_POST['hora_inicio'] ?? null;
$hora_fin = $_POST['hora_fin'] ?? null;
$id_proyecto = $_POST['id_proyecto'] ?? null;
$id_zona_trabajo = $_POST['id_zona_trabajo'] ?? null;

if (empty($registro_id) || empty($fecha_trabajada) || empty($hora_inicio) || empty($hora_fin) || empty($id_proyecto) || empty($id_zona_trabajo)) {
    header("Location: index.php?status=error&message=Faltan campos requeridos en el formulario de edición.");
    exit();
}

if (strtotime($hora_fin) <= strtotime($hora_inicio)) {
    header("Location: index.php?status=error&message=La hora fin debe ser posterior a la hora de inicio.");
    exit();
}

try {
    $sql = "UPDATE RegistroHoras SET
                fecha_trabajada = :fecha_trabajada,
                hora_inicio = :hora_inicio,
                hora_fin = :hora_fin,
                id_proyecto = :id_proyecto,
                id_zona_trabajo = :id_zona_trabajo
            WHERE id = :id AND estado_registro = 'Pendiente'";
            
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':fecha_trabajada' => $fecha_trabajada,
        ':hora_inicio' => $hora_inicio,
        ':hora_fin' => $hora_fin,
        ':id_proyecto' => $id_proyecto,
        ':id_zona_trabajo' => $id_zona_trabajo,
        ':id' => $registro_id
    ]);

    if ($stmt->rowCount() > 0) {
        header('Location: index.php?status=success&message=Registro #' . $registro_id . ' actualizado correctamente.');
    } else {
        throw new Exception("No se pudo actualizar el registro. Es posible que ya no esté en estado 'Pendiente' o que el ID no exista.");
    }
    exit();

} catch (Exception $e) {
    header('Location: index.php?status=error&message=' . urlencode('Error al actualizar el registro: ' . $e->getMessage()));
    exit();
}
?>
