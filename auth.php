<?php
require_once 'config/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username']));
    $password = trim($_POST['password']);
    
    $stmt = $pdo->prepare("
        SELECT u.*, c.id as id_contrato 
        FROM Usuarios u 
        JOIN Contratos c ON u.id_empleado = c.id_empleado 
        WHERE LOWER(u.nombre_usuario) = ? AND u.estado = 'Activo' AND c.estado_contrato = 'Vigente'
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['contrasena'])) {
        // La sesión ya está iniciada por init.php
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['nombre_usuario'] = $user['nombre_usuario'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['id_contrato'] = $user['id_contrato'];
        $_SESSION['id_empleado'] = $user['id_empleado'];
        
        header('Location: ' . BASE_URL . 'index.php');
        exit();
        
    } else {
        header('Location: ' . BASE_URL . 'login.php?error=1');
        exit();
    }
}