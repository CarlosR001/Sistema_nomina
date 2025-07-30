<?php
// employees/store.php
require_once '../config/init.php'; // Carga la sesión, DB y auth

// Proteger esta acción, solo usuarios con ciertos roles pueden crear empleados
if ($user['rol'] !== 'Admin' && $user['rol'] !== 'Supervisor') {
    die('Acceso denegado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula = trim($_POST['cedula']);
    $nombres = trim($_POST['nombres']);
    $primer_apellido = trim($_POST['primer_apellido']);
    $email_personal = trim($_POST['email_personal']);

    if (!empty($cedula) && !empty($nombres) && !empty($primer_apellido) && !empty($email_personal)) {
        $sql = "INSERT INTO Empleados (cedula, nombres, primer_apellido, email_personal, estado_empleado) VALUES (:cedula, :nombres, :primer_apellido, :email_personal, 'Activo')";
        $stmt = $pdo->prepare($sql);
        
        $params = [
            ':cedula' => $cedula,
            ':nombres' => $nombres,
            ':primer_apellido' => $primer_apellido,
            ':email_personal' => $email_personal
        ];

        try {
            $stmt->execute($params);
            header("Location: " . BASE_URL . "employees/index.php?status=success");
            exit();
        } catch (PDOException $e) {
            header("Location: " . BASE_URL . "employees/create.php?status=error&message=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        header("Location: " . BASE_URL . "employees/create.php?status=error&message=empty_fields");
        exit();
    }
} else {
    header("Location: " . BASE_URL . "employees/index.php");
    exit();
}
