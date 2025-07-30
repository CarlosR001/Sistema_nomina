<?php
// conceptos/store.php

require_once '../auth.php';
require_login();
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $codigo_concepto = trim($_POST['codigo_concepto']);
    $descripcion_publica = trim($_POST['descripcion_publica']);
    $tipo_concepto = $_POST['tipo_concepto'];
    $origen_calculo = $_POST['origen_calculo'];
    $afecta_tss = $_POST['afecta_tss'];
    $afecta_isr = $_POST['afecta_isr'];

    // Validación básica
    if (empty($codigo_concepto) || empty($descripcion_publica)) {
        header("Location: index.php?status=error&message=El%20c%C3%B3digo%20y%20la%20descripci%C3%B3n%20son%20obligatorios.");
        exit();
    }

    try {
        // Usar INSERT ... ON DUPLICATE KEY UPDATE para manejar tanto creación como edición.
        // Se asume que 'codigo_concepto' es una clave ÚNICA en la tabla.
        $sql = "INSERT INTO ConceptosNomina (codigo_concepto, descripcion_publica, tipo_concepto, origen_calculo, afecta_tss, afecta_isr) 
                VALUES (:codigo, :descripcion, :tipo, :origen, :tss, :isr)
                ON DUPLICATE KEY UPDATE 
                descripcion_publica = :descripcion, 
                tipo_concepto = :tipo, 
                origen_calculo = :origen,
                afecta_tss = :tss,
                afecta_isr = :isr";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codigo' => $codigo_concepto,
            ':descripcion' => $descripcion_publica,
            ':tipo' => $tipo_concepto,
            ':origen' => $origen_calculo,
            ':tss' => $afecta_tss,
            ':isr' => $afecta_isr
        ]);

        header("Location: index.php?status=success&message=Concepto%20guardado%20exitosamente.");

    } catch (PDOException $e) {
        header("Location: index.php?status=error&message=" . urlencode("Error al guardar el concepto: " . $e->getMessage()));
    }
    exit();
}

header("Location: index.php");
exit();
