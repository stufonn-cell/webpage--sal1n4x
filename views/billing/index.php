<?php

use PsiClinic\Core\View;
use PsiClinic\Domain\Invoices;
use PsiClinic\Support\Icons;

$pageTitle = 'Facturacion';
?>
<div class="page-head">
    <div>
        <h1>Facturacion</h1>
        <p class="page-head__subtitle"><?= (int) $result['total'] ?> facturas · moneda <?= e($currency) ?></p>
    </div>
    <a class="btn btn--primary" href="/facturacion/nueva"><?= Icons::render('plus', 16) ?> Nueva factura</a>
</div>

<form class="filters" method="get" action="/facturacion">
    <select name="estado">
        <option value="">Todos los estados</option>
        <?php foreach (Invoices::STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= Icons::render('search', 16) ?> Filtrar</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Numero</th><th>Paciente</th><th>Emision</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($result['rows'] as $invoice): ?>
                <?php $balance = (float) $invoice['total'] - (float) $invoice['paid']; ?>
                <tr>
                    <td class="fw-600"><?= e($invoice['number']) ?></td>
                    <td class="text-sm text-soft"><?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?></td>
                    <td class="text-sm"><?= e(formatDate((string) $invoice['issued_at'])) ?></td>
                    <td class="text-sm"><?= e(formatMoney($invoice['total'])) ?></td>
                    <td class="text-sm"><?= e(formatMoney($invoice['paid'])) ?></td>
                    <td class="text-sm <?= $balance > 0 ? 'fw-600' : 'text-muted' ?>"><?= e(formatMoney($balance)) ?></td>
                    <td><span class="badge badge--<?= $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'void' ? 'danger' : 'warning') ?>"><?= e(Invoices::STATUSES[$invoice['status']]) ?></span></td>
                    <td class="text-right"><a class="btn btn--ghost btn--sm" href="/facturacion/<?= (int) $invoice['id'] ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['rows'] === []): ?>
                <tr><td colspan="8"><div class="empty"><div class="empty__title">Sin facturas</div></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= View::partial('partials/pagination', ['result' => $result, 'baseUrl' => '/facturacion?estado=' . urlencode($status)]) ?>
</div>
