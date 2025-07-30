<?php
// employees/store.php

require_once '../auth.'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Administrador'); // Solo Administradores pueden crear empleados

// La conexión $pdo ya está disponible a través de auth.php

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
            header("Location: " . BASE_URL . "employees/index.php?status=success&message=Empleado%20creado%20exitosamente.");
            exit();
        } catch (PDOException $e) {
            // Error al insertar (ej. duplicado de cédula o email)
            header("Location: " . BASE_URL . "employees/create.php?status=error&message=" . urlencode("Error al crear el empleado: " . $e->getMessage()));
            exit();
        }
    } else {
        header("Location: " . BASE_URL . "employees/create.php?status=error&message=Faltan%20campos%20requeridos.");
        exit();
    }
} else {
    // Si no es una solicitud POST, redirigir al listado de empleados
    header("Location: " . BASE_URL . "employees/index.php");
    exit();
}
