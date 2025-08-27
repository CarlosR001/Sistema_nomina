<?php
// users/update.php - v2.1 (Hash Argon2id y Log de Actividad)
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
    redirect_with_error('edit.php?id=' . $id_usuario, 'Faltan datos para actualizar.');
}
if (!empty($contrasena) && $contrasena !== $confirmar_contrasena) {
    redirect_with_error('edit.php?id=' . $id_usuario, 'Las contraseñas no coinciden.');
}

try {
    // Verificar que el nuevo nombre de usuario no esté ya en uso por OTRO usuario
    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ? AND id != ?");
    $stmt_check->execute([$nombre_usuario, $id_usuario]);
    if ($stmt_check->fetch()) {
        redirect_with_error('edit.php?id=' . $id_usuario, 'El nombre de usuario ya está en uso.');
    }

    $pdo->beginTransaction();

    // 2. Actualizar la tabla principal de usuarios
    $log_details = ['usuario' => $nombre_usuario, 'estado' => $estado];
    if (!empty($contrasena)) {
// Se crea el hash usando explícitamente el algoritmo Argon2id y los parámetros personalizados.
        $hash_contrasena = password_hash($contrasena, PASSWORD_ARGON2ID, [
            'memory_cost' => ARGON2_MEMORY_COST,
            'time_cost'   => ARGON2_TIME_COST,
            'threads'     => ARGON2_THREADS
        ]);
        $stmt_user = $pdo->prepare("UPDATE usuarios SET nombre_usuario = ?, contrasena = ?, estado = ? WHERE id = ?");
        $stmt_user->execute([$nombre_usuario, $hash_contrasena, $estado, $id_usuario]);
        $log_details['contrasena'] = 'actualizada';
    } else {
        $stmt_user = $pdo->prepare("UPDATE usuarios SET nombre_usuario = ?, estado = ? WHERE id = ?");
        $stmt_user->execute([$nombre_usuario, $estado, $id_usuario]);
    }

    // 3. Sincronizar los roles
    $stmt_delete_roles = $pdo->prepare("DELETE FROM usuario_rol WHERE id_usuario = ?");
    $stmt_delete_roles->execute([$id_usuario]);

    if (!empty($roles_seleccionados)) {
        $stmt_insert_rol = $pdo->prepare("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (?, ?)");
        foreach ($roles_seleccionados as $id_rol) {
            $stmt_insert_rol->execute([$id_usuario, $id_rol]);
        }
    }

    // 4. Sincronizar los permisos explícitos
    $stmt_delete_perms = $pdo->prepare("DELETE FROM permisos_usuario WHERE id_usuario = ?");
    $stmt_delete_perms->execute([$id_usuario]);

    if (!empty($permisos_explicitos)) {
        $stmt_insert_perm = $pdo->prepare("INSERT INTO permisos_usuario (id_usuario, id_permiso, tiene_permiso) VALUES (?, ?, ?)");
        foreach ($permisos_explicitos as $id_permiso => $valor) {
            if ($valor !== '-1') {
                $stmt_insert_perm->execute([$id_usuario, $id_permiso, $valor]);
            }
        }
    }

    $pdo->commit();

    // --- AÑADIDO: Registro de Actividad ---
    log_activity('Actualizó un usuario', 'usuarios', $id_usuario, json_encode($log_details));

    redirect_with_success('index.php', 'Usuario actualizado correctamente.');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al actualizar usuario: " . $e->getMessage());
    redirect_with_error('edit.php?id=' . $id_usuario, 'Error de base de datos al actualizar el usuario.');
}
