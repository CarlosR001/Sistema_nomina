<?php
// reporting_periods/update_status.php
// Procesa el cambio de estado de un período de reporte.

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo_id'], $_POST['new_status'])) {
    header('Location: index.php?status=error&message=Solicitud no válida.');
    exit();
}

$periodo_id = $_POST['periodo_id'];
$new_status = $_POST['new_status'];

if (!in_array($new_status, ['Abierto', 'Cerrado'])) {
    header('Location: index.php?status=error&message=Estado no válido.');
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE PeriodosDeReporte SET estado_periodo = ? WHERE id = ?");
    $stmt->execute([$new_status, $periodo_id]);

    header('Location: index.php?status=success&message=El estado del período ha sido actualizado.');
    exit();

} catch (PDOException $e) {
    header('Location: index.php?status=error&message=' . urlencode('Error de base de datos.'));
    exit();
}
?>
