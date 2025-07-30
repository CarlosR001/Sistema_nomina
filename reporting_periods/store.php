<?php
// reporting_periods/store.php
require_once '../config/init.php';

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
            header("Location: index.php?status=success");
        } catch (PDOException $e) {
            header("Location: index.php?status=error&message=" . urlencode($e->getMessage()));
        }
        exit();
    }
}
header("Location: index.php");
exit();