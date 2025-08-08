<?php
// payroll/delete_payroll.php
// Elimina una nómina procesada y todos sus detalles asociados.

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['nomina_id'])) {
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=Solicitud inválida.');
    exit();
}

$id_nomina = $_POST['nomina_id'];

try {
    $pdo->beginTransaction();

    // 1. Obtener los IDs de las novedades que fueron aplicadas en esta nómina
    // (Esta es una aproximación, ya que no guardamos el id_novedad en NominaDetalle.
    // Una mejor forma es revertir TODAS las novedades del período de la nómina)
    $stmt_nomina_info = $pdo->prepare("SELECT periodo_inicio, periodo_fin FROM NominasProcesadas WHERE id = ?");
    $stmt_nomina_info->execute([$id_nomina]);
    $nomina_info = $stmt_nomina_info->fetch();

    if ($nomina_info) {
        // 2. Revertir el estado de TODAS las novedades dentro del período de esa nómina a 'Pendiente'
        $stmt_revert_novedades = $pdo->prepare(
            "UPDATE NovedadesPeriodo SET estado_novedad = 'Pendiente' WHERE periodo_aplicacion BETWEEN ? AND ? AND id_contrato IN (SELECT DISTINCT id_contrato FROM NominaDetalle WHERE id_nomina_procesada = ?)"
        );
        $stmt_revert_novedades->execute([$nomina_info['periodo_inicio'], $nomina_info['periodo_fin'], $id_nomina]);
    }

    // 3. Eliminar los registros de detalle de la nómina
    $stmt_delete_detalles = $pdo->prepare("DELETE FROM NominaDetalle WHERE id_nomina_procesada = ?");
    $stmt_delete_detalles->execute([$id_nomina]);

    // 4. Eliminar el registro principal de la nómina
    $stmt_delete_nomina = $pdo->prepare("DELETE FROM NominasProcesadas WHERE id = ?");
    $stmt_delete_nomina->execute([$id_nomina]);
    
    // (Opcional) Si la nómina era Semanal, podríamos querer reabrir el período de reporte.
    // Por ahora, lo dejamos como una acción manual para evitar complejidad.

    $pdo->commit();
    header('Location: ' . BASE_URL . 'payroll/review.php?status=success&message=' . urlencode('Nómina eliminada correctamente. Las novedades asociadas han sido revertidas a Pendiente.'));
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=' . urlencode('Error al eliminar la nómina: ' . $e->getMessage()));
    exit();
}
?>
