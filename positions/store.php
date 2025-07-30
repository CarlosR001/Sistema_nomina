<?php
// positions/store.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Administrador'); // Solo Administradores pueden gestionar posiciones

// La conexión $pdo ya está disponible a través de auth.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre_posicion = trim($_POST['nombre_posicion']);
    $id_departamento = $_POST['id_departamento'];

    if (!empty($nombre_posicion) && !empty($id_departamento)) {
        try {
            $sql = "INSERT INTO Posiciones (nombre_posicion, id_departamento) VALUES (:nombre_posicion, :id_departamento)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nombre_posicion', $nombre_posicion);
            $stmt->bindParam(':id_departamento', $id_departamento);
            $stmt->execute();
            
            header("Location: index.php?status=success&message=Posici%C3%B3n%20guardada%20exitosamente.");
            exit();

        } catch (PDOException $e) {
            header("Location: index.php?status=error&message=" . urlencode("Error al guardar la posición: " . $e->getMessage()));
            exit();
        }
    }
    header("Location: index.php?status=error&message=Faltan%20campos%20requeridos.");
    exit();
}
header("Location: index.php");
exit();