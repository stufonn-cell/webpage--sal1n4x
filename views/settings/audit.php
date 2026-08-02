<?php

$pageTitle = 'Auditoria';
$actionLabels = [
    'login' => 'Inicio de sesion',
    'logout' => 'Cierre de sesion',
    'create' => 'Creacion',
    'update' => 'Actualizacion',
    'delete' => 'Eliminacion',
    'view' => 'Consulta',
    'sign' => 'Firma',
    'upload' => 'Carga de archivo',
    'download' => 'Descarga',
    'payment' => 'Registro de pago',
    'toggle' => 'Cambio de estado',
];
?>
<div class="page-head">
    <div>
        <h1>Registro de auditoria</h1>
        <p class="page-head__subtitle">Ultimos <?= count($entries) ?> eventos sobre datos clinicos.</p>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Accion</th><th>Entidad</th><th>Origen</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <?php $action = explode(':', (string) $entry['action'])[0]; ?>
                <tr>
                    <td class="text-sm text-soft"><?= e(formatDateTime((string) $entry['created_at'])) ?></td>
                    <td class="text-sm"><?= e($entry['full_name'] ?: 'Sistema') ?></td>
                    <td><span class="badge"><?= e($actionLabels[$action] ?? $entry['action']) ?></span></td>
                    <td class="text-sm text-soft"><?= e($entry['entity']) ?><?= $entry['entity_id'] ? ' #' . (int) $entry['entity_id'] : '' ?></td>
                    <td class="text-xs text-muted"><?= e($entry['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($entries === []): ?>
                <tr><td colspan="5"><div class="empty"><div class="empty__title">Sin eventos registrados</div></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
