<?php
// employees/create.php
// v1.1 - Formulario completo para crear un empleado.

require_once '../auth.php';
require_login();
require_permission('empleados.gestionar');

// Obtener lista de bancos
$bancos = $pdo->query("SELECT id, nombre_banco FROM bancos ORDER BY nombre_banco")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="mb-4">Añadir Nuevo Empleado</h1>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars(urldecode($_GET['message'])); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        Información del Empleado
    </div>
    <div class="card-body">
        <form action="store.php" method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="cedula" class="form-label">Cédula</label>
                    <input type="text" class="form-control" name="cedula" required>
                </div>
                <div class="col-md-6">
                    <label for="nss" class="form-label">NSS (Número de Seguridad Social)</label>
                    <input type="text" class="form-control" name="nss">
                </div>
                
                <div class="col-md-4">
                    <label for="nombres" class="form-label">Nombres</label>
                    <input type="text" class="form-control" name="nombres" required>
                </div>
                <div class="col-md-4">
                    <label for="primer_apellido" class="form-label">Primer Apellido</label>
                    <input type="text" class="form-control" name="primer_apellido" required>
                </div>
                <div class="col-md-4">
                    <label for="segundo_apellido" class="form-label">Segundo Apellido</label>
                    <input type="text" class="form-control" name="segundo_apellido">
                </div>

                <div class="col-md-6">
                    <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                    <input type="date" class="form-control" name="fecha_nacimiento">
                </div>
                <div class="col-md-6">
                    <label for="sexo" class="form-label">Sexo</label>
                    <select class="form-select" name="sexo">
                        <option value="Masculino">Masculino</option>
                        <option value="Femenino">Femenino</option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="direccion_completa" class="form-label">Dirección Completa</label>
                    <textarea class="form-control" name="direccion_completa" rows="2"></textarea>
                </div>
                
                <div class="col-md-6">
                    <label for="telefono_principal" class="form-label">Teléfono Principal</label>
                    <input type="tel" class="form-control" name="telefono_principal">
                </div>
                <div class="col-md-6">
                    <label for="email_personal" class="form-label">Email Personal</label>
                    <input type="email" class="form-control" name="email_personal">
                </div>

                <hr class="my-2">
                <h5 class="col-12">Información Bancaria</h5>

                <div class="col-md-4">
                    <label for="id_banco" class="form-label">Banco</label>
                    <select class="form-select" name="id_banco">
                        <option value="">No especificado</option>
                        <?php foreach($bancos as $banco): ?>
                            <option value="<?php echo $banco['id']; ?>"><?php echo htmlspecialchars($banco['nombre_banco']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="tipo_cuenta_bancaria" class="form-label">Tipo de Cuenta</label>
                    <select class="form-select" name="tipo_cuenta_bancaria">
                        <option value="">No especificado</option>
                        <option value="Ahorros">Ahorros</option>
                        <option value="Corriente">Corriente</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="numero_cuenta_bancaria" class="form-label">Número de Cuenta</label>
                    <input type="text" class="form-control" name="numero_cuenta_bancaria">
                </div>
            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary">Guardar Empleado</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
