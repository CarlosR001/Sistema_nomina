<?php
// conceptos/update.php - v2.1 (con Visibilidad en Volante)

require_once '../auth.php';
require_login();
require_permission('organizacion.gestionar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger todos los campos del formulario de edición
    $id = $_POST['id'];
    $codigo_concepto = trim($_POST['codigo_concepto']);
    $descripcion_publica = trim($_POST['descripcion_publica']);
    $tipo_concepto = $_POST['tipo_concepto'];
    $afecta_tss = $_POST['afecta_tss'];
    $afecta_isr = $_POST['afecta_isr'];
    $codigo_tss = trim($_POST['codigo_tss']) ?: null;
    $incluir_en_volante = $_POST['incluir_en_volante']; // Nuevo campo

    if (empty($id) || empty($codigo_concepto) || empty($descripcion_publica)) {
        header('Location: index.php?status=error&message=Faltan campos por completar.');
        exit();
    }

    try {
        // Actualizar la consulta SQL para incluir la nueva columna
        $sql = "UPDATE conceptosnomina SET 
                    codigo_concepto = ?, 
                    descripcion_publica = ?, 
                    tipo_concepto = ?, 
                    afecta_tss = ?, 
                    afecta_isr = ?, 
                    codigo_tss = ?, 
                    incluir_en_volante = ? 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        // Añadir el nuevo valor a la ejecución
        $stmt->execute([$codigo_concepto, $descripcion_publica, $tipo_concepto, $afecta_tss, $afecta_isr, $codigo_tss, $incluir_en_volante, $id]);
        
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
