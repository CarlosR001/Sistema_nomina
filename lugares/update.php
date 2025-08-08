<?php
// lugares/update.php - v2.0 (Jerárquico)

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $nombre = $_POST['nombre_zona_o_muelle'] ?? '';
    $monto = $_POST['monto_transporte_completo'] ?? 0;

    if (!$id || empty($nombre) || !is_numeric($monto)) {
        header('Location: edit.php?id=' . $id . '&status=error&message=' . urlencode('El nombre y el monto son obligatorios.'));
        exit;
    }
    
    // Evitar que un lugar sea su propio padre
    if ($id == $parent_id) {
        header('Location: edit.php?id=' . $id . '&status=error&message=' . urlencode('Un lugar no puede ser su propio padre.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE lugares SET parent_id = ?, nombre_zona_o_muelle = ?, monto_transporte_completo = ? WHERE id = ?");
        $stmt->execute([$parent_id, $nombre, $monto, $id]);

        header('Location: index.php?status=success&message=' . urlencode('Registro actualizado correctamente.'));
        exit;
    } catch (PDOException $e) {
        $message = urlencode('Error al actualizar el registro: ' . $e->getMessage());
        header('Location: edit.php?id=' . $id . '&status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
