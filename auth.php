<?php
// auth.php

// Esta variable se llenará después de que el usuario inicie sesión.
$user = null;
$contrato_inspector_id = null;

if (isset($_SESSION['user_id'])) {
    // La variable $pdo debe existir antes de que este archivo sea incluido.
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && $user['rol'] === 'Inspector' && $user['id_empleado']) {
            $stmt_contrato = $pdo->prepare("SELECT id FROM Contratos WHERE id_empleado = ? AND tipo_nomina = 'Inspectores' AND estado_contrato = 'Vigente' LIMIT 1");
            $stmt_contrato->execute([$user['id_empleado']]);
            $contrato = $stmt_contrato->fetch();
            if ($contrato) {
                $contrato_inspector_id = $contrato['id'];
            }
        }
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
}

function require_role($role) {
    global $user;
    if (!$user || $user['rol'] !== $role) {
        // En un futuro, esto podría redirigir a una página de "acceso denegado".
        die('Acceso denegado. No tienes los permisos necesarios para esta acción.');
    }
}
?>
