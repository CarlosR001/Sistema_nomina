<?php
// conceptos/delete.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    if (empty($id)) {
        header('Location: index.php?status=error&message=ID no proporcionado.');
        exit();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM ConceptosNomina WHERE id = ?");
        $stmt->execute([$id]);
        
        header('Location: index.php?status=success&message=Concepto eliminado correctamente.');
        exit();
    } catch (PDOException $e) {
        // Manejar error de restricción de clave foránea
        if ($e->getCode() == '23000') {
            $message = 'No se puede eliminar el concepto porque ya está en uso en alguna nómina.';
        } else {
            $message = 'Error al eliminar el concepto: ' . $e->getMessage();
        }
        header('Location: index.php?status=error&message=' . urlencode($message));
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
