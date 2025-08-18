<?php
// payroll/delete_payroll.php
// Script para eliminar de forma segura una nómina procesada y todos sus detalles.

require_once '../auth.php';
require_login();
// Solo usuarios con permiso para procesar nóminas pueden eliminar.
require_permission('nomina.procesar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['nomina_id'])) {
    header('Location: ' . BASE_URL . 'nomina_administrativa/index.php?status=error&message=Solicitud no válida.');
    exit();
}

$id_nomina = filter_input(INPUT_POST, 'nomina_id', FILTER_VALIDATE_INT);

if (!$id_nomina) {
    header('Location: ' . BASE_URL . 'nomina_administrativa/index.php?status=error&message=ID de nómina no válido.');
    exit();
}

try {
    $pdo->beginTransaction();

    // Primero, verificamos que la nómina a borrar no esté ya finalizada o pagada, como medida de seguridad.
    $stmt_check = $pdo->prepare("SELECT estado_nomina FROM nominasprocesadas WHERE id = ?");
    $stmt_check->execute([$id_nomina]);
    $estado_actual = $stmt_check->fetchColumn();

    if ($estado_actual === 'Aprobada y Finalizada' || $estado_actual === 'Pagada') {
        throw new Exception('No se puede eliminar una nómina que ya ha sido aprobada o pagada.');
    }

    // Si pasa la validación, procedemos a borrar.
    // 1. Borrar los detalles (registros hijos en nominadetalle).
    $stmt_delete_details = $pdo->prepare("DELETE FROM nominadetalle WHERE id_nomina_procesada = ?");
    $stmt_delete_details->execute([$id_nomina]);

    // 2. Borrar la cabecera (registro padre en nominasprocesadas).
    $stmt_delete_header = $pdo->prepare("DELETE FROM nominasprocesadas WHERE id = ?");
    $stmt_delete_header->execute([$id_nomina]);

    $pdo->commit();

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'La nómina ha sido eliminada correctamente.'];
    header('Location: ' . BASE_URL . 'nomina_administrativa/index.php');
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al eliminar la nómina ID $id_nomina: " . $e->getMessage());
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error al eliminar la nómina: ' . $e->getMessage()];
    header('Location: ' . BASE_URL . 'nomina_administrativa/index.php');
    exit();
}
?>
