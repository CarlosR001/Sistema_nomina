<?php
// projects/store.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Admin'); // Solo Admin pueden gestionar proyectos

// La conexión $pdo ya está disponible a través de auth.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_proyecto = trim($_POST['nombre_proyecto']);
    $codigo_proyecto = !empty($_POST['codigo_proyecto']) ? trim($_POST['codigo_proyecto']) : null;

    if (!empty($nombre_proyecto)) {
        try {
            $sql = "INSERT INTO Proyectos (nombre_proyecto, codigo_proyecto, estado_proyecto) VALUES (:nombre, :codigo, 'Activo')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nombre' => $nombre_proyecto, ':codigo' => $codigo_proyecto]);
            header("Location: index.php?status=success&message=Proyecto%20guardado%20exitosamente.");
            exit();
        } catch (PDOException $e) {
            header("Location: index.php?status=error&message=" . urlencode("Error al guardar el proyecto: " . $e->getMessage()));
            exit();
        }
    }
    header("Location: index.php?status=error&message=El%20nombre%20del%20proyecto%20no%20puede%20estar%20vac%C3%ADo.");
    exit();
}
header("Location: index.php");
exit();