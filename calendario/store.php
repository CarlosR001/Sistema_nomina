<?php
// calendario/store.php

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'];
    $descripcion = trim($_POST['descripcion']);
    $tipo_dia = 'Feriado'; // Asumimos que esta interfaz solo añade feriados

    if (!empty($fecha) && !empty($descripcion)) {
        try {
            $sql = "INSERT INTO CalendarioLaboralRD (fecha, descripcion, tipo_dia) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fecha, $descripcion, $tipo_dia]);
            header("Location: index.php?status=success&message=Feriado%20guardado%20exitosamente.");
        } catch (PDOException $e) {
            header("Location: index.php?status=error&message=" . urlencode("Error al guardar el feriado: " . $e->getMessage()));
        }
        exit();
    }
    header("Location: index.php?status=error&message=Faltan%20campos%20requeridos.");
    exit();
}
header("Location: index.php");
exit();
