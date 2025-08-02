<?php
// approvals/update_status.php - v1.2
// Procesa la aprobación de transporte junto con el estado del registro.

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['registros'], $_POST['action'])) {
    header('Location: index.php?status=error&message=Solicitud no válida.');
    exit();
}

$registros_data = $_POST['registros'];
$new_status = $_POST['action'];

if (!in_array($new_status, ['Aprobado', 'Rechazado', 'Pendiente'])) {
    header('Location: index.php?status=error&message=Acción no válida.');
    exit();
}

if (empty($registros_data) || !is_array($registros_data)) {
     header('Location: index.php?status=error&message=No se seleccionó ningún registro.');
    exit();
}

try {
    $pdo->beginTransaction();

    $sql = "UPDATE RegistroHoras 
            SET estado_registro = ?, transporte_aprobado = ?, id_usuario_aprobador = ?, fecha_aprobacion = ?
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);

    $affected_rows = 0;
    
    // Iterar sobre cada registro seleccionado
    foreach ($registros_data as $registro_id => $data) {
        if (!isset($data['id'])) continue; // Si solo viene el transporte pero no el ID, ignorar.

        $transporte_aprobado = isset($data['transporte']) ? 1 : 0;
        
        // Si se revierte, limpiar datos de aprobación. Si se aprueba/rechaza, llenarlos.
        $id_aprobador = ($new_status === 'Pendiente') ? null : $_SESSION['user_id'];
        $fecha_aprobacion = ($new_status === 'Pendiente') ? null : date('Y-m-d H:i:s');
        
        $stmt->execute([
            $new_status,
            $transporte_aprobado,
            $id_aprobador,
            $fecha_aprobacion,
            $registro_id
        ]);
        $affected_rows += $stmt->rowCount();
    }
    
    $pdo->commit();

    $accion_texto = ($new_status === 'Pendiente') ? 'revertido' : strtolower($new_status) . 's';
    $redirect_view = ($new_status === 'Aprobado' || $new_status === 'Rechazado') ? '?view=pendientes' : '?view=aprobados';

    header('Location: index.php' . $redirect_view . '&status=success&message=' . $affected_rows . ' registro(s) han sido ' . $accion_texto . '.');
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: index.php?status=error&message=' . urlencode('Error de base de datos: ' . $e->getMessage()));
    exit();
}
?>
