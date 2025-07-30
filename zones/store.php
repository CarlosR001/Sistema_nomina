<?php
// zones/store.php
require_once '../config/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_zona = trim($_POST['nombre_zona']);
    $monto = $_POST['monto'];

    if (!empty($nombre_zona) && is_numeric($monto)) {
        try {
            $sql = "INSERT INTO ZonasTransporte (nombre_zona_o_muelle, monto_transporte_completo) VALUES (:nombre, :monto)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nombre' => $nombre_zona, ':monto' => $monto]);
            header("Location: index.php?status=success");
        } catch (PDOException $e) {
            header("Location: index.php?status=error&message=" . urlencode($e->getMessage()));
        }
        exit();
    }
}
header("Location: index.php");
exit();