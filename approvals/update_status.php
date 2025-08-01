<?php
// approvals/update_status.php - v1.1
// Añade la capacidad de revertir un registro a 'Pendiente'.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['registros'], $_POST['action'])) {
    header('Location: index.php?status=error&message=Solicitud no válida.');
    exit();
}

$registros_ids = $_POST['registros'];
$new_status = $_POST['action']; // 'Aprobado', 'Rechazado', o 'Pendiente'

// Validar que el nuevo estado sea uno de los permitidos
if (!in_array($new_status, ['Aprobado', 'Rechazado', 'Pendiente'])) {
    header('Location: index.php?status=error&message=Acción no válida.');
    exit();
}

if (empty($registros_ids) || !is_array($registros_ids)) {
     header('Location: index.php?status=error&message=No se seleccionó ningún registro.');
    exit();
}

try {
    // Construir la parte de los placeholders (?) para la consulta IN ()
    $placeholders = implode(',', array_fill(0, count($registros_ids), '?'));
    
    // Si revertimos a pendiente, limpiamos los datos de aprobación.
    $id_aprobador = ($new_status === 'Pendiente') ? null : $_SESSION['user_id'];
    $fecha_aprobacion = ($new_status === 'Pendiente') ? null : date('Y-m-d H:i:s');

    $sql = "UPDATE RegistroHoras 
            SET estado_registro = ?, id_usuario_aprobador = ?, fecha_aprobacion = ?
            WHERE id IN ($placeholders)";
            
    $stmt = $pdo->prepare($sql);
    
    // Crear el array de parámetros para execute()
    $params = array_merge([$new_status, $id_aprobador, $fecha_aprobacion], $registros_ids);
    
    $stmt->execute($params);
    $affected_rows = $stmt->rowCount();
    
    $accion_texto = ($new_status === 'Pendiente') ? 'revertido' : strtolower($new_status) . 's';
    $redirect_view = ($new_status === 'Aprobado' || $new_status === 'Rechazado') ? '?view=pendientes' : '?view=aprobados';


    header('Location: index.php' . $redirect_view . '&status=success&message=' . $affected_rows . ' registro(s) han sido ' . $accion_texto . '.');
    exit();

} catch (PDOException $e) {
    header('Location: index.php?status=error&message=' . urlencode('Error de base de datos: ' . $e->getMessage()));
    exit();
}
?>
