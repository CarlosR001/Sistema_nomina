<?php
// payroll/finalize.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role('Admin'); // Solo Admin pueden finalizar la nómina.

// La conexión $pdo ya está disponible a través de auth.php

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_nomina = $_GET['id'];
    try {
        $sql = "UPDATE NominasProcesadas SET estado_nomina = 'Aprobada y Finalizada' WHERE id = ?";
        $pdo->prepare($sql)->execute([$id_nomina]);
        header('Location: ' . BASE_URL . 'payroll/review.php?status=finalized');
        exit();
    } catch (PDOException $e) {
        header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=' . urlencode("Error al finalizar la n%C3%B3mina: " . $e->getMessage()));
        exit();
    }
} else {
    header('Location: ' . BASE_URL . 'payroll/review.php?status=error&message=ID%20de%20n%C3%B3mina%20no%20v%C3%A1lido.');
    exit();
}