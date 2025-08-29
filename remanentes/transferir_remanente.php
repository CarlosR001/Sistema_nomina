<?php
require_once '../auth.php';
require_login();
require_permission('nomina.remanentes.transferir');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$id_nomina_origen = $_POST['id_nomina_origen'] ?? null;
$id_contrato = $_POST['id_contrato'] ?? null;

if (!$id_nomina_origen || !$id_contrato) {
    header('Location: index.php?status=error&message=' . urlencode('Faltan parámetros necesarios.'));
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Obtener la fecha de la nómina de origen para encontrar la anterior.
    $stmt_fecha_origen = $pdo->prepare("SELECT periodo_fin FROM nominasprocesadas WHERE id = ?");
    $stmt_fecha_origen->execute([$id_nomina_origen]);
    $fecha_fin_origen = $stmt_fecha_origen->fetchColumn();

    if (!$fecha_fin_origen) {
        throw new Exception("No se encontró la nómina de origen.");
    }

    // 2. Encontrar la nómina inmediatamente anterior para el mismo contrato.
    $stmt_nomina_anterior = $pdo->prepare("
        SELECT np.id, np.periodo_inicio, np.periodo_fin
        FROM nominasprocesadas np
        JOIN nominadetalle nd ON np.id = nd.id_nomina_procesada
        WHERE nd.id_contrato = ? AND np.periodo_fin < ?
        ORDER BY np.periodo_fin DESC
        LIMIT 1
    ");
    $stmt_nomina_anterior->execute([$id_contrato, $fecha_fin_origen]);
    $nomina_anterior = $stmt_nomina_anterior->fetch(PDO::FETCH_ASSOC);

    if (!$nomina_anterior) {
        throw new Exception("No se encontró una nómina anterior para este empleado a la cual transferir el remanente.");
    }
    
    $id_nomina_destino = $nomina_anterior['id'];
    $periodo_aplicacion_novedad = $nomina_anterior['periodo_fin'];

    // 3. Obtener todas las deducciones (remanentes) de la nómina de origen.
    $stmt_deducciones = $pdo->prepare("
        SELECT id, codigo_concepto, monto_resultado 
        FROM nominadetalle 
        WHERE id_nomina_procesada = ? AND id_contrato = ? AND tipo_concepto = 'Deducción' AND monto_resultado > 0
    ");
    $stmt_deducciones->execute([$id_nomina_origen, $id_contrato]);
    $remanentes_a_transferir = $stmt_deducciones->fetchAll(PDO::FETCH_ASSOC);

    if (empty($remanentes_a_transferir)) {
        throw new Exception("No se encontraron deducciones remanentes para transferir.");
    }

    // 4. Mapeo de conceptos de deducción a conceptos de ajuste.
    // Esto es importante para no mezclar conceptos originales con ajustes.
    $mapa_conceptos = [
        'DED-ISR' => 'DED-AJUSTE-ISR',
        'DED-AFP' => 'DED-AJUSTE-AFP',
        'DED-SFS' => 'DED-AJUSTE-SFS',
        // Añadir otros mapeos si es necesario.
    ];

    $stmt_insertar_novedad = $pdo->prepare("
        INSERT INTO novedadesperiodo (id_contrato, id_concepto, monto_valor, periodo_aplicacion, estado_novedad, id_usuario_creador, origen_novedad)
        VALUES (?, ?, ?, ?, 'Pendiente', ?, ?)
    ");

    $stmt_id_concepto = $pdo->prepare("SELECT id FROM conceptosnomina WHERE codigo_concepto = ?");

    foreach ($remanentes_a_transferir as $rem) {
        $codigo_original = $rem['codigo_concepto'];
        $codigo_ajuste = $mapa_conceptos[$codigo_original] ?? $codigo_original . '_AJUSTE'; // Fallback por si no está en el mapa

        // Buscar el ID del concepto de ajuste
        $stmt_id_concepto->execute([$codigo_ajuste]);
        $id_concepto_ajuste = $stmt_id_concepto->fetchColumn();

        if (!$id_concepto_ajuste) {
            // Si no existe, lo creamos para futura referencia.
            // En un caso real, esto debería alertar a un administrador.
            $stmt_crear_concepto = $pdo->prepare("INSERT INTO conceptosnomina (codigo_concepto, descripcion_publica, tipo_concepto, afecta_tss, afecta_isr, es_recurrente, es_fijo, es_informativo) VALUES (?, ?, 'Deducción', 0, 0, 0, 0, 0)");
            $stmt_crear_concepto->execute([$codigo_ajuste, "Ajuste de " . str_replace('DED-', '', $codigo_original)]);
            $id_concepto_ajuste = $pdo->lastInsertId();
        }

        // Insertar la novedad en el período de la nómina anterior.
        $stmt_insertar_novedad->execute([
            $id_contrato,
            $id_concepto_ajuste,
            $rem['monto_resultado'],
            $periodo_aplicacion_novedad,
            $_SESSION['user_id'],
            'Transferencia Remanente Nomina ID ' . $id_nomina_origen
        ]);
    }

    // 5. Eliminar las deducciones originales de la nómina omitida para "limpiarla".
    $stmt_delete_deducciones = $pdo->prepare("
        DELETE FROM nominadetalle 
        WHERE id_nomina_procesada = ? AND id_contrato = ? AND tipo_concepto = 'Deducción'
    ");
    $stmt_delete_deducciones->execute([$id_nomina_origen, $id_contrato]);

    // 6. Opcional: Recalcular totales de la nómina de origen (ahora sin deducciones).
    // Esta parte puede ser compleja. Por ahora, asumimos que la eliminación es suficiente.

    $pdo->commit();

    $message = "Remanente transferido exitosamente como una novedad a la nómina del período " . $nomina_anterior['periodo_inicio'] . " al " . $nomina_anterior['periodo_fin'] . ". La nómina anterior debe ser recalculada para aplicar los cambios.";
    header('Location: index.php?status=success&message=' . urlencode($message));
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: index.php?status=error&message=' . urlencode('Error crítico: ' . $e->getMessage()));
    exit();
}
?>
