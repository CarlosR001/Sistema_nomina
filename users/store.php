<?php
// users/store.php
// Procesa y guarda un nuevo usuario.

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?status=error&message=Método no permitido.');
    exit();
}

// Recoger datos
$id_empleado = $_POST['id_empleado'];
$nombre_usuario = trim($_POST['nombre_usuario']);
$rol = $_POST['rol'];
$contrasena = $_POST['contrasena'];
$confirmar_contrasena = $_POST['confirmar_contrasena'];

// Validaciones
if (empty($id_empleado) || empty($nombre_usuario) || empty($rol) || empty($contrasena)) {
    header('Location: create.php?status=error&message=' . urlencode('Todos los campos son obligatorios.'));
    exit();
}

if ($contrasena !== $confirmar_contrasena) {
    header('Location: create.php?status=error&message=' . urlencode('Las contraseñas no coinciden.'));
    exit();
}

try {
    // Verificar que el nombre de usuario no esté ya en uso
    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ?");
    $stmt_check->execute([$nombre_usuario]);
    if ($stmt_check->fetch()) {
        header('Location: create.php?status=error&message=' . urlencode('El nombre de usuario ya está en uso.'));
        exit();
    }
    
    // Hashear la contraseña
    $hashed_password = password_hash($contrasena, PASSWORD_BCRYPT);
    
    // Insertar en la base de datos
    $stmt_insert = $pdo->prepare(
        "INSERT INTO usuarios (id_empleado, nombre_usuario, contrasena, rol, estado) 
         VALUES (?, ?, ?, ?, 'Activo')"
    );
    $stmt_insert->execute([$id_empleado, $nombre_usuario, $hashed_password, $rol]);

    header('Location: index.php?status=success&message=' . urlencode('Usuario creado exitosamente.'));
    exit();

} catch (PDOException $e) {
    header('Location: create.php?status=error&message=' . urlencode('Error de base de datos: ' . $e->getMessage()));
    exit();
}
?>
