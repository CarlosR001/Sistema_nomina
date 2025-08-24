<?php
// users/store.php
// Procesa y guarda un nuevo usuario.

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario (lógica correcta)
    $id_empleado = $_POST['id_empleado'];
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $contrasena = $_POST['contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];
    $roles_asignados = $_POST['roles'] ?? []; // Se espera un array de IDs de roles

    // Validaciones
    if (empty($id_empleado) || empty($nombre_usuario) || empty($contrasena)) {
        redirect_with_error('create.php', 'Todos los campos son obligatorios.');
        exit();
    }
    if ($contrasena !== $confirmar_contrasena) {
        redirect_with_error('create.php', 'Las contraseñas no coinciden.');
        exit();
    }

    $hash_contrasena = password_hash($contrasena, PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        // 1. Insertar el usuario en la tabla 'usuarios'
        $stmt = $pdo->prepare("INSERT INTO usuarios (id_empleado, nombre_usuario, contrasena, estado) VALUES (?, ?, ?, 'Activo')");
        $stmt->execute([$id_empleado, $nombre_usuario, $hash_contrasena]);
        $id_usuario_creado = $pdo->lastInsertId();

        // 2. Insertar las asignaciones en la tabla 'usuario_rol'
        if (!empty($roles_asignados)) {
            $stmt_rol = $pdo->prepare("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (?, ?)");
            foreach ($roles_asignados as $id_rol) {
                $stmt_rol->execute([$id_usuario_creado, $id_rol]);
            }
        }
        
        $pdo->commit();
        redirect_with_success('index.php', 'Usuario creado correctamente.');
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = 'Error al crear el usuario.';
        if ($e->getCode() == 23000) {
            $message = 'El nombre de usuario ya está en uso.';
        }
        header('Location: create.php?status=error&message=' . urlencode($message));
        exit;
    }
}
