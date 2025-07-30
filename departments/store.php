<?php
// departments/store.php

require_once '../auth.php'; // Carga el sistema de autenticación
require_login(); // Asegura que el usuario esté logueado
require_role('Administrador'); // Solo Administradores pueden acceder a esta sección

// La conexión $pdo ya está disponible a través de auth.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre_departamento = trim($_POST['nombre_departamento']);

    if (!empty($nombre_departamento)) {
        try {
            // Asumo que este script es solo para crear, si también edita, la lógica debe ser diferente.
            $sql = "INSERT INTO Departamentos (nombre_departamento, estado) VALUES (:nombre_departamento, 'Activo')";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nombre_departamento', $nombre_departamento);
            $stmt->execute();
            
            header("Location: index.php?status=success");
            exit();

        } catch (PDOException $e) {
            // Manejar error de duplicado (ej. nombre de departamento ya existe)
            if ($e->errorInfo[1] == 1062) { 
                header("Location: index.php?status=error&message=duplicate");
            } else {
                // Por seguridad, no mostramos el mensaje de la BBDD directamente al usuario.
                header("Location: index.php?status=error&message=Error%20al%20guardar%20el%20departamento.");
            }
            exit();
        }
    }
}
// Si la solicitud no es POST o el nombre está vacío, redirigir
header("Location: index.php?status=error&message=Datos%20inv%C3%A1lidos.");
exit();