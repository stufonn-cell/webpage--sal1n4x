<?php

use PsiClinic\Support\Icons;

$pageTitle = 'Nueva factura';
?>
<div class="page-head">
    <div>
        <h1>Nueva factura</h1>
        <p class="page-head__subtitle">Numero asignado automaticamente: <?= e($number) ?></p>
    </div>
    <a class="btn btn--ghost" href="/facturacion">Volver</a>
</div>

<form method="post" action="/facturacion">
    <?= csrf() ?>

    <div class="card mb-2">
        <div class="card__body">
            <div class="form-grid">
                <div class="field<?= hasError('patient_id') ?>">
                    <label for="patient_id">Paciente</label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">Selecciona un paciente</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= (int) $patient['id'] ?>"><?= e($patient['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= fieldError('patient_id') ?>
                </div>
                <div class="field">
                    <label for="issued_at">Fecha de emision</label>
                    <input id="issued_at" name="issued_at" type="date" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div class="field">
                    <label for="due_at">Fecha de vencimiento</label>
                    <input id="due_at" name="due_at" type="date">
                </div>
                <div class="field">
                    <label for="tax_rate">Impuesto (%)</label>
                    <input id="tax_rate" name="tax_rate" type="number" step="0.1" min="0" value="0">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card__head">
            <h2 class="card__title">Conceptos</h2>
            <button class="btn btn--sm" type="button" data-add-row><?= Icons::render('plus', 15) ?> Agregar linea</button>
        </div>
        <div class="table-wrap">
            <table class="data" data-invoice-items>
                <thead><tr><th>Descripcion</th><th style="width:110px">Cantidad</th><th style="width:160px">Valor unitario</th><th style="width:140px" class="text-right">Importe</th><th style="width:52px"></th></tr></thead>
                <tbody>
                <tr>
                    <td><input name="description[]" placeholder="Sesion de psicoterapia individual"></td>
                    <td><input name="quantity[]" type="number" step="0.5" min="0" value="1"></td>
                    <td><input name="unit_price[]" type="number" step="0.01" min="0" value="<?= e($defaultFee) ?>"></td>
                    <td class="text-right fw-600" data-amount>0,00</td>
                    <td class="text-right"><button class="btn btn--ghost btn--sm" type="button" data-remove-row><?= Icons::render('trash', 15) ?></button></td>
                </tr>
                </tbody>
            </table>
        </div>
        <div class="card__body">
            <dl class="definition" style="max-width:320px;margin-left:auto">
                <dt>Subtotal</dt><dd class="text-right" data-subtotal>0,00</dd>
                <dt>Impuesto</dt><dd class="text-right" data-tax>0,00</dd>
                <dt>Total</dt><dd class="text-right fw-600" data-total>0,00</dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card__body">
            <div class="field mb-2">
                <label for="notes">Notas</label>
                <textarea id="notes" name="notes"></textarea>
            </div>
            <div class="form-actions">
                <a class="btn btn--ghost" href="/facturacion">Cancelar</a>
                <button class="btn btn--primary" type="submit"><?= Icons::render('check', 16) ?> Emitir factura</button>
            </div>
        </div>
    </div>
</form>
