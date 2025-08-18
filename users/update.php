<?php
// users/update.php - v2.0 (Control de Acceso Híbrido)
// Procesa la actualización de un usuario, sus roles y sus permisos explícitos.

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// 1. Recoger y validar todos los datos del formulario
$id_usuario = $_POST['id'] ?? null;
$nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
$estado = $_POST['estado'] ?? 'Activo';
$contrasena = $_POST['contrasena'] ?? '';
$confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';
$roles_seleccionados = $_POST['roles'] ?? [];
$permisos_explicitos = $_POST['permisos'] ?? [];

// Validaciones básicas
if (!$id_usuario || empty($nombre_usuario)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Faltan datos para actualizar.'];
    header('Location: edit.php?id=' . $id_usuario);
    exit;
}

if (!empty($contrasena) && $contrasena !== $confirmar_contrasena) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Las contraseñas no coinciden.'];
    header('Location: edit.php?id=' . $id_usuario);
    exit;
}

try {
    // Verificar que el nuevo nombre de usuario no esté ya en uso por OTRO usuario
    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ? AND id != ?");
    $stmt_check->execute([$nombre_usuario, $id_usuario]);
    if ($stmt_check->fetch()) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'El nombre de usuario ya está en uso.'];
        header('Location: edit.php?id=' . $id_usuario);
        exit();
    }

    $pdo->beginTransaction();

    // 2. Actualizar la tabla principal de usuarios
    if (!empty($contrasena)) {
        $hash_contrasena = password_hash($contrasena, PASSWORD_DEFAULT);
        $stmt_user = $pdo->prepare("UPDATE usuarios SET nombre_usuario = ?, contrasena = ?, estado = ? WHERE id = ?");
        $stmt_user->execute([$nombre_usuario, $hash_contrasena, $estado, $id_usuario]);
    } else {
        $stmt_user = $pdo->prepare("UPDATE usuarios SET nombre_usuario = ?, estado = ? WHERE id = ?");
        $stmt_user->execute([$nombre_usuario, $estado, $id_usuario]);
    }

    // 3. Sincronizar los roles en la tabla usuario_rol
    $stmt_delete_roles = $pdo->prepare("DELETE FROM usuario_rol WHERE id_usuario = ?");
    $stmt_delete_roles->execute([$id_usuario]);

    if (!empty($roles_seleccionados)) {
        $stmt_insert_rol = $pdo->prepare("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (?, ?)");
        foreach ($roles_seleccionados as $id_rol) {
            $stmt_insert_rol->execute([$id_usuario, $id_rol]);
        }
    }

    // 4. Sincronizar los permisos explícitos en la tabla permisos_usuario
    $stmt_delete_perms = $pdo->prepare("DELETE FROM permisos_usuario WHERE id_usuario = ?");
    $stmt_delete_perms->execute([$id_usuario]);

    if (!empty($permisos_explicitos)) {
        $stmt_insert_perm = $pdo->prepare("INSERT INTO permisos_usuario (id_usuario, id_permiso, tiene_permiso) VALUES (?, ?, ?)");
        foreach ($permisos_explicitos as $id_permiso => $valor) {
            // Solo insertamos si el valor no es "heredar" (-1)
            if ($valor !== '-1') {
                $stmt_insert_perm->execute([$id_usuario, $id_permiso, $valor]);
            }
        }
    }

    $pdo->commit();

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Usuario actualizado correctamente.'];
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al actualizar usuario: " . $e->getMessage());
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de base de datos al actualizar el usuario.'];
    header('Location: edit.php?id=' . $id_usuario);
    exit;
}
