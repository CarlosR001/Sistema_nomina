<?php
// payroll/payslip_template.php
// Plantilla HTML para el cuerpo del correo del volante de pago.
// Esta plantilla respeta la bandera 'incluir_en_volante'.
// Asume que las variables ($empleado, $nomina, $ingresos, $deducciones, etc.) están disponibles.
?>
<style>
    body { font-family: Arial, sans-serif; color: #333; }
    .card { border: 1px solid #ddd; border-radius: 5px; margin: 20px; }
    .card-header { background-color: #343a40; color: white; padding: 10px; border-bottom: 1px solid #ddd; }
    .card-body { padding: 20px; }
    .row { display: flex; flex-wrap: wrap; margin-right: -15px; margin-left: -15px; }
    .col-md-4, .col-md-6 { position: relative; width: 100%; padding-right: 15px; padding-left: 15px; }
    @media (min-width: 768px) {
        .col-md-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
        .col-md-6 { flex: 0 0 50%; max-width: 50%; }
        .text-md-end { text-align: right; }
    }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 5px; border-bottom: 1px solid #eee; }
    .text-end { text-align: right; }
    .text-success { color: #28a745; }
    .text-danger { color: #dc3545; }
    .text-primary { color: #007bff; }
    .fw-bold { font-weight: bold; }
    h3, h4, h5 { margin-top: 0; margin-bottom: 0.5rem; }
    hr { margin-top: 1rem; margin-bottom: 1rem; border: 0; border-top: 1px solid rgba(0,0,0,.1); }
</style>

<div class="card">
    <div class="card-header">
        Recibo de Nómina
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h4><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['primer_apellido']); ?></h4>
                <p><strong>Cédula:</strong> <?php echo htmlspecialchars($empleado['cedula']); ?></p>
                <p><strong>Posición:</strong> <?php echo htmlspecialchars($empleado['nombre_posicion']); ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <p><strong>ID de Nómina:</strong> <?php echo htmlspecialchars($nomina['id']); ?></p>
                <p><strong>Período de Pago:</strong> <?php echo htmlspecialchars($nomina['periodo_inicio'] . ' al ' . $nomina['periodo_fin']); ?></p>
            </div>
        </div>
        <hr>
        <div class="row">
            <!-- Columna de Ingresos -->
            <div class="col-md-6">
                <h5 class="text-success">Ingresos</h5>
                <table>
                    <?php foreach ($ingresos as $item): ?>
                        <?php if (!empty($item['incluir_en_volante'])): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['descripcion_concepto']); ?></td>
                            <td class="text-end">$<?php echo number_format($item['monto_resultado'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
                <hr>
                <div style="display: flex; justify-content: space-between;" class="fw-bold">
                    <span>Total Ingresos</span>
                    <span>$<?php echo number_format($total_ingresos, 2); ?></span>
                </div>
            </div>

            <!-- Columna de Deducciones -->
            <div class="col-md-6">
                <h5 class="text-danger">Deducciones</h5>
                <table>
                     <?php foreach ($deducciones as $item): ?>
                        <?php if (!empty($item['incluir_en_volante'])): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['descripcion_concepto']); ?></td>
                            <td class="text-end">-$<?php echo number_format($item['monto_resultado'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
                <hr>
                <div style="display: flex; justify-content: space-between;" class="fw-bold">
                    <span>Total Deducciones</span>
                    <span>-$<?php echo number_format($total_deducciones, 2); ?></span>
                </div>
            </div>
        </div>
        <hr>
        <div class="text-end">
            <h3>Neto a Pagar: <span class="text-primary">$<?php echo number_format($neto_pagar, 2); ?></span></h3>
        </div>
    </div>
</div>
