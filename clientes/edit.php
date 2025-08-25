<?php
// clientes/edit.php - v1.1 (Corrección de error por valores nulos)

require_once '../auth.php';
require_login();
require_permission('ordenes.gestionar');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?status=error&message=ID de cliente no válido.');
    exit();
}

$id_cliente = $_GET['id'];

// Obtener datos del cliente
$stmt_cliente = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt_cliente->execute([$id_cliente]);
$cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header('Location: index.php?status=error&message=Cliente no encontrado.');
    exit();
}

// Obtener contactos del cliente
$stmt_contactos = $pdo->prepare("SELECT * FROM TB_Contact WHERE ID_Custumer = ? AND Contact_Delete = 0 ORDER BY Contact_Name");
$stmt_contactos->execute([$id_cliente]);
$contactos = $stmt_contactos->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestionar Cliente</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
        <li class="breadcrumb-item"><a href="index.php">Clientes</a></li>
        <li class="breadcrumb-item active"><?php echo htmlspecialchars($cliente['nombre_cliente'] ?? ''); ?></li>
    </ol>

    <!-- Formulario para editar datos del cliente -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-edit me-1"></i>
            Datos del Cliente
        </div>
        <div class="card-body">
            <form action="update.php" method="POST">
                <input type="hidden" name="id_cliente" value="<?php echo $cliente['id']; ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre_cliente" class="form-label">Nombre del Cliente</label>
                        <input type="text" class="form-control" id="nombre_cliente" name="nombre_cliente" value="<?php echo htmlspecialchars($cliente['nombre_cliente'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="rnc_cliente" class="form-label">RNC</label>
                        <input type="text" class="form-control" id="rnc_cliente" name="rnc_cliente" value="<?php echo htmlspecialchars($cliente['rnc_cliente'] ?? ''); ?>">
                    </div>
                    <div class="col-md-12">
                        <label for="Adress" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="Adress" name="Adress" value="<?php echo htmlspecialchars($cliente['Adress'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="Country" class="form-label">País</label>
                        <input type="text" class="form-control" id="Country" name="Country" value="<?php echo htmlspecialchars($cliente['Country'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="Phone_Number" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="Phone_Number" name="Phone_Number" value="<?php echo htmlspecialchars($cliente['Phone_Number'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado" required>
                            <option value="Activo" <?php echo ($cliente['estado'] === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="Inactivo" <?php echo ($cliente['estado'] === 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Actualizar Cliente</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sección de Contactos -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-address-book me-1"></i> Contactos</span>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
                <i class="fas fa-plus"></i> Añadir Contacto
            </button>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Cargo</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contactos)): ?>
                        <tr><td colspan="5" class="text-center">No hay contactos para este cliente.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contactos as $contacto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($contacto['Contact_Name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($contacto['Position'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($contacto['Email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($contacto['Phone_Number'] ?? ''); ?></td>
                            <td>
                                <!-- Próximamente: botones para editar/eliminar contactos -->
                                <button class="btn btn-sm btn-secondary" disabled>Editar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para Añadir Contacto -->
<div class="modal fade" id="addContactModal" tabindex="-1" aria-labelledby="addContactModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addContactModalLabel">Añadir Nuevo Contacto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="store_contact.php" method="POST">
        <div class="modal-body">
            <input type="hidden" name="ID_Custumer" value="<?php echo $id_cliente; ?>">
            <div class="mb-3">
                <label for="Contact_Name" class="form-label">Nombre del Contacto</label>
                <input type="text" class="form-control" id="Contact_Name" name="Contact_Name" required>
            </div>
            <div class="mb-3">
                <label for="Position" class="form-label">Cargo (Opcional)</label>
                <input type="text" class="form-control" id="Position" name="Position">
            </div>
            <div class="mb-3">
                <label for="Email" class="form-label">Email (Opcional)</label>
                <input type="email" class="form-control" id="Email" name="Email">
            </div>
            <div class="mb-3">
                <label for="Phone_Number_Contact" class="form-label">Teléfono</label>
                <input type="text" class="form-control" id="Phone_Number_Contact" name="Phone_Number" required>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button type="submit" class="btn btn-primary">Guardar Contacto</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
