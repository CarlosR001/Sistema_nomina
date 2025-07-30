<?php
// positions/store.php
require_once '../config/init.php';

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
            
            header("Location: index.php?status=success");
            exit();

        } catch (PDOException $e) {
            header("Location: index.php?status=error&message=" . urlencode($e->getMessage()));
            exit();
        }
    }
}
header("Location: index.php");
exit();