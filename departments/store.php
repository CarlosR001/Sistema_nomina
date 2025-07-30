<?php
// departments/store.php

require_once '../config/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre_departamento = trim($_POST['nombre_departamento']);

    if (!empty($nombre_departamento)) {
        try {
            $sql = "INSERT INTO Departamentos (nombre_departamento, estado) VALUES (:nombre_departamento, 'Activo')";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nombre_departamento', $nombre_departamento);
            $stmt->execute();
            
            header("Location: index.php?status=success");
            exit();

        } catch (PDOException $e) {
            // Manejar error de duplicado
            if ($e->errorInfo[1] == 1062) { // 1062 es el código de error para entrada duplicada
                header("Location: index.php?status=error&message=duplicate");
            } else {
                header("Location: index.php?status=error&message=" . urlencode($e->getMessage()));
            }
            exit();
        }
    }
}
header("Location: index.php");
exit();