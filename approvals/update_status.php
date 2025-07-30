<?php
// approvals/update_status.php

require_once '../auth.php'; // Carga el sistema de autenticación, inicia la sesión y $pdo
require_login(); // Asegura que el usuario esté logueado
require_role(['Admin', 'Supervisor']); // Solo Admin y Supervisores pueden acceder

// La conexión $pdo ya está disponible a través de auth.php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registros']) && isset($_POST['action'])) {

    $registros_ids = $_POST['registros'];
    $nuevo_estado = $_POST['action']; // 'Aprobado' o 'Rechazado'
    $usuario_aprobador_id = $_SESSION['user_id']; // Obtener el ID del usuario logueado de la sesión

    // Validar que el nuevo estado sea uno de los permitidos
    if (!in_array($nuevo_estado, ['Aprobado', 'Rechazado'])) {
        header('Location: index.php?status=error&message=Acci%C3%B3n%20no%20v%C3%A1lida.');
        exit();
    }

    // Asegurarnos de que los IDs sean números y evitar inyección SQL en el IN clause
    $ids_filtrados = array_filter($registros_ids, 'is_numeric');

    if (!empty($ids_filtrados)) {
        // Crear una cadena de placeholders (?,?,?) para la consulta IN
        $placeholders = implode(',', array_fill(0, count($ids_filtrados), '?'));

        // Añadir id_usuario_aprobador y fecha_aprobacion a la actualización
        $sql = "UPDATE RegistroHoras SET estado_registro = ?, id_usuario_aprobador = ?, fecha_aprobacion = NOW() WHERE id IN ($placeholders)";

        try {
            $stmt = $pdo->prepare($sql);
            // Los parámetros deben ir en el orden correcto: nuevo_estado, id_usuario_aprobador, y luego todos los IDs.
            $params = array_merge([$nuevo_estado, $usuario_aprobador_id], $ids_filtrados);
            $stmt->execute($params);

            header("Location: index.php?status=success");
            exit();

        } catch (PDOException $e) {
            // Manejo de errores más detallado en un entorno de producción
            header("Location: index.php?status=error&message=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        header("Location: index.php?status=error&message=No%20se%20seleccionaron%20registros.");
        exit();
    }
}

header("Location: index.php");
exit();
