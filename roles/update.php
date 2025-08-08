<?php
// roles/update.php

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_rol = $_POST['id_rol'] ?? null;
    $permisos_seleccionados = $_POST['permisos'] ?? [];

    if (!$id_rol) {
        header('Location: index.php?status=error&message=' . urlencode('No se proporcionó un ID de rol.'));
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Borrar todos los permisos antiguos para este rol
        $stmt_delete = $pdo->prepare("DELETE FROM rol_permiso WHERE id_rol = ?");
        $stmt_delete->execute([$id_rol]);

        // 2. Insertar los nuevos permisos seleccionados
        if (!empty($permisos_seleccionados)) {
            $stmt_insert = $pdo->prepare("INSERT INTO rol_permiso (id_rol, id_permiso) VALUES (?, ?)");
            foreach ($permisos_seleccionados as $id_permiso) {
                $stmt_insert->execute([$id_rol, $id_permiso]);
            }
        }

        $pdo->commit();

        header('Location: index.php?status=success&message=' . urlencode('Permisos actualizados correctamente.'));
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = urlencode('Error al actualizar los permisos: ' . $e->getMessage());
        header('Location: edit.php?id=' . $id_rol . '&status=error&message=' . $message);
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
