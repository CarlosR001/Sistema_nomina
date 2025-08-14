<?php
// approvals/update_status.php - v2.3 (Corrección de Clave Foránea Definitiva)

require_once '../auth.php';
require_login();
require_permission('aprobaciones.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['registros'], $_POST['action'])) {
    header('Location: index.php?status=error&message=Solicitud no válida.');
    exit();
}

// --- LÓGICA DE REDIRECCIÓN ROBUSTA ---
$filters_string = $_POST['filters'] ?? '';
parse_str($filters_string, $redirect_params);

function redirect_with_message($params, $status, $message) {
    $params['status'] = $status;
    $params['message'] = urlencode($message);
    header('Location: index.php?' . http_build_query($params));
    exit();
}
// --- FIN LÓGICA DE REDIRECCIÓN ---

$registros = $_POST['registros'];
$new_status = $_POST['action'];

// --- CORRECCIÓN CRÍTICA ---
// Se debe usar el ID del EMPLEADO que aprueba (vinculado al usuario), no el ID del USUARIO.
// Esta variable se crea durante el login en auth.php.
$approver_employee_id = $_SESSION['user_id_empleado']; 
$current_time = date('Y-m-d H:i:s');

// Validación para asegurar que el usuario que aprueba está correctamente vinculado.
if (empty($approver_employee_id)) {
    redirect_with_message($redirect_params, 'error', 'Error de Aprobación: Su cuenta de usuario no está vinculada a un registro de empleado. Contacte al administrador.');
}

try {
    $pdo->beginTransaction();

    $sql_update = "UPDATE registrohoras SET 
                       estado_registro = :estado, 
                       id_usuario_aprobador = :aprobador, 
                       fecha_aprobacion = :fecha,
                       transporte_aprobado = :transporte,
                       transporte_mitad = :mitad
                   WHERE id = :id";
    $stmt = $pdo->prepare($sql_update);

    foreach ($registros as $id => $data) {
        if (!empty($data['id'])) {
            $transporte_aprobado = isset($data['transporte']) ? 1 : 0;
            $transporte_mitad = isset($data['transporte_mitad']) ? 1 : 0;
            
            if ($transporte_aprobado == 0) {
                $transporte_mitad = 0;
            }

            if ($new_status === 'Pendiente') {
                $sql_revert = "UPDATE registrohoras SET estado_registro = 'Pendiente', id_usuario_aprobador = NULL, fecha_aprobacion = NULL WHERE id = ?";
                $pdo->prepare($sql_revert)->execute([$id]);
            } else {
                $params = [
                    ':estado' => $new_status,
                    ':aprobador' => $approver_employee_id, // <-- Se usa la variable correcta
                    ':fecha' => $current_time,
                    ':transporte' => $transporte_aprobado,
                    ':mitad' => $transporte_mitad,
                    ':id' => $id
                ];
                $stmt->execute($params);
            }
        }
    }

    $pdo->commit();
    redirect_with_message($redirect_params, 'success', 'Registros actualizados correctamente.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_with_message($redirect_params, 'error', 'Error al actualizar: ' . $e->getMessage());
}
