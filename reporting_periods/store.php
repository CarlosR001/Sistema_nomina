<?php
// reporting_periods/store.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Admin'); // Solo Admin pueden gestionar períodos de reporte

// La conexión $pdo ya está disponible a través de auth.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $tipo_nomina = $_POST['tipo_nomina'];

    if (!empty($fecha_inicio) && !empty($fecha_fin) && !empty($tipo_nomina)) {
        try {
            $sql = "INSERT INTO PeriodosDeReporte (fecha_inicio_periodo, fecha_fin_periodo, tipo_nomina, estado_periodo) VALUES (:inicio, :fin, :tipo, 'Abierto')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':inicio' => $fecha_inicio,
                ':fin' => $fecha_fin,
                ':tipo' => $tipo_nomina
            ]);
            header("Location: index.php?status=success&message=Per%C3%ADodo%20abierto%20exitosamente.");
            exit();
        } catch (PDOException $e) {
            header("Location: index.php?status=error&message=" . urlencode("Error al abrir el per%C3%ADodo: " . $e->getMessage()));
            exit();
        }
    }
    header("Location: index.php?status=error&message=Faltan%20campos%20requeridos%20o%20son%20inv%C3%A1lidos.");
    exit();
}
header("Location: index.php");
exit();