<?php
// novedades/guardar_saldo_a_favor.php

require_once '../auth.php';
require_login();
require_permission('nomina.procesar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_empleado = $_POST['id_empleado'] ?? null;
    $monto = $_POST['monto'] ?? null;
    $periodo_aplicacion = $_POST['periodo_aplicacion'] ?? null;

    if (empty($id_empleado) || !is_numeric($monto) || $monto <= 0 || empty($periodo_aplicacion)) {
        redirect_with_error('saldo_a_favor.php', 'Todos los campos son obligatorios y el monto debe ser mayor a cero.');
    }

    try {
        // 1. Obtener el ID del contrato vigente del empleado.
        $stmt_contrato = $pdo->prepare("SELECT id FROM contratos WHERE id_empleado = ? AND estado_contrato = 'Vigente' AND tipo_nomina IN ('Administrativa', 'Directiva')");
        $stmt_contrato->execute([$id_empleado]);
        $id_contrato = $stmt_contrato->fetchColumn();

        if (!$id_contrato) {
            throw new Exception("No se encontró un contrato administrativo/directivo vigente para el empleado seleccionado.");
        }

        // 2. Obtener el ID del concepto del sistema para el saldo a favor.
        $stmt_concepto = $pdo->prepare("SELECT id FROM conceptosnomina WHERE codigo_concepto = 'SYS-SALDO-FAVOR'");
        $stmt_concepto->execute();
        $id_concepto = $stmt_concepto->fetchColumn();

        if (!$id_concepto) {
            throw new Exception("El concepto de sistema 'SYS-SALDO-FAVOR' no fue encontrado. Por favor, ejecute el script SQL de configuración.");
        }

        // 3. Insertar la novedad.
        $stmt_insert = $pdo->prepare(
            "INSERT INTO novedadesperiodo (id_contrato, id_concepto, periodo_aplicacion, monto_valor, descripcion_adicional, estado_novedad)
             VALUES (?, ?, ?, ?, 'Saldo a favor de ISR registrado por el usuario.', 'Pendiente')"
        );
        $stmt_insert->execute([$id_contrato, $id_concepto, $periodo_aplicacion, $monto]);

        redirect_with_success('index.php', 'Saldo a favor registrado correctamente.');

    } catch (Exception $e) {
        redirect_with_error('saldo_a_favor.php', 'Error al guardar el saldo a favor: ' . $e->getMessage());
    }
} else {
    header('Location: index.php');
    exit;
}
?>
