<?php

use PsiClinic\Core\Auth;
use PsiClinic\Support\Icons;

$pageTitle = 'Consentimiento';
$isSigned = $consent['status'] === 'signed';
$canSign = Auth::is('patient') || Auth::isStaff();
?>
<div class="page-head">
    <div>
        <h1><?= e($consent['title']) ?></h1>
        <p class="page-head__subtitle">
            <?= e($consent['first_name'] . ' ' . $consent['last_name']) ?> · <?= e($consent['record_number']) ?>
        </p>
    </div>
    <span class="badge badge--<?= $isSigned ? 'success' : 'warning' ?>"><?= $isSigned ? 'Firmado' : 'Pendiente de firma' ?></span>
</div>

<div class="card mb-2">
    <div class="card__body">
        <p class="text-soft" style="white-space:pre-line"><?= e($consent['body']) ?></p>
    </div>
</div>

<?php if ($isSigned): ?>
    <div class="card">
        <div class="card__head"><h2 class="card__title">Firma registrada</h2></div>
        <div class="card__body">
            <p class="text-sm mb-1"><strong><?= e($consent['signed_name']) ?></strong></p>
            <p class="text-xs text-muted">Firmado el <?= e(formatDateTime($consent['signed_at'])) ?> desde <?= e($consent['signed_ip']) ?></p>
            <?php if (trim((string) $consent['signature_svg']) !== ''): ?>
                <div style="max-width:340px;color:var(--text)"><?= $consent['signature_svg'] ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($canSign): ?>
    <div class="card">
        <div class="card__head"><h2 class="card__title">Firmar documento</h2></div>
        <div class="card__body">
            <form method="post" action="/consentimientos/<?= (int) $consent['id'] ?>/firmar">
                <?= csrf() ?>
                <div class="field mb-2">
                    <label for="signed_name">Nombre completo</label>
                    <input id="signed_name" name="signed_name" required
                           value="<?= e($consent['first_name'] . ' ' . $consent['last_name']) ?>">
                </div>

                <div class="field mb-2">
                    <label>Firma</label>
                    <svg data-signature viewBox="0 0 600 200" style="width:100%;height:170px;border:1px dashed var(--border-strong);border-radius:var(--radius);background:var(--surface-alt);color:var(--text);touch-action:none"></svg>
                    <input type="hidden" name="signature_svg">
                    <span class="field-hint">Dibuja con el mouse o el dedo. La firma se guarda como trazo vectorial.</span>
                </div>

                <div class="flex gap-1">
                    <button class="btn" type="button" data-signature-clear>Limpiar</button>
                    <button class="btn btn--primary" type="submit"><?= Icons::render('check', 16) ?> Firmar consentimiento</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
