<?php
// approvals/update_record.php

require_once '../auth.php';
require_login();
require_role(['Admin', 'Supervisor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger los datos del formulario
    $registro_id = $_POST['registro_id'];
    $fecha = $_POST['fecha_trabajada'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $id_proyecto = $_POST['id_proyecto'];
    $id_zona = $_POST['id_zona_trabajo'];
    
    // Validaciones
    if (empty($registro_id) || empty($fecha) || empty($hora_inicio) || empty($hora_fin) || empty($id_proyecto) || empty($id_zona)) {
        header('Location: index.php?status=error&message=Faltan%20campos%20requeridos.');
        exit();
    }

    if (strtotime($hora_fin) <= strtotime($hora_inicio)) {
        header('Location: index.php?status=error&message=La%20hora%20de%20fin%20debe%20ser%20posterior%20a%20la%20de%20inicio.');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Obtener los datos ANTIGUOS para el log de auditoría
        $stmt_old = $pdo->prepare("SELECT * FROM registrohoras WHERE id = ?");
        $stmt_old->execute([$registro_id]);
        $old_data = $stmt_old->fetch();

        // 2. Actualizar el registro de horas con los datos NUEVOS
        $sql_update = "UPDATE registrohoras 
                       SET fecha_trabajada = ?, hora_inicio = ?, hora_fin = ?, id_proyecto = ?, id_zona_trabajo = ? 
                       WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$fecha, $hora_inicio, $hora_fin, $id_proyecto, $id_zona, $registro_id]);

        // 3. Crear el log de auditoría ("Súper Plus")
        $detalle_log = "El usuario {$_SESSION['user_info']['nombre_usuario']} modificó el registro #{$registro_id}. ";
        $detalle_log .= "Valores Antiguos -> Fecha: {$old_data['fecha_trabajada']}, Inicio: {$old_data['hora_inicio']}, Fin: {$old_data['hora_fin']}. ";
        $detalle_log .= "Valores Nuevos -> Fecha: {$fecha}, Inicio: {$hora_inicio}, Fin: {$hora_fin}.";
        
        $sql_log = "INSERT INTO logdeactividad (id_usuario, accion_realizada, tabla_afectada, id_registro_afectado, detalle) 
                    VALUES (?, 'Modificación de Horas', 'registrohoras', ?, ?)";
        $stmt_log = $pdo->prepare($sql_log);
        $stmt_log->execute([$_SESSION['user_id'], $registro_id, $detalle_log]);
        
        $pdo->commit();
        
        header('Location: index.php?status=success&message=Registro%20actualizado%20y%20auditado%20correctamente.');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: index.php?status=error&message=' . urlencode('Error al actualizar el registro: ' . $e->getMessage()));
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?>
