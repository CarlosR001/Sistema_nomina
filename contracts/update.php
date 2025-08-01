<?php
// contracts/update.php - DEBUG MODE
// Este script ha sido modificado para mostrar los datos recibidos en lugar de procesarlos.

require_once '../auth.php';
require_login();
require_role('Admin');

echo "<pre style='font-family: monospace; border: 2px solid #00f; padding: 10px; background-color: #eef;'>";
echo "<strong>--- INICIO DE DEBUG: contracts/update.php ---</strong>

";

echo "<strong>Datos recibidos del formulario (\$_POST):</strong>
";
print_r($_POST);

echo "
<strong>--- FIN DE DEBUG ---</strong>";
echo "</pre>";

die(); // Detenemos la ejecución para ver solo la información de depuración.

// El código original de procesamiento está desactivado temporalmente debajo.
/*
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // ... (código original)
}
// ...
*/
?>
