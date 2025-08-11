<?php
// ordenes/update.php - v2.0 (Seguridad y Validación Mejoradas)

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

// --- 1. Iniciar manejo de sesión para mensajes flash ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 2. Validación del Método y Token CSRF ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    die('Método no permitido.');
}

// Regenerar token en cada carga de formulario para mayor seguridad
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Error de seguridad (CSRF token inválido). Inténtelo de nuevo.'
    ];
    // Es mejor redirigir a una página segura o a la de origen con un error claro
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    header('Location: ' . ($id ? 'edit.php?id=' . $id : 'index.php'));
    exit;
}

// --- 3. Recolección y Validación de Datos ---
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$codigo_orden = trim($_POST['codigo_orden'] ?? '');
$id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
$id_lugar = filter_input(INPUT_POST, 'id_lugar', FILTER_VALIDATE_INT);
$id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
$id_operacion = filter_input(INPUT_POST, 'id_operacion', FILTER_VALIDATE_INT);
$id_supervisor = filter_input(INPUT_POST, 'id_supervisor', FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
$id_division = filter_input(INPUT_POST, 'id_division', FILTER_VALIDATE_INT);
$fecha_creacion = $_POST['fecha_creacion'] ?? null;
$fecha_finalizacion = !empty($_POST['fecha_finalizacion']) ? $_POST['fecha_finalizacion'] : null;
$estado_orden = $_POST['estado_orden'] ?? 'Pendiente';

$errors = [];

// Validación de campos obligatorios y formato
if (!$id) {
    $errors[] = 'ID de orden inválido.';
}
if (empty($codigo_orden)) {
    $errors[] = 'El código de la orden es obligatorio.';
}
if (!$id_cliente) {
    $errors[] = 'Debe seleccionar un cliente.';
}
if (!$id_lugar) {
    $errors[] = 'Debe seleccionar un lugar.';
}
if (!$id_producto) {
    $errors[] = 'Debe seleccionar un producto.';
}
if (!$id_operacion) {
    $errors[] = 'Debe seleccionar una operación.';
}
if (!$id_division) {
    $errors[] = 'Debe seleccionar una división.';
}
if (empty($fecha_creacion) || !DateTime::createFromFormat('Y-m-d', $fecha_creacion)) {
    $errors[] = 'La fecha de creación es inválida.';
}
if ($fecha_finalizacion && !DateTime::createFromFormat('Y-m-d', $fecha_finalizacion)) {
    $errors[] = 'La fecha de finalización es inválida.';
}
if (!in_array($estado_orden, ['Pendiente', 'En Proceso', 'Finalizada', 'Cancelada'])) {
    $errors[] = 'El estado de la orden es inválido.';
}

if (!empty($errors)) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Error de validación: ' . implode(' ', $errors)
    ];
    $_SESSION['form_data'] = $_POST; // Guardar datos para rellenar el formulario
    header('Location: edit.php?id=' . $id);
    exit;
}

// --- 4. Ejecución de la Consulta ---
try {
    $stmt = $pdo->prepare(
        "UPDATE ordenes SET 
            codigo_orden = :codigo_orden, id_cliente = :id_cliente, id_lugar = :id_lugar, 
            id_producto = :id_producto, id_operacion = :id_operacion, id_supervisor = :id_supervisor, 
            id_division = :id_division, fecha_creacion = :fecha_creacion, fecha_finalizacion = :fecha_finalizacion, 
            estado_orden = :estado_orden 
         WHERE id = :id"
    );
    
    $stmt->execute([
        ':codigo_orden' => $codigo_orden,
        ':id_cliente' => $id_cliente,
        ':id_lugar' => $id_lugar,
        ':id_producto' => $id_producto,
        ':id_operacion' => $id_operacion,
        ':id_supervisor' => $id_supervisor,
        ':id_division' => $id_division,
        ':fecha_creacion' => $fecha_creacion,
        ':fecha_finalizacion' => $fecha_finalizacion,
        ':estado_orden' => $estado_orden,
        ':id' => $id
    ]);

    $_SESSION['flash_message'] = [
        'type' => 'success',
        'message' => 'Orden actualizada correctamente.'
    ];
    // Limpiar datos de formulario en sesión si todo fue exitoso
    unset($_SESSION['form_data']); 
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    error_log("Error al actualizar la orden: " . $e->getMessage()); // Log del error real
    
    $errorMessage = 'Error al actualizar la orden. Inténtelo de nuevo.';
    if ($e->getCode() == 23000) { // Error de clave duplicada (UNIQUE constraint)
        $errorMessage = 'Error: Ya existe otra orden con el código "' . htmlspecialchars($codigo_orden) . '".';
    }

    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => $errorMessage
    ];
    $_SESSION['form_data'] = $_POST;
    header('Location: edit.php?id=' . $id);
    exit;
}
