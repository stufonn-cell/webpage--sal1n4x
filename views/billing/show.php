<?php

use PsiClinic\Domain\Invoices;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Icons;

$pageTitle = 'Factura ' . $invoice['number'];
?>
<div class="page-head">
    <div>
        <h1>Factura <?= e($invoice['number']) ?></h1>
        <p class="page-head__subtitle">
            <a href="/pacientes/<?= (int) $invoice['patient_id'] ?>"><?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?></a>
            · emitida el <?= e(formatDate((string) $invoice['issued_at'])) ?>
        </p>
    </div>
    <div class="flex gap-1">
        <span class="badge badge--<?= $invoice['status'] === 'paid' ? 'success' : 'warning' ?>"><?= e(Invoices::STATUSES[$invoice['status']]) ?></span>
        <button class="btn" type="button" onclick="window.print()"><?= Icons::render('print', 16) ?> Imprimir</button>
    </div>
</div>

<div class="grid grid--sidebar">
    <div class="card">
        <div class="card__head">
            <div>
                <h2 class="card__title"><?= e(Settings::get('clinic_name')) ?></h2>
                <span class="text-xs text-muted"><?= e(Settings::get('clinic_email')) ?> · <?= e(Settings::get('clinic_phone')) ?></span>
            </div>
            <span class="text-sm text-muted"><?= e($currency) ?></span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Descripcion</th><th class="text-right">Cantidad</th><th class="text-right">Unitario</th><th class="text-right">Importe</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="text-sm"><?= e($item['description']) ?></td>
                        <td class="text-right text-sm"><?= e(rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',')) ?></td>
                        <td class="text-right text-sm"><?= e(formatMoney($item['unit_price'])) ?></td>
                        <td class="text-right fw-600"><?= e(formatMoney($item['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card__body">
            <dl class="definition" style="max-width:320px;margin-left:auto">
                <dt>Subtotal</dt><dd class="text-right"><?= e(formatMoney($invoice['subtotal'])) ?></dd>
                <dt>Impuesto</dt><dd class="text-right"><?= e(formatMoney($invoice['tax'])) ?></dd>
                <dt>Total</dt><dd class="text-right fw-600"><?= e(formatMoney($invoice['total'])) ?></dd>
                <dt>Saldo</dt><dd class="text-right fw-600"><?= e(formatMoney($balance)) ?></dd>
            </dl>
            <?php if (trim((string) $invoice['notes']) !== ''): ?>
                <p class="text-sm text-soft mt-2"><?= e($invoice['notes']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card mb-2 no-print">
            <div class="card__head"><h2 class="card__title">Registrar pago</h2></div>
            <div class="card__body">
                <form method="post" action="/facturacion/<?= (int) $invoice['id'] ?>/pagos">
                    <?= csrf() ?>
                    <div class="field mb-2">
                        <label for="amount">Monto</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0" value="<?= e(number_format($balance, 2, '.', '')) ?>" required>
                    </div>
                    <div class="field mb-2">
                        <label for="paid_at">Fecha</label>
                        <input id="paid_at" name="paid_at" type="date" value="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <div class="field mb-2">
                        <label for="method">Medio de pago</label>
                        <select id="method" name="method">
                            <?php foreach (Invoices::METHODS as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="reference">Referencia</label>
                        <input id="reference" name="reference">
                    </div>
                    <button class="btn btn--primary btn--block" type="submit"<?= $balance <= 0 ? ' disabled' : '' ?>>
                        <?= Icons::render('check', 16) ?> Registrar pago
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">Pagos recibidos</h2></div>
            <div class="card__body card__body--flush">
                <?php foreach ($payments as $payment): ?>
                    <div class="list-row">
                        <?= Icons::render('check', 16) ?>
                        <span>
                            <span class="fw-600"><?= e(formatMoney($payment['amount'])) ?></span>
                            <span class="text-xs text-muted" style="display:block"><?= e(Invoices::METHODS[$payment['method']]) ?></span>
                        </span>
                        <span class="list-row__meta"><?= e(formatDate((string) $payment['paid_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ($payments === []): ?>
                    <div class="empty"><p class="text-sm mb-0">Sin pagos registrados.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
