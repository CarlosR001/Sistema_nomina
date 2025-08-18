<?php
// payroll/finalize.php
// Este script maneja la finalización de una nómina.

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['nomina_id'])) {
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=Solicitud%20inv%C3%A1lida.');
    exit();
}

$id_nomina = $_POST['nomina_id'];

try {
    $pdo->beginTransaction();

    // Actualizar el estado de la nómina a "Aprobada y Finalizada"
    // CORRECCIÓN: El nombre de la tabla es en minúsculas: nominasprocesadas
    $stmt_update_nomina = $pdo->prepare("UPDATE nominasprocesadas SET estado_nomina = 'Aprobada y Finalizada' WHERE id = ?");
    $stmt_update_nomina->execute([$id_nomina]);
    
    // Opcional: Podríamos añadir lógica adicional aquí, como marcar las novedades como 'Pagadas',
    // o cambiar el estado del período de reporte si fuera necesario.
    // Por ahora, solo actualizamos el estado de la nómina.

    $pdo->commit();
    
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina . '&status=finalized');
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al finalizar la nómina ID $id_nomina: " . $e->getMessage()); // Añadimos un log más detallado
    header('Location: ' . BASE_URL . 'payroll/show.php?id=' . $id_nomina . '&status=error&message=' . urlencode('Error al finalizar la nómina.'));
    exit();
}
?>
