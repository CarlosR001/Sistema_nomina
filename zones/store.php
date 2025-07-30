<?php
// zones/store.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Admin'); // Solo Admin pueden gestionar zonas de transporte

// La conexión $pdo ya está disponible a través de auth.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_zona = trim($_POST['nombre_zona']);
    $monto = $_POST['monto'];

    if (!empty($nombre_zona) && is_numeric($monto)) {
        try {
            $sql = "INSERT INTO ZonasTransporte (nombre_zona_o_muelle, monto_transporte_completo) VALUES (:nombre, :monto)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nombre' => $nombre_zona, ':monto' => $monto]);
            header("Location: index.php?status=success&message=Zona%20guardada%20exitosamente.");
            exit();
        } catch (PDOException $e) {
            header("Location: index.php?status=error&message=" . urlencode("Error al guardar la zona: " . $e->getMessage()));
            exit();
        }
    }
    header("Location: index.php?status=error&message=Faltan%20campos%20requeridos%20o%20son%20inv%C3%A1lidos.");
    exit();
}
header("Location: index.php");
exit();