<?php
// users/update.php
// Procesa la actualización de un usuario existente.

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
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
