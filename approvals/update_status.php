<?php
// approvals/update_status.php
require_once '../config/init.php';

// --- Verificación de Seguridad y Rol ---
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}
if (!in_array($_SESSION['rol'], ['Administrador', 'Supervisor'])) {
    die('Acceso Denegado. No tienes los permisos necesarios para realizar esta acción.');
}
// --- Fin de la verificación ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registros']) && isset($_POST['action'])) {

    $registros_ids = $_POST['registros'];
    $nuevo_estado = $_POST['action']; // 'Aprobado' o 'Rechazado'

    // Validar que el nuevo estado sea uno de los permitidos
    if (!in_array($nuevo_estado, ['Aprobado', 'Rechazado'])) {
        die("Acción no válida.");
    }

    // Asegurarnos de que los IDs sean números para evitar inyección SQL
    $ids_filtrados = array_filter($registros_ids, 'is_numeric');

    if (!empty($ids_filtrados)) {
        // Crear una cadena de placeholders (?,?,?) para la consulta IN
        $placeholders = implode(',', array_fill(0, count($ids_filtrados), '?'));

        $sql = "UPDATE RegistroHoras SET estado_registro = ? WHERE id IN ($placeholders)";

        try {
            $stmt = $pdo->prepare($sql);

            // Vincular el nuevo estado y luego todos los IDs en un solo array de parámetros
            $params = array_merge([$nuevo_estado], $ids_filtrados);
            $stmt->execute($params);

            header("Location: index.php?status=success");
            exit();

        } catch (PDOException $e) {
            // En un entorno de producción, sería mejor registrar el error que mostrarlo.
            header("Location: index.php?status=error&message=" . urlencode($e->getMessage()));
            exit();
        }
    }
}

// Si no se seleccionó nada, no es POST, o no hay IDs válidos, redirigir.
header("Location: index.php");
exit();