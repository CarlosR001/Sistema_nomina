<?php
// users/batch_update.php
// Procesa acciones en lote para los usuarios seleccionados.

require_once '../auth.php';
require_login();
require_permission('usuarios.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_error('index.php', 'Acceso no permitido.');
}

$user_ids = $_POST['user_ids'] ?? [];
$action = $_POST['action'] ?? '';

if (empty($user_ids) || empty($action)) {
    redirect_with_error('index.php', 'No se seleccionaron usuarios o ninguna acción.');
}

// Prevenir que un administrador se modifique a sí mismo en lote
if (($key = array_search($_SESSION['user_id'], $user_ids)) !== false) {
    unset($user_ids[$key]);
}

if (empty($user_ids)) {
    redirect_with_error('index.php', 'No se puede realizar una acción en lote sobre su propio usuario.');
}

try {
    // Creamos los placeholders (?) para la consulta SQL, uno por cada ID
    $placeholders = rtrim(str_repeat('?,', count($user_ids)), ',');
    $sql = '';
    $log_action = '';

    switch ($action) {
        case 'force_password_change':
            $sql = "UPDATE usuarios SET debe_cambiar_contrasena = TRUE WHERE id IN ($placeholders)";
            $log_action = 'Forzó cambio de contraseña en lote';
            break;
        case 'activate':
            $sql = "UPDATE usuarios SET estado = 'Activo' WHERE id IN ($placeholders)";
            $log_action = 'Activó usuarios en lote';
            break;
        case 'deactivate':
            $sql = "UPDATE usuarios SET estado = 'Inactivo' WHERE id IN ($placeholders)";
            $log_action = 'Desactivó usuarios en lote';
            break;
        default:
            redirect_with_error('index.php', 'Acción no válida.');
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($user_ids);

    log_activity($log_action, 'usuarios', null, 'IDs: ' . implode(', ', $user_ids));
    redirect_with_success('index.php', 'Acción en lote aplicada correctamente a ' . count($user_ids) . ' usuario(s).');

} catch (PDOException $e) {
    error_log("Error en acción en lote: " . $e->getMessage());
    redirect_with_error('index.php', 'Error al aplicar la acción en lote.');
}
?>
