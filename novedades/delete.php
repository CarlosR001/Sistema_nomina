<?php
// novedades/delete.php
// Procesa la eliminación de una novedad.

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

$id = $_POST['id'] ?? null;

if (empty($id)) {
    header('Location: index.php?status=error&message=ID de novedad no proporcionado.');
    exit();
}

try {
    // Asegurarse de que la novedad que se intenta eliminar todavía está 'Pendiente'
    $stmt_check = $pdo->prepare("SELECT estado_novedad FROM novedadesperiodo WHERE id = ?");
    $stmt_check->execute([$id]);
    $estado_actual = $stmt_check->fetchColumn();

    if ($estado_actual === false) {
         throw new Exception("La novedad que intentas eliminar no existe.");
    }

    if ($estado_actual !== 'Pendiente') {
        throw new Exception("No se puede eliminar una novedad que ya ha sido aplicada en una nómina.");
    }

    // Eliminar el registro
    $stmt_delete = $pdo->prepare("DELETE FROM novedadesperiodo WHERE id = ?");
    $stmt_delete->execute([$id]);

    header('Location: index.php?status=success&message=Novedad eliminada correctamente.');
    exit();

} catch (Exception $e) {
    header('Location: index.php?status=error&message=' . urlencode($e->getMessage()));
    exit();
}
?>
