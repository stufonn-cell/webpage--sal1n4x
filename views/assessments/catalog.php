<?php

use PsiClinic\Domain\Instruments;
use PsiClinic\Support\Icons;

$pageTitle = 'Catalogo de instrumentos';
?>
<div class="page-head">
    <div>
        <h1>Catalogo de instrumentos</h1>
        <p class="page-head__subtitle">Seis escalas de uso libre con correccion, bandas de severidad e interpretacion automatica.</p>
    </div>
    <a class="btn btn--ghost" href="/evaluaciones">Volver</a>
</div>

<div class="grid grid--3">
    <?php foreach ($instruments as $code => $instrument): ?>
        <div class="card">
            <div class="card__head">
                <div>
                    <h2 class="card__title"><?= e($code) ?></h2>
                    <span class="text-xs text-muted"><?= e($instrument['name']) ?></span>
                </div>
                <span class="badge badge--brand"><?= e($instrument['domain']) ?></span>
            </div>
            <div class="card__body">
                <p class="text-sm text-soft"><?= e($instrument['description']) ?></p>
                <dl class="definition text-sm">
                    <dt>Items</dt><dd><?= count($instrument['items']) ?></dd>
                    <dt>Ventana</dt><dd><?= e($instrument['window']) ?></dd>
                    <dt>Rango</dt><dd>0 a <?= Instruments::maxScore($code) ?></dd>
                    <?php if (isset($instrument['subscales'])): ?>
                        <dt>Subescalas</dt><dd><?= e(implode(', ', array_keys($instrument['subscales']))) ?></dd>
                    <?php endif; ?>
                </dl>

                <div class="mt-2">
                    <?php foreach ($instrument['bands'] as $band): ?>
                        <div class="flex-between text-xs" style="padding:.25rem 0;border-bottom:1px solid var(--border)">
                            <span class="text-soft"><?= e($band[2]) ?></span>
                            <span class="text-muted"><?= (int) $band[0] ?> - <?= (int) $band[1] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <a class="btn btn--primary btn--block mt-2" href="/evaluaciones/nueva?instrumento=<?= urlencode($code) ?>">
                    <?= Icons::render('clipboard', 16) ?> Aplicar
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
