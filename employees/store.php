<?php
// employees/store.php

// Requerir el archivo de conexión
require_once '../config/init.php';

// Verificar si se recibieron datos por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recoger y sanear los datos del formulario
    $cedula = trim($_POST['cedula']);
    $nombres = trim($_POST['nombres']);
    $primer_apellido = trim($_POST['primer_apellido']);
    $email_personal = trim($_POST['email_personal']);

    // Validar que los campos no estén vacíos (validación básica)
    if (!empty($cedula) && !empty($nombres) && !empty($primer_apellido) && !empty($email_personal)) {
        
        // Preparar la consulta SQL para evitar inyecciones SQL
        $sql = "INSERT INTO Empleados (cedula, nombres, primer_apellido, email_personal, estado_empleado) VALUES (:cedula, :nombres, :primer_apellido, :email_personal, 'Activo')";
        
        $stmt = $pdo->prepare($sql);

        // Vincular los parámetros
        $stmt->bindParam(':cedula', $cedula);
        $stmt->bindParam(':nombres', $nombres);
        $stmt->bindParam(':primer_apellido', $primer_apellido);
        $stmt->bindParam(':email_personal', $email_personal);

        // Ejecutar la consulta
        try {
            $stmt->execute();
            // Redirigir al listado de empleados con un mensaje de éxito
            header("Location: index.php?status=success");
            exit();
        } catch (PDOException $e) {
            // En caso de error, redirigir con un mensaje de error
            // En un entorno real, registraríamos el error en un log
            header("Location: create.php?status=error&message=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        // Redirigir si hay campos vacíos
        header("Location: create.php?status=error&message=empty_fields");
        exit();
    }
} else {
    // Si no es una solicitud POST, redirigir al listado
    header("Location: index.php");
    exit();
}