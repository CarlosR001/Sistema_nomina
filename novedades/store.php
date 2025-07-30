<?php
require_once '../config/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_contrato = $_POST['id_contrato'];
    $id_concepto = $_POST['id_concepto'];
    $monto_valor = $_POST['monto_valor'];
    $periodo_aplicacion = $_POST['periodo_aplicacion'];

    if (!empty($id_contrato) && !empty($id_concepto) && !empty($monto_valor) && !empty($periodo_aplicacion)) {
        try {
            $sql = "INSERT INTO NovedadesPeriodo (id_contrato, id_concepto, monto_valor, periodo_aplicacion, estado_novedad) VALUES (?, ?, ?, ?, 'Pendiente')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_contrato, $id_concepto, $monto_valor, $periodo_aplicacion]);
            header("Location: index.php?status=success");
        } catch (PDOException $e) {
            header("Location: index.php?status=error&message=" . urlencode($e->getMessage()));
        }
        exit();
    }
}
header("Location: index.php");
exit();