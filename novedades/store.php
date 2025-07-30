<?php
// novedades/store.php

require_once '../auth.php'; // Carga el sistema de autenticación (incluye DB y sesión)
require_login(); // Asegura que el usuario esté logueado
require_role(['Administrador', 'Contabilidad']); // Roles permitidos para almacenar novedades

// La conexión $pdo ya está disponible a través de auth.php

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
            exit();
        } catch (PDOException $e) {
            header("Location: index.php?status=error&message=" . urlencode("Error al guardar la novedad: " . $e->getMessage()));
            exit();
        }
    }
    // Si faltan campos, redirigir con un mensaje de error
    header("Location: index.php?status=error&message=Faltan%20campos%20requeridos.");
    exit();
}
// Si no es una solicitud POST, redirigir al índice de novedades
header("Location: index.php");
exit();