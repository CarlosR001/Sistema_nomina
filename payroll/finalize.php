<?php
// payroll/finalize.php
require_once '../config/init.php';
if (!isset($_SESSION['usuario_id'])) { die('Acceso no autorizado.'); }

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_nomina = $_GET['id'];
    try {
        $sql = "UPDATE NominasProcesadas SET estado_nomina = 'Aprobada y Finalizada' WHERE id = ?";
        $pdo->prepare($sql)->execute([$id_nomina]);
        header('Location: ' . BASE_URL . 'payroll/review.php?status=finalized');
        exit();
    } catch (PDOException $e) {
        header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=' . urlencode($e->getMessage()));
        exit();
    }
} else {
    header('Location: ' . BASE_URL . 'payroll/review.php');
    exit();
}