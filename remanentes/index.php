<?php
require_once '../auth.php';
require_login();
require_permission('nomina.remanentes');

// --- Lógica para obtener los remanentes ---

$remanentes = [];

try {
    // 1. Encontrar todas las nóminas procesadas de tipo Administrativa.
    $stmt_nominas = $pdo->query("SELECT id, periodo_inicio, periodo_fin FROM nominasprocesadas WHERE tipo_nomina_procesada = 'Administrativa' ORDER BY periodo_fin DESC");
    $nominas_procesadas = $stmt_nominas->fetchAll(PDO::FETCH_ASSOC);

    foreach ($nominas_procesadas as $nomina) {
        $id_nomina = $nomina['id'];

        // 2. Para cada nómina, buscar los contratos que tuvieron un ingreso de salario igual a cero.
        $stmt_salario_cero = $pdo->prepare("
            SELECT id_contrato 
            FROM nominadetalle 
            WHERE id_nomina_procesada = ? 
            AND codigo_concepto = 'ING-SALARIO' 
            AND monto_resultado = 0
        ");
        $stmt_salario_cero->execute([$id_nomina]);
        $contratos_con_salario_cero = $stmt_salario_cero->fetchAll(PDO::FETCH_COLUMN);

        if (empty($contratos_con_salario_cero)) {
            continue;
        }

        // 3. Para esos contratos, verificar si existen deducciones en esa misma nómina.
        $placeholders = rtrim(str_repeat('?,', count($contratos_con_salario_cero)), ',');
        $sql_deducciones = "
            SELECT 
                nd.id_contrato, 
                c.nombre_completo,
                GROUP_CONCAT(CONCAT(nd.descripcion_concepto, ': ', FORMAT(nd.monto_resultado, 2)) SEPARATOR '<br>') as deducciones_detalle,
                SUM(nd.monto_resultado) as total_remanente
            FROM nominadetalle nd
            JOIN contratos co ON nd.id_contrato = co.id
            JOIN employees c ON co.id_empleado = c.id
            WHERE nd.id_nomina_procesada = ? 
            AND nd.id_contrato IN ($placeholders)
            AND nd.tipo_concepto = 'Deducción'
            GROUP BY nd.id_contrato, c.nombre_completo
            HAVING total_remanente > 0
        ";
        
        $params = array_merge([$id_nomina], $contratos_con_salario_cero);
        $stmt_remanentes = $pdo->prepare($sql_deducciones);
        $stmt_remanentes->execute($params);
        
        $resultados = $stmt_remanentes->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resultados as $res) {
            $remanentes[] = [
                'id_nomina_origen' => $id_nomina,
                'id_contrato' => $res['id_contrato'],
                'nombre_completo' => $res['nombre_completo'],
                'periodo' => $nomina['periodo_inicio'] . ' al ' . $nomina['periodo_fin'],
                'deducciones_detalle' => $res['deducciones_detalle'],
                'total_remanente' => $res['total_remanente']
            ];
        }
    }

} catch (Exception $e) {
    $error_message = "Error al consultar remanentes: " . $e->getMessage();
}


// --- INICIO DE LA VISTA ---
include_once '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Gestión de Remanentes de Nóminas Omitidas</h2>
    <p>Aquí se listan las nóminas que fueron omitidas (salario en cero) pero que generaron deducciones. Puede transferir estas deducciones como una novedad a la nómina anterior del empleado.</p>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['message']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['message']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Remanentes Detectados</h5>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Período Omitido</th>
                        <th>Deducciones (Remanente)</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($remanentes)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No se encontraron remanentes pendientes.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($remanentes as $rem): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rem['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($rem['periodo']); ?></td>
                                <td><?php echo $rem['deducciones_detalle']; ?></td>
                                <td><?php echo htmlspecialchars(number_format($rem['total_remanente'], 2)); ?></td>
                                <td>
                                    <form action="transferir_remanente.php" method="POST" onsubmit="return confirm('¿Está seguro de que desea transferir este remanente a la nómina anterior?');">
                                        <input type="hidden" name="id_nomina_origen" value="<?php echo $rem['id_nomina_origen']; ?>">
                                        <input type="hidden" name="id_contrato" value="<?php echo $rem['id_contrato']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Transferir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
