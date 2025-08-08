<?php
// users/update.php
// Procesa la actualización de un usuario existente.

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = $_POST['id'] ?? null;
    $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $estado = $_POST['estado'] ?? 'Activo';
    $roles_seleccionados = $_POST['roles'] ?? []; // Recibe un array de IDs de roles

    if (!$id_usuario || empty($nombre_usuario)) {
        header('Location: index.php?status=error&message=' . urlencode('Faltan datos para actualizar.'));
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Actualizar la tabla principal de usuarios
        if (!empty($contrasena)) {
            // Si se proporcionó una nueva contraseña, la hasheamos
            $hash_contrasena = password_hash($contrasena, PASSWORD_DEFAULT);
            $stmt_user = $pdo->prepare("UPDATE usuarios SET nombre_usuario = ?, contrasena = ?, estado = ? WHERE id = ?");
            $stmt_user->execute([$nombre_usuario, $hash_contrasena, $estado, $id_usuario]);
        } else {
            // Si no se proporcionó contraseña, no la actualizamos
            $stmt_user = $pdo->prepare("UPDATE usuarios SET nombre_usuario = ?, estado = ? WHERE id = ?");
            $stmt_user->execute([$nombre_usuario, $estado, $id_usuario]);
        }

        // 2. Sincronizar los roles en la tabla usuario_rol
        // Primero, borramos todas las asignaciones de roles existentes para este usuario
        $stmt_delete_roles = $pdo->prepare("DELETE FROM usuario_rol WHERE id_usuario = ?");
        $stmt_delete_roles->execute([$id_usuario]);

        // Segundo, insertamos las nuevas asignaciones seleccionadas
        if (!empty($roles_seleccionados)) {
            $stmt_insert_rol = $pdo->prepare("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (?, ?)");
            foreach ($roles_seleccionados as $id_rol) {
                $stmt_insert_rol->execute([$id_usuario, $id_rol]);
            }
        }

        $pdo->commit();

        header('Location: index.php?status=success&message=' . urlencode('Usuario actualizado correctamente.'));
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = urlencode('Error al actualizar el usuario: ' . $e->getMessage());
        header('Location: edit.php?id=' . $id_usuario . '&status=error&message=' . $message);
        exit;
    }
}

// Recoger datos
$id = $_POST['id'];
$nombre_usuario = trim($_POST['nombre_usuario']);
$rol = $_POST['rol'];
$estado = $_POST['estado'];
$contrasena = $_POST['contrasena'];
$confirmar_contrasena = $_POST['confirmar_contrasena'];

// Validaciones
if (empty($id) || empty($nombre_usuario) || empty($rol) || empty($estado)) {
    header('Location: edit.php?id=' . $id . '&status=error&message=' . urlencode('Los campos de usuario, rol y estado son obligatorios.'));
    exit();
}

if (!empty($contrasena) && ($contrasena !== $confirmar_contrasena)) {
    header('Location: edit.php?id=' . $id . '&status=error&message=' . urlencode('Las contraseñas no coinciden.'));
    exit();
}

try {
    // Verificar que el nombre de usuario no esté ya en uso por otro usuario
    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ? AND id != ?");
    $stmt_check->execute([$nombre_usuario, $id]);
    if ($stmt_check->fetch()) {
        header('Location: edit.php?id=' . $id . '&status=error&message=' . urlencode('El nombre de usuario ya está en uso.'));
        exit();
    }
    
    // Si se proporcionó una nueva contraseña, actualizarla. Si no, mantener la antigua.
    if (!empty($contrasena)) {
        $hashed_password = password_hash($contrasena, PASSWORD_BCRYPT);
        $sql = "UPDATE usuarios SET nombre_usuario = ?, rol = ?, estado = ?, contrasena = ? WHERE id = ?";
        $params = [$nombre_usuario, $rol, $estado, $hashed_password, $id];
    } else {
        $sql = "UPDATE usuarios SET nombre_usuario = ?, rol = ?, estado = ? WHERE id = ?";
        $params = [$nombre_usuario, $rol, $estado, $id];
    }
    
    $stmt_update = $pdo->prepare($sql);
    $stmt_update->execute($params);

    header('Location: index.php?status=success&message=' . urlencode('Usuario actualizado exitosamente.'));
    exit();

} catch (PDOException $e) {
    header('Location: edit.php?id=' . $id . '&status=error&message=' . urlencode('Error de base de datos: ' . $e->getMessage()));
    exit();
}
?>
