<?php
// lugares/delete.php - v2.0 (Lógica de Movimientos Eliminada)

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        redirect_with_error('index.php', 'No se proporcionó un ID válido.');
        exit;
    }

    try {
        // Verificar si el lugar (o sub-lugar) está siendo usado en alguna orden
        $stmt_check_orden = $pdo->prepare("SELECT COUNT(*) FROM ordenes WHERE id_lugar = ?");
        $stmt_check_orden->execute([$id]);
        if ($stmt_check_orden->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar porque está asignado a una o más órdenes.");
        }
        
        // Verificar si el lugar (o sub-lugar) está siendo usado en algún registro de horas
        $stmt_check_horas = $pdo->prepare("SELECT COUNT(*) FROM registrohoras WHERE id_zona_trabajo = ?");
        $stmt_check_horas->execute([$id]);
        if ($stmt_check_horas->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar porque está en uso en uno o más reportes de horas.");
        }

        // Si no está en uso, proceder a eliminar
        $stmt = $pdo->prepare("DELETE FROM lugares WHERE id = ?");
        $stmt->execute([$id]);

        redirect_with_success('index.php', 'Lugar/Sub-Lugar eliminado correctamente.');
        exit;

    } catch (Exception $e) {
        $message = urlencode('Error al eliminar: ' . $e->getMessage());
        header('Location: index.php?status=error&message=' . $message);
        exit;
    }

} else {
    header('Location: index.php');
    exit;
}
