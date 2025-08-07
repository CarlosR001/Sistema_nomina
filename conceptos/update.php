<?php
// conceptos/update.php - v2.0 (con Código TSS)

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $codigo_concepto = trim($_POST['codigo_concepto']);
    $descripcion_publica = trim($_POST['descripcion_publica']);
    $tipo_concepto = $_POST['tipo_concepto'];
    $afecta_tss = $_POST['afecta_tss'];
    $afecta_isr = $_POST['afecta_isr'];
    // El nuevo campo puede estar vacío
    $codigo_tss = trim($_POST['codigo_tss']) ?: null;

    if (empty($id) || empty($codigo_concepto) || empty($descripcion_publica)) {
        header('Location: index.php?status=error&message=Faltan campos por completar.');
        exit();
    }

    try {
        // Consulta SQL con la nueva columna
        $stmt = $pdo->prepare("UPDATE ConceptosNomina SET codigo_concepto = ?, descripcion_publica = ?, tipo_concepto = ?, afecta_tss = ?, afecta_isr = ?, codigo_tss = ? WHERE id = ?");
        $stmt->execute([$codigo_concepto, $descripcion_publica, $tipo_concepto, $afecta_tss, $afecta_isr, $codigo_tss, $id]);
        
        header('Location: index.php?status=success&message=Concepto actualizado correctamente.');
        exit();
    } catch (PDOException $e) {
        header('Location: index.php?status=error&message=Error al actualizar el concepto: ' . urlencode($e->getMessage()));
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
