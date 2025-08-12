<?php
// approvals/update_status.php - v2.0 (Lógica de Transporte Simplificada)

require_once '../auth.php';
require_login();
require_permission('aprobaciones.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['registros'], $_POST['action'])) {
    header('Location: index.php?status=error&message=Solicitud no válida.');
    exit();
}

$registros = $_POST['registros'];
$new_status = $_POST['action'];
$user_id = $_SESSION['user_id'];
$current_time = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    $sql_update = "UPDATE registrohoras SET 
                       estado_registro = :estado, 
                       id_usuario_aprobador = :aprobador, 
                       fecha_aprobacion = :fecha,
                       transporte_aprobado = :transporte,
                       transporte_mitad = :mitad -- <-- Nueva columna
                   WHERE id = :id";
    $stmt = $pdo->prepare($sql_update);

    foreach ($registros as $id => $data) {
        if (!empty($data['id'])) {
            // Determinar los valores para los checkboxes
            $transporte_aprobado = isset($data['transporte']) ? 1 : 0;
            $transporte_mitad = isset($data['transporte_mitad']) ? 1 : 0;
            
            // Si el transporte no está aprobado, forzar el 50% a 0 para consistencia.
            if ($transporte_aprobado == 0) {
                $transporte_mitad = 0;
            }

            $params = [
                ':estado' => $new_status,
                ':aprobador' => $user_id,
                ':fecha' => $current_time,
                ':transporte' => $transporte_aprobado,
                ':mitad' => $transporte_mitad,
                ':id' => $id
            ];

            // Si se revierte a 'Pendiente', no actualizamos los datos de aprobación
            if ($new_status === 'Pendiente') {
                $sql_revert = "UPDATE registrohoras SET estado_registro = 'Pendiente', id_usuario_aprobador = NULL, fecha_aprobacion = NULL WHERE id = ?";
                $pdo->prepare($sql_revert)->execute([$id]);
            } else {
                $stmt->execute($params);
            }
        }
    }

    $pdo->commit();
    header('Location: index.php?status=success&message=Registros actualizados correctamente.');
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: index.php?status=error&message=' . urlencode('Error al actualizar: ' . $e->getMessage()));
    exit();
}
