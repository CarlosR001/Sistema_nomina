<?php
// projects/store.php
require_once '../config/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_proyecto = trim($_POST['nombre_proyecto']);
    $codigo_proyecto = !empty($_POST['codigo_proyecto']) ? trim($_POST['codigo_proyecto']) : null;

    if (!empty($nombre_proyecto)) {
        try {
            $sql = "INSERT INTO Proyectos (nombre_proyecto, codigo_proyecto, estado_proyecto) VALUES (:nombre, :codigo, 'Activo')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nombre' => $nombre_proyecto, ':codigo' => $codigo_proyecto]);
            header("Location: index.php?status=success");
        } catch (PDOException $e) {
            header("Location: index.php?status=error&message=" . urlencode($e->getMessage()));
        }
        exit();
    }
}
header("Location: index.php");
exit();