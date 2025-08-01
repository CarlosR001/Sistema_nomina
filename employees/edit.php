<?php
// employees/edit.php
// Formulario para editar un empleado existente.

require_once '../auth.php';
require_login();
require_role('Admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?status=error&message=ID de empleado no válido.');
    exit();
}
$id_empleado = $_GET['id'];

// Obtener los datos del empleado
$stmt = $pdo->prepare("SELECT * FROM Empleados WHERE id = ?");
$stmt->execute([$id_empleado]);
$employee = $stmt->fetch();

if (!$employee) {
    header('Location: index.php?status=error&message=Empleado no encontrado.');
    exit();
}

// Obtener lista de bancos
$bancos = $pdo->query("SELECT id, nombre_banco FROM Bancos ORDER BY nombre_banco")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Editar Empleado</h1>

<div class="card">
    <div class="card-header">
        Modificar información de <?php echo htmlspecialchars($employee['nombres'] . ' ' . $employee['primer_apellido']); ?>
    </div>
    <div class="card-body">
        <form action="update.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($employee['id']); ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="cedula" class="form-label">Cédula</label>
                    <input type="text" class="form-control" name="cedula" value="<?php echo htmlspecialchars($employee['cedula']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="nss" class="form-label">NSS (Número de Seguridad Social)</label>
                    <input type="text" class="form-control" name="nss" value="<?php echo htmlspecialchars($employee['nss']); ?>">
                </div>
                
                <div class="col-md-4">
                    <label for="nombres" class="form-label">Nombres</label>
                    <input type="text" class="form-control" name="nombres" value="<?php echo htmlspecialchars($employee['nombres']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="primer_apellido" class="form-label">Primer Apellido</label>
                    <input type="text" class="form-control" name="primer_apellido" value="<?php echo htmlspecialchars($employee['primer_apellido']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="segundo_apellido" class="form-label">Segundo Apellido</label>
                    <input type="text" class="form-control" name="segundo_apellido" value="<?php echo htmlspecialchars($employee['segundo_apellido']); ?>">
                </div>

                <div class="col-md-6">
                    <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                    <input type="date" class="form-control" name="fecha_nacimiento" value="<?php echo htmlspecialchars($employee['fecha_nacimiento']); ?>">
                </div>
                <div class="col-md-6">
                    <label for="sexo" class="form-label">Sexo</label>
                    <select class="form-select" name="sexo">
                        <option value="Masculino" <?php echo ($employee['sexo'] == 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                        <option value="Femenino" <?php echo ($employee['sexo'] == 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="direccion_completa" class="form-label">Dirección Completa</label>
                    <textarea class="form-control" name="direccion_completa" rows="2"><?php echo htmlspecialchars($employee['direccion_completa']); ?></textarea>
                </div>
                
                <div class="col-md-6">
                    <label for="telefono_principal" class="form-label">Teléfono Principal</label>
                    <input type="tel" class="form-control" name="telefono_principal" value="<?php echo htmlspecialchars($employee['telefono_principal']); ?>">
                </div>
                <div class="col-md-6">
                    <label for="email_personal" class="form-label">Email Personal</label>
                    <input type="email" class="form-control" name="email_personal" value="<?php echo htmlspecialchars($employee['email_personal']); ?>">
                </div>

                <hr class="my-2">

                <div class="col-md-4">
                    <label for="id_banco" class="form-label">Banco</label>
                    <select class="form-select" name="id_banco">
                        <option value="">No especificado</option>
                        <?php foreach($bancos as $banco): ?>
                            <option value="<?php echo $banco['id']; ?>" <?php echo ($employee['id_banco'] == $banco['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($banco['nombre_banco']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="tipo_cuenta_bancaria" class="form-label">Tipo de Cuenta</label>
                    <select class="form-select" name="tipo_cuenta_bancaria">
                        <option value="">No especificado</option>
                        <option value="Ahorros" <?php echo ($employee['tipo_cuenta_bancaria'] == 'Ahorros') ? 'selected' : ''; ?>>Ahorros</option>
                        <option value="Corriente" <?php echo ($employee['tipo_cuenta_bancaria'] == 'Corriente') ? 'selected' : ''; ?>>Corriente</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="numero_cuenta_bancaria" class="form-label">Número de Cuenta</label>
                    <input type="text" class="form-control" name="numero_cuenta_bancaria" value="<?php echo htmlspecialchars($employee['numero_cuenta_bancaria']); ?>">
                </div>

                 <div class="col-md-6">
                    <label for="estado_empleado" class="form-label">Estado del Empleado</label>
                    <select class="form-select" name="estado_empleado" required>
                        <option value="Activo" <?php echo ($employee['estado_empleado'] == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                        <option value="Inactivo" <?php echo ($employee['estado_empleado'] == 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                        <option value="Suspendido" <?php echo ($employee['estado_empleado'] == 'Suspendido') ? 'selected' : ''; ?>>Suspendido</option>
                        <option value="De Vacaciones" <?php echo ($employee['estado_empleado'] == 'De Vacaciones') ? 'selected' : ''; ?>>De Vacaciones</option>
                        <option value="Licencia" <?php echo ($employee['estado_empleado'] == 'Licencia') ? 'selected' : ''; ?>>Licencia</option>
                    </select>
                </div>

            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary">Actualizar Empleado</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
