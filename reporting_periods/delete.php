<?php
// reporting_periods/delete.php
// Elimina un período de reporte solo si no tiene registros de horas asociados.

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo_id'])) {
    header('Location: index.php?status=error&message=Solicitud inválida.');
    exit();
}

$periodo_id = $_POST['periodo_id'];

try {
    $pdo->beginTransaction();

    // 1. VERIFICACIÓN DE SEGURIDAD:
    // Comprobar si existen registros de horas asociados a este período.
    $stmt_check = $pdo->prepare("SELECT COUNT(id) FROM RegistroHoras WHERE id_periodo_reporte = ?");
    $stmt_check->execute([$periodo_id]);
    $record_count = $stmt_check->fetchColumn();

    if ($record_count > 0) {
        // Si hay registros, no se puede eliminar.
        throw new Exception("No se puede eliminar el período porque ya contiene " . $record_count . " registro(s) de horas. Elimine los registros primero.");
    }

    // 2. Si la verificación pasa, proceder con la eliminación.
    $stmt_delete = $pdo->prepare("DELETE FROM PeriodosDeReporte WHERE id = ?");
    $stmt_delete->execute([$periodo_id]);

    $pdo->commit();
    header('Location: index.php?status=success&message=' . urlencode('Período de reporte eliminado correctamente.'));
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: index.php?status=error&message=' . urlencode($e->getMessage()));
    exit();
}
?>
