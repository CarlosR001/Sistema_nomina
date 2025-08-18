<?php
// ordenes/update.php - v2.1 (Nuevos campos + Seguridad y Validación)

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}

// Validación del Token CSRF (asumiendo que se genera en el formulario de edición)
if (empty($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de seguridad. Por favor, inténtelo de nuevo.'];
    header('Location: index.php');
    exit;
}

// Recolección y Validación de Datos
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$codigo_orden = trim($_POST['codigo_orden'] ?? '');
$numero_orden_compra = trim($_POST['numero_orden_compra'] ?? ''); // NUEVO
$id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
$id_lugar = filter_input(INPUT_POST, 'id_lugar', FILTER_VALIDATE_INT);
$id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
$id_operacion = filter_input(INPUT_POST, 'id_operacion', FILTER_VALIDATE_INT);
$id_supervisor = filter_input(INPUT_POST, 'id_supervisor', FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
$id_division = filter_input(INPUT_POST, 'id_division', FILTER_VALIDATE_INT);
$estado_orden = $_POST['estado_orden'] ?? 'Pendiente';
$observaciones = trim($_POST['observaciones'] ?? ''); // NUEVO

$errors = [];
if (!$id) { $errors[] = 'ID de orden inválido.'; }
if (empty($codigo_orden)) { $errors[] = 'El código de la orden es obligatorio.'; }
if (!$id_cliente) { $errors[] = 'Debe seleccionar un cliente.'; }
// ... (se pueden añadir más validaciones si es necesario)

if (!empty($errors)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error de validación: ' . implode(' ', $errors)];
    $_SESSION['form_data'] = $_POST;
    header('Location: edit.php?id=' . $id);
    exit;
}

try {
    $sql = "UPDATE ordenes SET 
                codigo_orden = :codigo_orden, 
                numero_orden_compra = :numero_orden_compra, -- NUEVO
                id_cliente = :id_cliente, 
                id_lugar = :id_lugar, 
                id_producto = :id_producto, 
                id_operacion = :id_operacion, 
                id_supervisor = :id_supervisor, 
                id_division = :id_division, 
                estado_orden = :estado_orden,
                observaciones = :observaciones -- NUEVO
            WHERE id = :id";
            
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':codigo_orden' => $codigo_orden,
        ':numero_orden_compra' => !empty($numero_orden_compra) ? $numero_orden_compra : null, // NUEVO
        ':id_cliente' => $id_cliente,
        ':id_lugar' => $id_lugar,
        ':id_producto' => $id_producto,
        ':id_operacion' => $id_operacion,
        ':id_supervisor' => $id_supervisor,
        ':id_division' => $id_division,
        ':estado_orden' => $estado_orden,
        ':observaciones' => !empty($observaciones) ? $observaciones : null, // NUEVO
        ':id' => $id
    ]);

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Orden actualizada correctamente.'];
    unset($_SESSION['form_data']); 
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    error_log("Error al actualizar la orden: " . $e->getMessage());
    $errorMessage = 'Error al actualizar la orden. Inténtelo de nuevo.';
    if ($e->getCode() == 23000) {
        $errorMessage = 'Error: Ya existe otra orden con el código "' . htmlspecialchars($codigo_orden) . '".';
    }
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $errorMessage];
    $_SESSION['form_data'] = $_POST;
    header('Location: edit.php?id=' . $id);
    exit;
}
?>
